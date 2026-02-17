<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ShipmentEventType;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'shipment_event')]
class ShipmentEvent
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Shipment::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Shipment $shipment;

    #[ORM\Column(length: 40, enumType: ShipmentEventType::class)]
    private ShipmentEventType $eventType;

    #[ORM\Column(type: 'json')]
    private array $payload = [];

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct(Shipment $shipment, ShipmentEventType $eventType, array $payload = [])
    {
        $this->id = Uuid::v7();
        $this->shipment = $shipment;
        $this->eventType = $eventType;
        $this->payload = $payload;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getEventType(): ShipmentEventType { return $this->eventType; }
    public function getPayload(): array { return $this->payload; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
}
