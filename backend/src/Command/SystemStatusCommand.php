<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\AdminMetricsService;
use App\Service\SystemHealthService;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:system:status',
    description: 'Run all health checks and print a formatted system status report.',
)]
class SystemStatusCommand extends Command
{
    public function __construct(
        private readonly SystemHealthService $healthService,
        private readonly AdminMetricsService $metricsService,
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('quiet-check', null, InputOption::VALUE_NONE, 'Suppress output, exit 1 if any check fails (for monitoring)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $quietCheck = (bool) $input->getOption('quiet-check');
        $io = new SymfonyStyle($input, $output);

        // Run all health checks
        $live = $this->healthService->checkLive();
        $metrics = $this->metricsService->collect();

        $allOk = true;

        // Determine statuses
        $checks = [
            'PostgreSQL' => [
                'status' => $live['database']['ok'],
                'detail' => sprintf('%dms', $live['database']['latency_ms']),
            ],
            'Redis' => [
                'status' => $live['redis']['ok'],
                'detail' => sprintf('%dms', $live['redis']['latency_ms']),
            ],
            'Traccar API' => [
                'status' => $live['traccar']['ok'],
                'detail' => sprintf('%dms', $live['traccar']['latency_ms']),
            ],
            'Mercure Hub' => [
                'status' => $live['mercure']['ok'],
                'detail' => sprintf('%dms', $live['mercure']['latency_ms']),
            ],
        ];

        foreach ($checks as $check) {
            if (!$check['status']) {
                $allOk = false;
            }
        }

        if ($quietCheck) {
            return $allOk ? self::SUCCESS : self::FAILURE;
        }

        $io->title('System Status Report');

        // Service health table
        $io->section('Service Health');
        $healthRows = [];
        foreach ($checks as $name => $check) {
            $healthRows[] = [
                $name,
                $check['status'] ? 'OK' : 'FAIL',
                $check['detail'],
            ];
        }
        $io->table(['Service', 'Status', 'Latency'], $healthRows);

        // Infrastructure
        $io->section('Infrastructure');
        $infraRows = [
            ['Position rows (approx)', number_format($live['positions']['row_count'])],
            ['Position table warning', $live['positions']['warning'] ? 'YES (>1M rows)' : 'No'],
            ['Database size', sprintf('%.2f MB', $live['disk']['db_size_mb'])],
            [
                'Last ingestion',
                $live['last_ingestion']['timestamp'] !== null
                    ? sprintf('%s (%ds ago)', $live['last_ingestion']['timestamp'], $live['last_ingestion']['seconds_ago'])
                    : 'No data',
            ],
        ];
        $io->table(['Metric', 'Value'], $infraRows);

        // Business metrics
        $io->section('Business Metrics');
        $businessRows = [
            ['Active routes', (string) $metrics['active_routes']],
            ['Pending stops', (string) $metrics['pending_stops']],
            ['CSV imports today', (string) $metrics['import_runs_today']],
            ['Positions ingested (last hour)', (string) $metrics['positions_ingested_last_hour']],
        ];

        // Add online vehicles count
        $onlineVehicles = $this->countOnlineVehicles();
        $businessRows[] = ['Online vehicles (last 10min)', (string) $onlineVehicles];

        $io->table(['Metric', 'Value'], $businessRows);

        // Overall status
        if ($allOk) {
            $io->success('All health checks passed.');
        } else {
            $io->error('One or more health checks failed.');
        }

        return $allOk ? self::SUCCESS : self::FAILURE;
    }

    private function countOnlineVehicles(): int
    {
        try {
            $tenMinAgo = (new \DateTimeImmutable())->modify('-10 minutes')->format('Y-m-d H:i:s');

            return (int) $this->connection->fetchOne(
                'SELECT COUNT(DISTINCT vehicle_id) FROM vehicle_last_position WHERE server_time >= :since',
                ['since' => $tenMinAgo],
            );
        } catch (\Throwable) {
            return 0;
        }
    }
}
