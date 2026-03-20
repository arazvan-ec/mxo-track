<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Model;

use App\Entity\Customer;
use App\Entity\CustomerScopedEntityInterface;
use App\Entity\SoftDeletableInterface;
use App\Enum\ServiceType;
use App\Enum\ShipmentPriority;
use App\Enum\VehicleSkill;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Uid\Ulid;

/**
 * Shipment entity — domain POPO.
 * Persistence handled via external XML mapping (no ORM attributes).
 */
class Shipment implements CustomerScopedEntityInterface, SoftDeletableInterface
{
    private ?string $id = null;
    private Ulid $publicId;
    private string $reference;
    private Customer $customer;
    private ?string $recipientName = null;
    private ?string $recipientPhone = null;
    private ?string $address = null;
    private ?float $latitude = null;
    private ?float $longitude = null;
    private ?string $notes = null;
    private ?string $description = null;
    private ServiceType $serviceType = ServiceType::DELIVERY;
    private ?string $totalWeightKg = null;
    private ?string $totalVolumeM3 = null;
    private int $totalParcels = 1;
    private ?DateTimeImmutable $estimatedDeliveryDate = null;
    private ?DateTimeImmutable $preferredWindowStart = null;
    private ?DateTimeImmutable $preferredWindowEnd = null;
    private ?int $serviceTimeSeconds = null;
    /** @var Collection<int, Parcel> */
    private Collection $parcels;
    private ShipmentPriority $priority = ShipmentPriority::NORMAL;
    private ?string $trackingToken = null;
    private ?array $requiredSkills = [];
    private DateTimeImmutable $createdAt;
    private ?DateTimeImmutable $deletedAt = null;

    public function __construct(string $reference, Customer $customer)
    {
        $this->reference = $reference;
        $this->customer = $customer;
        $this->createdAt = new DateTimeImmutable();
        $this->trackingToken = self::generateTrackingToken();
        $this->parcels = new ArrayCollection();
        $this->publicId = new Ulid();
    }

    public static function generateTrackingToken(): string
    {
        $bytes = random_bytes(6);
        $hex = strtoupper(bin2hex($bytes));

        return sprintf('TRK-%s-%s', substr($hex, 0, 4), substr($hex, 4, 4));
    }

    // ── Identity ──

    public function getId(): ?string { return $this->id; }
    public function getPublicId(): Ulid { return $this->publicId; }
    public function getPublicIdString(): string { return (string) $this->publicId; }

    public function initializePublicId(): void
    {
        $this->publicId ??= new Ulid();
    }

    // ── Core Accessors ──

    public function getReference(): string { return $this->reference; }
    public function setReference(string $reference): void { $this->reference = $reference; }
    public function getCustomer(): Customer { return $this->customer; }
    public function setCustomer(Customer $customer): void { $this->customer = $customer; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }

    public function getRecipientName(): ?string { return $this->recipientName; }
    public function setRecipientName(?string $recipientName): void { $this->recipientName = $recipientName; }
    public function getRecipientPhone(): ?string { return $this->recipientPhone; }
    public function setRecipientPhone(?string $recipientPhone): void { $this->recipientPhone = $recipientPhone; }
    public function getAddress(): ?string { return $this->address; }
    public function setAddress(?string $address): void { $this->address = $address; }
    public function getLatitude(): ?float { return $this->latitude; }
    public function setLatitude(?float $latitude): void { $this->latitude = $latitude; }
    public function getLongitude(): ?float { return $this->longitude; }
    public function setLongitude(?float $longitude): void { $this->longitude = $longitude; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): void { $this->notes = $notes; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): void { $this->description = $description; }

    public function getServiceTimeSeconds(): ?int { return $this->serviceTimeSeconds; }
    public function setServiceTimeSeconds(?int $serviceTimeSeconds): void { $this->serviceTimeSeconds = $serviceTimeSeconds; }

    public function getTrackingToken(): ?string { return $this->trackingToken; }
    public function setTrackingToken(?string $trackingToken): void { $this->trackingToken = $trackingToken; }

    public function getServiceType(): ServiceType { return $this->serviceType; }
    public function setServiceType(ServiceType $serviceType): void { $this->serviceType = $serviceType; }

    public function getTotalWeightKg(): ?float { return $this->totalWeightKg !== null ? (float) $this->totalWeightKg : null; }
    public function setTotalWeightKg(?float $totalWeightKg): void { $this->totalWeightKg = $totalWeightKg !== null ? (string) $totalWeightKg : null; }

    public function getTotalVolumeM3(): ?float { return $this->totalVolumeM3 !== null ? (float) $this->totalVolumeM3 : null; }
    public function setTotalVolumeM3(?float $totalVolumeM3): void { $this->totalVolumeM3 = $totalVolumeM3 !== null ? (string) $totalVolumeM3 : null; }

    public function getTotalParcels(): int { return $this->totalParcels; }
    public function setTotalParcels(int $totalParcels): void { $this->totalParcels = $totalParcels; }

    public function getEstimatedDeliveryDate(): ?DateTimeImmutable { return $this->estimatedDeliveryDate; }
    public function setEstimatedDeliveryDate(?DateTimeImmutable $date): void { $this->estimatedDeliveryDate = $date; }

    public function getPreferredWindowStart(): ?DateTimeImmutable { return $this->preferredWindowStart; }
    public function setPreferredWindowStart(?DateTimeImmutable $time): void { $this->preferredWindowStart = $time; }

    public function getPreferredWindowEnd(): ?DateTimeImmutable { return $this->preferredWindowEnd; }
    public function setPreferredWindowEnd(?DateTimeImmutable $time): void { $this->preferredWindowEnd = $time; }

    /** @return Collection<int, Parcel> */
    public function getParcels(): Collection { return $this->parcels; }

    public function addParcel(Parcel $parcel): void
    {
        if (!$this->parcels->contains($parcel)) {
            $this->parcels->add($parcel);
        }
    }

    public function removeParcel(Parcel $parcel): void
    {
        $this->parcels->removeElement($parcel);
    }

    public function recalculateTotals(): void
    {
        $totalWeight = 0.0;
        $totalVolume = 0.0;
        foreach ($this->parcels as $parcel) {
            $totalWeight += $parcel->getWeightKg();
            $totalVolume += $parcel->getVolumeM3();
        }
        $this->totalWeightKg = (string) $totalWeight;
        $this->totalVolumeM3 = (string) $totalVolume;
        $this->totalParcels = $this->parcels->count();
    }

    public function getPriority(): ShipmentPriority { return $this->priority; }
    public function setPriority(ShipmentPriority $priority): void { $this->priority = $priority; }

    /** @return VehicleSkill[] */
    public function getRequiredSkills(): array
    {
        return array_filter(
            array_map(
                static fn (int $v): ?VehicleSkill => VehicleSkill::tryFrom($v),
                $this->requiredSkills ?? [],
            ),
        );
    }

    /** @param VehicleSkill[] $requiredSkills */
    public function setRequiredSkills(array $requiredSkills): void
    {
        $this->requiredSkills = array_map(
            static fn (VehicleSkill $s): int => $s->value,
            $requiredSkills,
        );
    }

    // ── SoftDeletableInterface ──

    public function getDeletedAt(): ?DateTimeImmutable { return $this->deletedAt; }

    public function isDeleted(): bool { return $this->deletedAt !== null; }

    public function softDelete(): void { $this->deletedAt = new DateTimeImmutable(); }
}
