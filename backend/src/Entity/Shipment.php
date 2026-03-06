<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\PublicIdTrait;
use App\Entity\Concerns\SoftDeleteTrait;
use App\Enum\ServiceType;
use App\Enum\ShipmentPriority;
use App\Enum\VehicleSkill;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: \App\Repository\ShipmentRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_shipment_public_id', columns: ['public_id'])]
#[ORM\UniqueConstraint(name: 'uniq_shipment_tracking_token', columns: ['tracking_token'])]
#[ORM\Index(name: 'idx_shipment_deleted_at', columns: ['deleted_at'])]
#[ORM\HasLifecycleCallbacks]
class Shipment implements CustomerScopedEntityInterface, SoftDeletableInterface
{
    use PublicIdTrait;
    use SoftDeleteTrait;

    #[ORM\Column(length: 80, unique: true)]
    private string $reference;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Customer $customer;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $recipientName = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $recipientPhone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $latitude = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $longitude = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(length: 30, enumType: ServiceType::class)]
    private ServiceType $serviceType = ServiceType::DELIVERY;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $totalWeightKg = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 4, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $totalVolumeM3 = null;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $totalParcels = 1;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $estimatedDeliveryDate = null;

    #[ORM\Column(type: 'time_immutable', nullable: true)]
    private ?DateTimeImmutable $preferredWindowStart = null;

    #[ORM\Column(type: 'time_immutable', nullable: true)]
    private ?DateTimeImmutable $preferredWindowEnd = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $serviceTimeSeconds = null;

    /** @var Collection<int, Parcel> */
    #[ORM\OneToMany(targetEntity: Parcel::class, mappedBy: 'shipment', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $parcels;

    #[ORM\Column(type: 'smallint', enumType: ShipmentPriority::class)]
    private ShipmentPriority $priority = ShipmentPriority::NORMAL;

    #[ORM\Column(length: 20, nullable: true, unique: true)]
    private ?string $trackingToken = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $requiredSkills = [];

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct(string $reference, Customer $customer)
    {
        $this->reference = $reference;
        $this->customer = $customer;
        $this->createdAt = new DateTimeImmutable();
        $this->trackingToken = self::generateTrackingToken();
        $this->parcels = new ArrayCollection();
    }

    public static function generateTrackingToken(): string
    {
        $bytes = random_bytes(6);
        $hex = strtoupper(bin2hex($bytes));

        return sprintf('TRK-%s-%s', substr($hex, 0, 4), substr($hex, 4, 4));
    }

    public function getReference(): string { return $this->reference; }
    public function getCustomer(): Customer { return $this->customer; }
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

    public function getPriority(): ShipmentPriority { return $this->priority; }
    public function setPriority(ShipmentPriority $priority): void { $this->priority = $priority; }
}
