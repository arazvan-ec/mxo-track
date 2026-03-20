<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Model;

use App\Enum\ShipmentEventType;
use DateTimeImmutable;
use Symfony\Component\Uid\Ulid;

/**
 * ShipmentEvent entity — domain POPO.
 * Persistence handled via external XML mapping (no ORM attributes).
 * Append-only event log for shipment lifecycle tracking.
 */
class ShipmentEvent
{
    private ?string $id = null;
    private Ulid $publicId;
    private Shipment $shipment;
    private ShipmentEventType $eventType;
    private array $payload = [];
    private DateTimeImmutable $createdAt;

    public function __construct(Shipment $shipment, ShipmentEventType $eventType, array $payload = [])
    {
        $this->shipment = $shipment;
        $this->eventType = $eventType;
        $this->payload = $payload;
        $this->createdAt = new DateTimeImmutable();
        $this->publicId = new Ulid();
    }

    // ── Identity ──

    public function getId(): ?string { return $this->id; }
    public function getPublicId(): Ulid { return $this->publicId; }
    public function getPublicIdString(): string { return (string) $this->publicId; }

    public function initializePublicId(): void
    {
        $this->publicId ??= new Ulid();
    }

    // ── Accessors ──

    public function getShipment(): Shipment { return $this->shipment; }
    public function getEventType(): ShipmentEventType { return $this->eventType; }
    public function getPayload(): array { return $this->payload; }
    public function setPayload(array $payload): void { $this->payload = $payload; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
}
