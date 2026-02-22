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
    name: 'app:positions:downsample',
    description: 'Downsample vehicle_positions: keep 1/min for >24h, 1/5min for >7d.',
)]
class PositionsDownsampleCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be deleted without actually deleting')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Rows to delete per batch', '5000');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $batchSize = max(100, (int) $input->getOption('batch-size'));

        $io->title('Position Downsample');

        if ($dryRun) {
            $io->note('Dry-run mode: no data will be deleted.');
        }

        $totalBefore = $this->countPositions();
        $io->text(sprintf('Total positions before: %s', number_format($totalBefore)));

        // Phase 1: Data older than 7 days -- keep 1 position per 5-minute window
        $deleted7d = $this->downsampleWindow($io, 7, '5 minutes', $batchSize, $dryRun);
        $io->text(sprintf(
            'Phase 1 (>7d, 5-min windows): %s rows %s',
            number_format($deleted7d),
            $dryRun ? 'would be deleted' : 'deleted',
        ));

        // Phase 2: Data older than 24h (but <=7d) -- keep 1 position per 1-minute window
        $deleted24h = $this->downsampleWindow($io, 1, '1 minute', $batchSize, $dryRun);
        $io->text(sprintf(
            'Phase 2 (>24h, 1-min windows): %s rows %s',
            number_format($deleted24h),
            $dryRun ? 'would be deleted' : 'deleted',
        ));

        $totalAfter = $dryRun ? $totalBefore : $this->countPositions();
        $totalDeleted = $deleted7d + $deleted24h;

        $io->newLine();
        $io->text(sprintf('Total positions after: %s', number_format($totalAfter)));
        $io->success(sprintf(
            'Downsample complete. %s rows %s.',
            number_format($totalDeleted),
            $dryRun ? 'would be removed' : 'removed',
        ));

        return self::SUCCESS;
    }

    /**
     * Remove duplicate positions within time windows for data older than $daysOld days.
     * Keeps the first position (lowest id) in each (vehicle_id, time_bucket) group.
     *
     * Uses PostgreSQL date_bin() (PG 14+) to bucket timestamps into fixed intervals.
     * Fallback: date_trunc + integer arithmetic for standard interval alignment.
     */
    private function downsampleWindow(
        SymfonyStyle $io,
        int $daysOld,
        string $interval,
        int $batchSize,
        bool $dryRun,
    ): int {
        $cutoff = (new \DateTimeImmutable())
            ->modify(sprintf('-%d days', $daysOld))
            ->format('Y-m-d H:i:s');

        // Build the time-bucketing expression using to_timestamp + floor/extract
        // This groups device_time into $interval-sized buckets by truncating to epoch seconds
        $intervalSeconds = $this->intervalToSeconds($interval);
        $bucketExpr = sprintf(
            "to_timestamp(floor(extract(epoch from device_time) / %d) * %d)",
            $intervalSeconds,
            $intervalSeconds,
        );

        // Count candidates: positions that are NOT the MIN(id) in their bucket
        $countSql = "
            SELECT COUNT(*) FROM vehicle_positions
            WHERE device_time < :cutoff
              AND id NOT IN (
                SELECT MIN(id)
                FROM vehicle_positions
                WHERE device_time < :cutoff
                GROUP BY vehicle_id, {$bucketExpr}
              )
        ";

        $candidateCount = (int) $this->connection->fetchOne($countSql, ['cutoff' => $cutoff]);

        $io->text(sprintf(
            '  Candidates for deletion (>%dd, %s window): %s',
            $daysOld,
            $interval,
            number_format($candidateCount),
        ));

        if ($dryRun || $candidateCount === 0) {
            return $candidateCount;
        }

        // Delete in batches
        $totalDeleted = 0;
        $deleteSql = "
            DELETE FROM vehicle_positions
            WHERE id IN (
                SELECT vp.id FROM vehicle_positions vp
                WHERE vp.device_time < :cutoff
                  AND vp.id NOT IN (
                    SELECT MIN(id)
                    FROM vehicle_positions
                    WHERE device_time < :cutoff
                    GROUP BY vehicle_id, {$bucketExpr}
                  )
                LIMIT {$batchSize}
            )
        ";

        do {
            $deleted = (int) $this->connection->executeStatement($deleteSql, ['cutoff' => $cutoff]);
            $totalDeleted += $deleted;

            if ($deleted > 0) {
                $io->text(sprintf('  Batch deleted: %d (total: %s)', $deleted, number_format($totalDeleted)));
            }
        } while ($deleted >= $batchSize);

        return $totalDeleted;
    }

    private function intervalToSeconds(string $interval): int
    {
        return match ($interval) {
            '1 minute' => 60,
            '5 minutes' => 300,
            '10 minutes' => 600,
            '15 minutes' => 900,
            '30 minutes' => 1800,
            '1 hour' => 3600,
            default => 60,
        };
    }

    private function countPositions(): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM vehicle_positions');
    }
}
