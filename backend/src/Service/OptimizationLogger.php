<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Customer;
use App\Domain\Route\Model\Route;
use App\Entity\RouteOptimizationLog;
use App\Enum\OptimizationOperation;
use App\Enum\OptimizationStepCategory;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Collects optimization process steps during a single operation,
 * then persists them as a RouteOptimizationLog entity.
 *
 * Usage:
 *   $logger->startOperation(OptimizationOperation::BUILD_ROUTES, [...]);
 *   $logger->logStep(OptimizationStepCategory::VEHICLE_MAPPING, 'Mapped 3 vehicles', [...]);
 *   // ... more steps ...
 *   $log = $logger->finishOperation([...resultSummary], $route, $customer);
 */
final class OptimizationLogger
{
    /** @var list<array{timestamp: float, category: string, categoryLabel: string, message: string, data: array, icon: string, color: string}> */
    private array $steps = [];
    private ?float $startTime = null;
    private ?OptimizationOperation $operation = null;
    private array $inputSummary = [];
    private string $optimizerUsed = 'unknown';

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function startOperation(OptimizationOperation $operation, array $inputSummary = []): void
    {
        $this->reset();
        $this->operation = $operation;
        $this->inputSummary = $inputSummary;
        $this->startTime = microtime(true);

        $this->logStep(
            OptimizationStepCategory::OPTIMIZER_SELECTION,
            sprintf('Iniciando operacion: %s', $operation->label()),
            ['operation' => $operation->value],
        );
    }

    public function setOptimizerUsed(string $optimizer): void
    {
        $this->optimizerUsed = $optimizer;
    }

    public function logStep(OptimizationStepCategory $category, string $message, array $data = []): void
    {
        $this->steps[] = [
            'timestamp' => microtime(true),
            'elapsed_ms' => $this->startTime !== null
                ? (int) round((microtime(true) - $this->startTime) * 1000)
                : 0,
            'category' => $category->value,
            'categoryLabel' => $category->label(),
            'message' => $message,
            'data' => $data,
            'icon' => $category->icon(),
            'color' => $category->color(),
        ];
    }

    /**
     * Persists the collected log as a RouteOptimizationLog entity.
     */
    public function finishOperation(
        array $resultSummary,
        ?Route $route = null,
        ?Customer $customer = null,
    ): RouteOptimizationLog {
        $durationMs = $this->startTime !== null
            ? (int) round((microtime(true) - $this->startTime) * 1000)
            : 0;

        $this->logStep(
            OptimizationStepCategory::RESULT_SUMMARY,
            sprintf('Operacion completada en %d ms', $durationMs),
            $resultSummary,
        );

        $log = new RouteOptimizationLog(
            operation: $this->operation ?? OptimizationOperation::TEST_ROUTING,
            optimizerUsed: $this->optimizerUsed,
            inputSummary: $this->inputSummary,
            steps: $this->steps,
            resultSummary: $resultSummary,
            durationMs: $durationMs,
            route: $route,
            customer: $customer,
        );

        $this->em->persist($log);

        return $log;
    }

    /**
     * Returns collected steps without persisting (for test-routing or transient use).
     *
     * @return list<array{timestamp: float, elapsed_ms: int, category: string, categoryLabel: string, message: string, data: array, icon: string, color: string}>
     */
    public function getSteps(): array
    {
        return $this->steps;
    }

    /**
     * Returns collected steps plus summary metadata (for API/template responses).
     */
    public function getLogData(): array
    {
        $durationMs = $this->startTime !== null
            ? (int) round((microtime(true) - $this->startTime) * 1000)
            : 0;

        return [
            'operation' => $this->operation?->value,
            'operationLabel' => $this->operation?->label(),
            'optimizerUsed' => $this->optimizerUsed,
            'durationMs' => $durationMs,
            'steps' => $this->steps,
        ];
    }

    public function isStarted(): bool
    {
        return $this->operation !== null;
    }

    public function reset(): void
    {
        $this->steps = [];
        $this->startTime = null;
        $this->operation = null;
        $this->inputSummary = [];
        $this->optimizerUsed = 'unknown';
    }
}
