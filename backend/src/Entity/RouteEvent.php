<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\RouteEventType;
use App\Repository\RouteEventRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RouteEventRepository::class)]
#[ORM\Table(name: 'route_event')]
#[ORM\Index(name: 'idx_route_event_route_occurred', columns: ['route_id', 'occurred_at'])]
#[ORM\Index(name: 'idx_route_event_type_occurred', columns: ['event_type', 'occurred_at'])]
class RouteEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Route::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Route $route;

    #[ORM\Column(length: 40, enumType: RouteEventType::class)]
    private RouteEventType $eventType;

    #[ORM\Column(length: 20)]
    private string $actorType;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $actorUser = null;

    #[ORM\Column(type: 'json')]
    private array $payload = [];

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $snapshotMetrics = null;

    #[ORM\Column]
    private DateTimeImmutable $occurredAt;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct(
        Route $route,
        RouteEventType $eventType,
        string $actorType,
        ?User $actorUser = null,
        array $payload = [],
        ?array $snapshotMetrics = null,
        ?DateTimeImmutable $occurredAt = null,
    ) {
        $this->route = $route;
        $this->eventType = $eventType;
        $this->actorType = $actorType;
        $this->actorUser = $actorUser;
        $this->payload = $payload;
        $this->snapshotMetrics = $snapshotMetrics;
        $this->occurredAt = $occurredAt ?? new DateTimeImmutable();
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getRoute(): Route { return $this->route; }
    public function getEventType(): RouteEventType { return $this->eventType; }
    public function getActorType(): string { return $this->actorType; }
    public function getActorUser(): ?User { return $this->actorUser; }
    public function getPayload(): array { return $this->payload; }
    public function getSnapshotMetrics(): ?array { return $this->snapshotMetrics; }
    public function getOccurredAt(): DateTimeImmutable { return $this->occurredAt; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
}
