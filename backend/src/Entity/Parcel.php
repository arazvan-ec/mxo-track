<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\PublicIdTrait;
use App\Enum\ParcelStatus;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'parcel')]
#[ORM\UniqueConstraint(name: 'uniq_parcel_public_id', columns: ['public_id'])]
#[ORM\Index(name: 'idx_parcel_shipment', columns: ['shipment_id'])]
#[ORM\Index(name: 'idx_parcel_ean', columns: ['ean'])]
#[ORM\HasLifecycleCallbacks]
class Parcel
{
    use PublicIdTrait;

    #[ORM\ManyToOne(targetEntity: Shipment::class, inversedBy: 'parcels')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Shipment $shipment;

    #[ORM\Column]
    private int $sequenceNumber = 1;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\Positive(message: 'El peso es obligatorio y debe ser positivo.')]
    private string $weightKg = '0';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 4)]
    #[Assert\Positive(message: 'El volumen es obligatorio y debe ser positivo.')]
    private string $volumeM3 = '0';

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $ean = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 20, enumType: ParcelStatus::class)]
    private ParcelStatus $status = ParcelStatus::REGISTERED;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    public function __construct(Shipment $shipment, int $sequenceNumber, float $weightKg, float $volumeM3)
    {
        $this->shipment = $shipment;
        $this->sequenceNumber = $sequenceNumber;
        $this->weightKg = (string) $weightKg;
        $this->volumeM3 = (string) $volumeM3;
        $now = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $shipment->addParcel($this);
    }

    public function getShipment(): Shipment { return $this->shipment; }

    public function getSequenceNumber(): int { return $this->sequenceNumber; }
    public function setSequenceNumber(int $seq): void { $this->sequenceNumber = $seq; }

    public function getWeightKg(): float { return (float) $this->weightKg; }
    public function setWeightKg(float $weightKg): void { $this->weightKg = (string) $weightKg; }

    public function getVolumeM3(): float { return (float) $this->volumeM3; }
    public function setVolumeM3(float $volumeM3): void { $this->volumeM3 = (string) $volumeM3; }

    public function getEan(): ?string { return $this->ean; }
    public function setEan(?string $ean): void { $this->ean = $ean; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): void { $this->description = $description; }

    public function getStatus(): ParcelStatus { return $this->status; }

    public function transition(ParcelStatus $newStatus): void
    {
        $this->status = $newStatus;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getLabel(): string
    {
        return sprintf('%d/%d', $this->sequenceNumber, $this->shipment->getTotalParcels());
    }

    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): DateTimeImmutable { return $this->updatedAt; }

    #[ORM\PreUpdate]
    public function touchUpdatedAt(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
