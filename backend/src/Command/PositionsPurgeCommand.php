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
    name: 'app:positions:purge',
    description: 'Purge vehicle_positions older than a configurable number of days (default 90).',
)]
class PositionsPurgeCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'Delete positions older than this many days', '90')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be deleted without actually deleting')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Rows to delete per batch', '10000');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = max(1, (int) $input->getOption('days'));
        $dryRun = (bool) $input->getOption('dry-run');
        $batchSize = max(100, (int) $input->getOption('batch-size'));

        $cutoff = (new \DateTimeImmutable())->modify(sprintf('-%d days', $days));
        $cutoffStr = $cutoff->format('Y-m-d H:i:s');

        $io->title('Position Purge');
        $io->text(sprintf('Retention policy: %d days (cutoff: %s)', $days, $cutoff->format('Y-m-d')));

        if ($dryRun) {
            $io->note('Dry-run mode: no data will be deleted.');
        }

        // Count total positions
        $totalCount = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM vehicle_positions');
        $io->text(sprintf('Total positions: %s', number_format($totalCount)));

        // Count candidates for deletion
        $candidateCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM vehicle_positions WHERE device_time < :cutoff',
            ['cutoff' => $cutoffStr],
        );
        $io->text(sprintf('Positions older than %d days: %s', $days, number_format($candidateCount)));

        if ($candidateCount === 0) {
            $io->success('No positions to purge.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            $io->success(sprintf('%s positions would be purged.', number_format($candidateCount)));
            return self::SUCCESS;
        }

        // Delete in batches
        $totalDeleted = 0;

        do {
            $deleted = (int) $this->connection->executeStatement(
                'DELETE FROM vehicle_positions WHERE id IN (
                    SELECT id FROM vehicle_positions WHERE device_time < :cutoff LIMIT :batch_limit
                )',
                ['cutoff' => $cutoffStr, 'batch_limit' => $batchSize],
            );
            $totalDeleted += $deleted;

            if ($deleted > 0) {
                $pct = $candidateCount > 0 ? round(100 * $totalDeleted / $candidateCount, 1) : 100;
                $io->text(sprintf(
                    '  Batch: %d deleted (total: %s / %s = %.1f%%)',
                    $deleted,
                    number_format($totalDeleted),
                    number_format($candidateCount),
                    $pct,
                ));
            }
        } while ($deleted >= $batchSize);

        $remainingCount = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM vehicle_positions');

        $io->newLine();
        $io->text(sprintf('Remaining positions: %s', number_format($remainingCount)));
        $io->success(sprintf('Purge complete. %s positions deleted.', number_format($totalDeleted)));

        return self::SUCCESS;
    }
}
