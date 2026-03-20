<?php

declare(strict_types=1);

namespace App\Domain\Route\Model;

use App\Entity\User;
use App\Enum\RouteEventType;
use DateTimeImmutable;

/**
 * RouteEvent entity — domain POPO (immutable after construction).
 * Persistence handled via external XML mapping (no ORM attributes).
 */
class RouteEvent
{
    private ?int $id = null;
    private Route $route;
    private RouteEventType $eventType;
    private string $actorType;
    private ?User $actorUser;
    private array $payload;
    private ?array $snapshotMetrics;
    private DateTimeImmutable $occurredAt;
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
