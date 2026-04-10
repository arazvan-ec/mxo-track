<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\OptimizationStrategyComparisonRepository;
use App\Repository\RoutePerformanceMetricRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Outputs aggregate learning metrics for Claude to consult during brainstorming.
 *
 * Usage: php bin/console app:learning:metrics --period=30d --context=route-optimization
 */
#[AsCommand(
    name: 'app:learning:metrics',
    description: 'Display aggregate learning metrics from route performance and strategy comparisons.',
)]
class LearningMetricsCommand extends Command
{
    public function __construct(
        private readonly RoutePerformanceMetricRepository $performanceRepo,
        private readonly OptimizationStrategyComparisonRepository $comparisonRepo,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('period', 'p', InputOption::VALUE_REQUIRED, 'Period to analyze (e.g., 7d, 30d, 90d)', '30d')
            ->addOption('context', 'c', InputOption::VALUE_REQUIRED, 'Context filter (route-optimization)', 'route-optimization');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $period = $this->parsePeriod((string) $input->getOption('period'));
        $since = new \DateTimeImmutable(sprintf('-%d days', $period));

        $io->title(sprintf('Learning Metrics — Last %d days', $period));

        $this->renderPerformanceMetrics($io, $since);
        $this->renderOptimizerComparison($io, $since);
        $this->renderStrategyComparisons($io, $since);

        return Command::SUCCESS;
    }

    private function renderPerformanceMetrics(SymfonyStyle $io, \DateTimeImmutable $since): void
    {
        $io->section('Route Performance Summary');

        $metrics = $this->performanceRepo->getAggregateMetrics($since);

        if ($metrics['total_routes'] === 0) {
            $io->note('No route performance data for this period.');

            return;
        }

        $io->table(
            ['Metric', 'Value'],
            [
                ['Total routes completed', (string) $metrics['total_routes']],
                ['Avg delivery success rate', $this->formatPercent($metrics['avg_delivery_rate'])],
                ['Avg km saved per route', $this->formatDecimal($metrics['avg_km_saved']) . ' km'],
                ['Avg plan accuracy', $this->formatPercent($metrics['avg_plan_accuracy'])],
            ],
        );
    }

    private function renderOptimizerComparison(SymfonyStyle $io, \DateTimeImmutable $since): void
    {
        $io->section('Performance by Optimizer');

        $byOptimizer = $this->performanceRepo->getMetricsByOptimizer($since);

        if ($byOptimizer === []) {
            $io->note('No optimizer comparison data for this period.');

            return;
        }

        $rows = [];
        foreach ($byOptimizer as $row) {
            $rows[] = [
                $row['optimizer_used'],
                (string) $row['route_count'],
                $this->formatDecimal($row['avg_distance_km']) . ' km',
                $row['avg_duration_min'] . ' min',
                $this->formatPercent($row['avg_success_rate']),
            ];
        }

        $io->table(
            ['Optimizer', 'Routes', 'Avg Distance', 'Avg Duration', 'Avg Success Rate'],
            $rows,
        );
    }

    private function renderStrategyComparisons(SymfonyStyle $io, \DateTimeImmutable $since): void
    {
        $io->section('Strategy A/B Comparisons');

        $comparisons = $this->comparisonRepo->findWithOutcomes($since);

        if ($comparisons === []) {
            $io->note('No A/B strategy comparisons with outcomes for this period.');

            return;
        }

        $rows = [];
        foreach ($comparisons as $comparison) {
            $a = $comparison->getStrategyA();
            $b = $comparison->getStrategyB();
            $rows[] = [
                ($a['strategy'] ?? '?') . ' vs ' . ($b['strategy'] ?? '?'),
                (string) $comparison->getShipmentCount(),
                $comparison->getChosen(),
                $comparison->getChosenReason() ?? '-',
            ];
        }

        $io->table(
            ['Comparison', 'Shipments', 'Chosen', 'Reason'],
            $rows,
        );
    }

    private function parsePeriod(string $period): int
    {
        if (preg_match('/^(\d+)d$/', $period, $matches)) {
            return (int) $matches[1];
        }

        return 30;
    }

    private function formatPercent(?string $value): string
    {
        if ($value === null) {
            return 'N/A';
        }

        return round((float) $value, 1) . '%';
    }

    private function formatDecimal(?string $value): string
    {
        if ($value === null) {
            return 'N/A';
        }

        return (string) round((float) $value, 2);
    }
}
