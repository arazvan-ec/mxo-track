<?php

declare(strict_types=1);

namespace App\Domain\Route\Model;

use App\Domain\Route\ValueObject\RouteId;
use App\Enum\RouteEventType;

final class RouteEvent
{
    private \DateTimeImmutable $createdAt;

    public function __construct(
        private readonly RouteId $routeId,
        private readonly RouteEventType $eventType,
        private readonly string $actorType,
        private readonly ?int $actorUserId = null,
        private readonly array $payload = [],
        private readonly ?array $snapshotMetrics = null,
        private readonly ?\DateTimeImmutable $occurredAt = null,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function reconstitute(
        RouteId $routeId,
        RouteEventType $eventType,
        string $actorType,
        ?int $actorUserId,
        array $payload,
        ?array $snapshotMetrics,
        \DateTimeImmutable $occurredAt,
        \DateTimeImmutable $createdAt,
    ): self {
        $event = new self($routeId, $eventType, $actorType, $actorUserId, $payload, $snapshotMetrics, $occurredAt);
        $event->createdAt = $createdAt;

        return $event;
    }

    public function routeId(): RouteId { return $this->routeId; }
    public function eventType(): RouteEventType { return $this->eventType; }
    public function actorType(): string { return $this->actorType; }
    public function actorUserId(): ?int { return $this->actorUserId; }
    public function payload(): array { return $this->payload; }
    public function snapshotMetrics(): ?array { return $this->snapshotMetrics; }
    public function occurredAt(): \DateTimeImmutable { return $this->occurredAt ?? $this->createdAt; }
    public function createdAt(): \DateTimeImmutable { return $this->createdAt; }
}
