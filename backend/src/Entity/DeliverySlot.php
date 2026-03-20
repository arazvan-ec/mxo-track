<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Shipment\Model\Shipment;
use App\Entity\Concerns\PublicIdTrait;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'delivery_slot')]
#[ORM\UniqueConstraint(name: 'uniq_delivery_slot_public_id', columns: ['public_id'])]
#[ORM\Index(name: 'idx_delivery_slot_shipment_status', columns: ['shipment_id', 'status'])]
#[ORM\HasLifecycleCallbacks]
class DeliverySlot
{
    use PublicIdTrait;

    public const STATUS_PROPOSED = 'proposed';
    public const STATUS_SELECTED = 'selected';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_EXPIRED = 'expired';

    #[ORM\ManyToOne(targetEntity: Shipment::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Shipment $shipment;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private DateTimeImmutable $slotDate;

    #[ORM\Column(type: Types::TIME_IMMUTABLE)]
    private DateTimeImmutable $slotStart;

    #[ORM\Column(type: Types::TIME_IMMUTABLE)]
    private DateTimeImmutable $slotEnd;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PROPOSED;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $recipientPhone = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $selectedAt = null;

    public function __construct(
        Shipment $shipment,
        DateTimeImmutable $slotDate,
        DateTimeImmutable $slotStart,
        DateTimeImmutable $slotEnd,
    ) {
        $this->shipment = $shipment;
        $this->slotDate = $slotDate;
        $this->slotStart = $slotStart;
        $this->slotEnd = $slotEnd;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getShipment(): Shipment { return $this->shipment; }
    public function getSlotDate(): DateTimeImmutable { return $this->slotDate; }
    public function getSlotStart(): DateTimeImmutable { return $this->slotStart; }
    public function getSlotEnd(): DateTimeImmutable { return $this->slotEnd; }
    public function getStatus(): string { return $this->status; }
    public function getRecipientPhone(): ?string { return $this->recipientPhone; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
    public function getSelectedAt(): ?DateTimeImmutable { return $this->selectedAt; }

    public function select(string $recipientPhone): void
    {
        $this->status = self::STATUS_SELECTED;
        $this->recipientPhone = $recipientPhone;
        $this->selectedAt = new DateTimeImmutable();
    }

    public function confirm(): void
    {
        $this->status = self::STATUS_CONFIRMED;
    }

    public function expire(): void
    {
        $this->status = self::STATUS_EXPIRED;
    }

    public function getTimeRange(): string
    {
        return sprintf('%s-%s', $this->slotStart->format('H:i'), $this->slotEnd->format('H:i'));
    }
}
