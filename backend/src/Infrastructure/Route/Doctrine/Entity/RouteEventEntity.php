<?php

declare(strict_types=1);

namespace App\Infrastructure\Route\Doctrine\Entity;

use App\Domain\Route\Model\RouteEvent;
use App\Domain\Route\ValueObject\RouteId;
use App\Entity\User;
use App\Enum\RouteEventType;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'route_event')]
#[ORM\Index(name: 'idx_route_event_route_occurred', columns: ['route_id', 'occurred_at'])]
#[ORM\Index(name: 'idx_route_event_type_occurred', columns: ['event_type', 'occurred_at'])]
class RouteEventEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: RouteEntity::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private RouteEntity $route;

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

    private function __construct()
    {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRoute(): RouteEntity
    {
        return $this->route;
    }

    // ── Domain ↔ Doctrine Mapping ──

    public function toDomain(): RouteEvent
    {
        return RouteEvent::reconstitute(
            routeId: new RouteId((string) $this->route->getPublicId()),
            eventType: $this->eventType,
            actorType: $this->actorType,
            actorUserId: $this->actorUser?->getId() !== null ? (int) $this->actorUser->getId() : null,
            payload: $this->payload,
            snapshotMetrics: $this->snapshotMetrics,
            occurredAt: $this->occurredAt,
            createdAt: $this->createdAt,
        );
    }

    public static function fromDomain(RouteEvent $event, RouteEntity $routeEntity, ?User $actorUser = null): self
    {
        $entity = new self();
        $entity->route = $routeEntity;
        $entity->eventType = $event->eventType();
        $entity->actorType = $event->actorType();
        $entity->actorUser = $actorUser;
        $entity->payload = $event->payload();
        $entity->snapshotMetrics = $event->snapshotMetrics();
        $entity->occurredAt = $event->occurredAt();
        $entity->createdAt = $event->createdAt();

        return $entity;
    }
}
