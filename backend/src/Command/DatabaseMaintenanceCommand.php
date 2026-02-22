<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:db:maintenance',
    description: 'Database maintenance: VACUUM ANALYZE, table sizes, index stats, bloat detection.',
)]
class DatabaseMaintenanceCommand extends Command
{
    /** Tables that are critical for performance and should be vacuumed. */
    private const KEY_TABLES = [
        'vehicle_positions',
        'vehicle_last_position',
        'vehicle_checkpoint',
        'route_plan',
        'route_stop',
        'shipment',
        'shipment_event',
        'audit_log',
    ];

    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report only, do not run VACUUM ANALYZE');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $io->title('Database Maintenance');

        if ($dryRun) {
            $io->note('Dry-run mode: no VACUUM ANALYZE will be executed.');
        }

        // 1. Report table sizes
        $io->section('Table Sizes');
        $this->reportTableSizes($io);

        // 2. Report index usage
        $io->section('Index Usage Statistics');
        $this->reportIndexUsage($io);

        // 3. Check for bloated tables
        $io->section('Table Bloat Estimates');
        $this->reportBloat($io);

        // 4. VACUUM ANALYZE
        if (!$dryRun) {
            $io->section('VACUUM ANALYZE');
            $this->vacuumAnalyze($io);
        }

        $io->success($dryRun ? 'Dry-run report complete.' : 'Maintenance complete.');

        return self::SUCCESS;
    }

    private function reportTableSizes(SymfonyStyle $io): void
    {
        $rows = $this->connection->fetchAllAssociative("
            SELECT
                schemaname || '.' || tablename AS table_name,
                pg_size_pretty(pg_total_relation_size(schemaname || '.' || tablename)) AS total_size,
                pg_size_pretty(pg_relation_size(schemaname || '.' || tablename)) AS table_size,
                pg_size_pretty(
                    pg_total_relation_size(schemaname || '.' || tablename)
                    - pg_relation_size(schemaname || '.' || tablename)
                ) AS index_size,
                COALESCE(n_live_tup, 0) AS live_rows
            FROM pg_tables
            LEFT JOIN pg_stat_user_tables ON pg_tables.tablename = pg_stat_user_tables.relname
                AND pg_tables.schemaname = pg_stat_user_tables.schemaname
            WHERE pg_tables.schemaname = 'public'
            ORDER BY pg_total_relation_size(schemaname || '.' || tablename) DESC
        ");

        $headers = ['Table', 'Total Size', 'Data Size', 'Index Size', 'Live Rows'];
        $tableRows = [];
        foreach ($rows as $row) {
            $tableRows[] = [
                $row['table_name'],
                $row['total_size'],
                $row['table_size'],
                $row['index_size'],
                number_format((int) $row['live_rows']),
            ];
        }

        $io->table($headers, $tableRows);
    }

    private function reportIndexUsage(SymfonyStyle $io): void
    {
        $rows = $this->connection->fetchAllAssociative("
            SELECT
                schemaname || '.' || relname AS table_name,
                indexrelname AS index_name,
                idx_scan AS scans,
                pg_size_pretty(pg_relation_size(indexrelid)) AS index_size,
                CASE
                    WHEN idx_scan = 0 THEN 'UNUSED'
                    WHEN idx_scan < 50 THEN 'LOW'
                    ELSE 'OK'
                END AS status
            FROM pg_stat_user_indexes
            WHERE schemaname = 'public'
            ORDER BY idx_scan ASC, pg_relation_size(indexrelid) DESC
            LIMIT 30
        ");

        $headers = ['Table', 'Index', 'Scans', 'Size', 'Status'];
        $tableRows = [];
        foreach ($rows as $row) {
            $tableRows[] = [
                $row['table_name'],
                $row['index_name'],
                number_format((int) $row['scans']),
                $row['index_size'],
                $row['status'],
            ];
        }

        $io->table($headers, $tableRows);

        $unusedCount = 0;
        foreach ($rows as $row) {
            if ($row['status'] === 'UNUSED') {
                $unusedCount++;
            }
        }

        if ($unusedCount > 0) {
            $io->warning(sprintf('%d index(es) have never been scanned. Consider reviewing.', $unusedCount));
        }
    }

    private function reportBloat(SymfonyStyle $io): void
    {
        $rows = $this->connection->fetchAllAssociative("
            SELECT
                schemaname || '.' || relname AS table_name,
                n_live_tup AS live_rows,
                n_dead_tup AS dead_rows,
                CASE
                    WHEN n_live_tup > 0
                    THEN round(100.0 * n_dead_tup / (n_live_tup + n_dead_tup), 1)
                    ELSE 0
                END AS dead_pct,
                last_vacuum,
                last_autovacuum,
                last_analyze,
                last_autoanalyze
            FROM pg_stat_user_tables
            WHERE schemaname = 'public'
            ORDER BY n_dead_tup DESC
            LIMIT 15
        ");

        $headers = ['Table', 'Live Rows', 'Dead Rows', 'Dead %', 'Last Vacuum', 'Last Analyze'];
        $tableRows = [];
        foreach ($rows as $row) {
            $lastVacuum = $row['last_vacuum'] ?? $row['last_autovacuum'] ?? 'never';
            $lastAnalyze = $row['last_analyze'] ?? $row['last_autoanalyze'] ?? 'never';

            $deadPct = (float) $row['dead_pct'];
            $deadPctStr = $deadPct > 20 ? sprintf('!! %.1f%%', $deadPct) : sprintf('%.1f%%', $deadPct);

            $tableRows[] = [
                $row['table_name'],
                number_format((int) $row['live_rows']),
                number_format((int) $row['dead_rows']),
                $deadPctStr,
                is_string($lastVacuum) ? $lastVacuum : 'never',
                is_string($lastAnalyze) ? $lastAnalyze : 'never',
            ];
        }

        $io->table($headers, $tableRows);
    }

    private function vacuumAnalyze(SymfonyStyle $io): void
    {
        foreach (self::KEY_TABLES as $table) {
            // Check table exists before vacuuming
            $exists = (int) $this->connection->fetchOne(
                "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_name = :table",
                ['table' => $table],
            );

            if ($exists === 0) {
                $io->text(sprintf('  Skipping %s (table not found)', $table));
                continue;
            }

            $start = microtime(true);
            // VACUUM cannot run inside a transaction — use native connection
            $this->connection->getNativeConnection()->exec(sprintf('VACUUM ANALYZE %s', $table));
            $elapsed = (int) round((microtime(true) - $start) * 1000);

            $io->text(sprintf('  VACUUM ANALYZE %s ... %dms', $table, $elapsed));
        }
    }
}
