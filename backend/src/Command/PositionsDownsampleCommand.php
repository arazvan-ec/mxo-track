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

        // Phase 1: Data older than 7 days — keep 1 position per 5-minute window
        $deleted7d = $this->downsampleWindow($io, 7, 5, $batchSize, $dryRun);
        $io->text(sprintf('Phase 1 (>7d, 5-min windows): %s rows %s', number_format($deleted7d), $dryRun ? 'would be deleted' : 'deleted'));

        // Phase 2: Data older than 24h (but <= 7d) — keep 1 position per 1-minute window
        $deleted24h = $this->downsampleWindow($io, 1, 1, $batchSize, $dryRun);
        $io->text(sprintf('Phase 2 (>24h, 1-min windows): %s rows %s', number_format($deleted24h), $dryRun ? 'would be deleted' : 'deleted'));

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
     * Keeps the first position (lowest id) in each (vehicle_id, window) group.
     */
    private function downsampleWindow(SymfonyStyle $io, int $daysOld, int $windowMinutes, int $batchSize, bool $dryRun): int
    {
        $cutoff = (new \DateTimeImmutable())->modify(sprintf('-%d days', $daysOld))->format('Y-m-d H:i:s');

        // Count candidates for deletion
        $countSql = sprintf("
            SELECT COUNT(*) FROM vehicle_positions vp
            WHERE vp.device_time < :cutoff
              AND vp.id NOT IN (
                SELECT MIN(id)
                FROM vehicle_positions
                WHERE device_time < :cutoff2
                GROUP BY vehicle_id, date_trunc('minute', device_time - (EXTRACT(MINUTE FROM device_time)::int %% %d) * INTERVAL '1 minute')
              )
        ", $windowMinutes);

        $candidateCount = (int) $this->connection->fetchOne($countSql, [
            'cutoff' => $cutoff,
            'cutoff2' => $cutoff,
        ]);

        $io->text(sprintf('  Candidates for deletion (>%dd, %d-min window): %s', $daysOld, $windowMinutes, number_format($candidateCount)));

        if ($dryRun || $candidateCount === 0) {
            return $candidateCount;
        }

        // Delete in batches
        $totalDeleted = 0;
        $deleteSql = sprintf("
            DELETE FROM vehicle_positions
            WHERE id IN (
                SELECT vp.id FROM vehicle_positions vp
                WHERE vp.device_time < :cutoff
                  AND vp.id NOT IN (
                    SELECT MIN(id)
                    FROM vehicle_positions
                    WHERE device_time < :cutoff2
                    GROUP BY vehicle_id, date_trunc('minute', device_time - (EXTRACT(MINUTE FROM device_time)::int %%%% %d) * INTERVAL '1 minute')
                  )
                LIMIT :batch_limit
            )
        ", $windowMinutes);

        do {
            $deleted = (int) $this->connection->executeStatement($deleteSql, [
                'cutoff' => $cutoff,
                'cutoff2' => $cutoff,
                'batch_limit' => $batchSize,
            ]);
            $totalDeleted += $deleted;

            if ($deleted > 0) {
                $io->text(sprintf('  Batch deleted: %d (total: %s)', $deleted, number_format($totalDeleted)));
            }
        } while ($deleted >= $batchSize);

        return $totalDeleted;
    }

    private function countPositions(): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM vehicle_positions');
    }
}
