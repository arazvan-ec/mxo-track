<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Model;

use App\Enum\ParcelStatus;
use DateTimeImmutable;
use Symfony\Component\Uid\Ulid;

/**
 * Parcel entity — domain POPO.
 * Persistence handled via external XML mapping (no ORM attributes).
 */
class Parcel
{
    private ?string $id = null;
    private Ulid $publicId;
    private Shipment $shipment;
    private int $sequenceNumber = 1;
    private string $weightKg = '0';
    private string $volumeM3 = '0';
    private ?string $ean = null;
    private ?string $description = null;
    private ParcelStatus $status = ParcelStatus::REGISTERED;
    private DateTimeImmutable $createdAt;
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
        $this->publicId = new Ulid();
        $shipment->addParcel($this);
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

    public function touchUpdatedAt(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
