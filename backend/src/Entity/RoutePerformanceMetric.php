<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Route\Model\Route;
use App\Entity\Concerns\PublicIdTrait;
use App\Repository\RoutePerformanceMetricRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Immutable post-route analysis record with queryable KPIs.
 *
 * Created once when a route completes. Unlike RouteSnapshot (mutable, operational),
 * this entity captures final performance data for cross-route comparison and learning.
 */
#[ORM\Entity(repositoryClass: RoutePerformanceMetricRepository::class)]
#[ORM\Table(name: 'route_performance_metric')]
#[ORM\UniqueConstraint(name: 'uniq_rpm_public_id', columns: ['public_id'])]
#[ORM\UniqueConstraint(name: 'uniq_rpm_route', columns: ['route_id'])]
#[ORM\Index(name: 'idx_rpm_customer_created', columns: ['customer_id', 'created_at'])]
#[ORM\Index(name: 'idx_rpm_optimizer', columns: ['optimizer_used'])]
#[ORM\HasLifecycleCallbacks]
class RoutePerformanceMetric implements CustomerScopedEntityInterface
{
    use PublicIdTrait;

    #[ORM\OneToOne(targetEntity: Route::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Route $route;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Customer $customer;

    // ── Planning metrics ──

    #[ORM\Column(length: 30)]
    private string $optimizerUsed;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $plannedDistanceKm;

    #[ORM\Column(nullable: true)]
    private ?int $plannedDurationMinutes;

    #[ORM\Column]
    private int $totalStops;

    // ── Execution metrics ──

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $actualDistanceKm;

    #[ORM\Column(nullable: true)]
    private ?int $actualDurationMinutes;

    #[ORM\Column]
    private int $deliveredCount;

    #[ORM\Column]
    private int $exceptionCount;

    #[ORM\Column]
    private int $skippedCount;

    // ── Computed KPIs ──

    #[ORM\Column(type: 'decimal', precision: 5, scale: 1, nullable: true)]
    private ?string $deliverySuccessRate;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $kmSaved;

    #[ORM\Column(nullable: true)]
    private ?int $timeSavedMinutes;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 1, nullable: true)]
    private ?string $planAccuracyPercent;

    // ── Context ──

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $tags;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct(
        Route $route,
        Customer $customer,
        string $optimizerUsed,
        int $totalStops,
        int $deliveredCount,
        int $exceptionCount,
        int $skippedCount,
        ?string $plannedDistanceKm = null,
        ?int $plannedDurationMinutes = null,
        ?string $actualDistanceKm = null,
        ?int $actualDurationMinutes = null,
        ?string $deliverySuccessRate = null,
        ?string $kmSaved = null,
        ?int $timeSavedMinutes = null,
        ?string $planAccuracyPercent = null,
        ?array $tags = null,
    ) {
        $this->route = $route;
        $this->customer = $customer;
        $this->optimizerUsed = $optimizerUsed;
        $this->totalStops = $totalStops;
        $this->deliveredCount = $deliveredCount;
        $this->exceptionCount = $exceptionCount;
        $this->skippedCount = $skippedCount;
        $this->plannedDistanceKm = $plannedDistanceKm;
        $this->plannedDurationMinutes = $plannedDurationMinutes;
        $this->actualDistanceKm = $actualDistanceKm;
        $this->actualDurationMinutes = $actualDurationMinutes;
        $this->deliverySuccessRate = $deliverySuccessRate;
        $this->kmSaved = $kmSaved;
        $this->timeSavedMinutes = $timeSavedMinutes;
        $this->planAccuracyPercent = $planAccuracyPercent;
        $this->tags = $tags;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getRoute(): Route
    {
        return $this->route;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function getOptimizerUsed(): string
    {
        return $this->optimizerUsed;
    }

    public function getPlannedDistanceKm(): ?string
    {
        return $this->plannedDistanceKm;
    }

    public function getPlannedDurationMinutes(): ?int
    {
        return $this->plannedDurationMinutes;
    }

    public function getTotalStops(): int
    {
        return $this->totalStops;
    }

    public function getActualDistanceKm(): ?string
    {
        return $this->actualDistanceKm;
    }

    public function getActualDurationMinutes(): ?int
    {
        return $this->actualDurationMinutes;
    }

    public function getDeliveredCount(): int
    {
        return $this->deliveredCount;
    }

    public function getExceptionCount(): int
    {
        return $this->exceptionCount;
    }

    public function getSkippedCount(): int
    {
        return $this->skippedCount;
    }

    public function getDeliverySuccessRate(): ?string
    {
        return $this->deliverySuccessRate;
    }

    public function getKmSaved(): ?string
    {
        return $this->kmSaved;
    }

    public function getTimeSavedMinutes(): ?int
    {
        return $this->timeSavedMinutes;
    }

    public function getPlanAccuracyPercent(): ?string
    {
        return $this->planAccuracyPercent;
    }

    public function getTags(): ?array
    {
        return $this->tags;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
