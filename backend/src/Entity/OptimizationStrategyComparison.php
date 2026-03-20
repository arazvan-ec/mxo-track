<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Route\Model\Route;
use App\Entity\Concerns\PublicIdTrait;
use App\Repository\OptimizationStrategyComparisonRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * A/B comparison of two optimization strategies on the same shipment set.
 *
 * Stores both strategy results and, after execution, the actual outcome
 * of the chosen strategy. Used to learn which strategies work best
 * under which conditions.
 */
#[ORM\Entity(repositoryClass: OptimizationStrategyComparisonRepository::class)]
#[ORM\Table(name: 'optimization_strategy_comparison')]
#[ORM\UniqueConstraint(name: 'uniq_osc_public_id', columns: ['public_id'])]
#[ORM\Index(name: 'idx_osc_created_at', columns: ['created_at'])]
#[ORM\HasLifecycleCallbacks]
class OptimizationStrategyComparison
{
    use PublicIdTrait;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Customer $customer;

    /**
     * JSON: {strategy: string, params: array, result: {distance_km: float, duration_min: int, stops: int, unassigned: int}}
     */
    #[ORM\Column(type: 'json')]
    private array $strategyA;

    /**
     * JSON: same structure as strategyA
     */
    #[ORM\Column(type: 'json')]
    private array $strategyB;

    /** Which strategy was chosen: 'a', 'b', or 'neither' */
    #[ORM\Column(length: 10)]
    private string $chosen;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $chosenReason;

    /** Actual metrics after executing the chosen strategy (populated later) */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $actualOutcome;

    #[ORM\ManyToOne(targetEntity: Route::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Route $resultRoute;

    #[ORM\Column]
    private int $shipmentCount;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $outcomeRecordedAt;

    public function __construct(
        array $strategyA,
        array $strategyB,
        string $chosen,
        int $shipmentCount,
        ?Customer $customer = null,
        ?string $chosenReason = null,
        ?Route $resultRoute = null,
    ) {
        $this->strategyA = $strategyA;
        $this->strategyB = $strategyB;
        $this->chosen = $chosen;
        $this->shipmentCount = $shipmentCount;
        $this->customer = $customer;
        $this->chosenReason = $chosenReason;
        $this->resultRoute = $resultRoute;
        $this->actualOutcome = null;
        $this->outcomeRecordedAt = null;
        $this->createdAt = new DateTimeImmutable();
    }

    public function recordOutcome(array $actualOutcome): void
    {
        $this->actualOutcome = $actualOutcome;
        $this->outcomeRecordedAt = new DateTimeImmutable();
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function getStrategyA(): array
    {
        return $this->strategyA;
    }

    public function getStrategyB(): array
    {
        return $this->strategyB;
    }

    public function getChosen(): string
    {
        return $this->chosen;
    }

    public function getChosenReason(): ?string
    {
        return $this->chosenReason;
    }

    public function getActualOutcome(): ?array
    {
        return $this->actualOutcome;
    }

    public function getResultRoute(): ?Route
    {
        return $this->resultRoute;
    }

    public function getShipmentCount(): int
    {
        return $this->shipmentCount;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getOutcomeRecordedAt(): ?DateTimeImmutable
    {
        return $this->outcomeRecordedAt;
    }
}
