<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Route\Model\Route;
use App\Entity\Concerns\PublicIdTrait;
use App\Enum\OptimizationOperation;
use App\Repository\RouteOptimizationLogRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RouteOptimizationLogRepository::class)]
#[ORM\Table(name: 'route_optimization_log')]
#[ORM\UniqueConstraint(name: 'uniq_route_opt_log_public_id', columns: ['public_id'])]
#[ORM\Index(name: 'idx_route_opt_log_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'idx_route_opt_log_operation', columns: ['operation'])]
#[ORM\HasLifecycleCallbacks]
class RouteOptimizationLog
{
    use PublicIdTrait;

    #[ORM\ManyToOne(targetEntity: Route::class)]
    #[ORM\JoinColumn(name: 'route_id', nullable: true, onDelete: 'SET NULL')]
    private ?Route $route;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(name: 'customer_id', nullable: true, onDelete: 'SET NULL')]
    private ?Customer $customer;

    #[ORM\Column(length: 30, enumType: OptimizationOperation::class)]
    private OptimizationOperation $operation;

    #[ORM\Column(length: 30)]
    private string $optimizerUsed;

    #[ORM\Column(type: 'json')]
    private array $inputSummary;

    #[ORM\Column(type: 'json')]
    private array $steps;

    #[ORM\Column(type: 'json')]
    private array $resultSummary;

    #[ORM\Column]
    private int $durationMs;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct(
        OptimizationOperation $operation,
        string $optimizerUsed,
        array $inputSummary,
        array $steps,
        array $resultSummary,
        int $durationMs,
        ?Route $route = null,
        ?Customer $customer = null,
    ) {
        $this->operation = $operation;
        $this->optimizerUsed = $optimizerUsed;
        $this->inputSummary = $inputSummary;
        $this->steps = $steps;
        $this->resultSummary = $resultSummary;
        $this->durationMs = $durationMs;
        $this->route = $route;
        $this->customer = $customer;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getRoute(): ?Route
    {
        return $this->route;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function getOperation(): OptimizationOperation
    {
        return $this->operation;
    }

    public function getOptimizerUsed(): string
    {
        return $this->optimizerUsed;
    }

    public function getInputSummary(): array
    {
        return $this->inputSummary;
    }

    public function getSteps(): array
    {
        return $this->steps;
    }

    public function getResultSummary(): array
    {
        return $this->resultSummary;
    }

    public function getDurationMs(): int
    {
        return $this->durationMs;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
