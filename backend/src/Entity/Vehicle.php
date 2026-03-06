<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\PublicIdTrait;
use App\Entity\Concerns\SoftDeleteTrait;
use App\Enum\VehicleSkill;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: \App\Repository\VehicleRepository::class)]
#[ORM\Table(name: 'vehicle')]
#[ORM\UniqueConstraint(name: 'uniq_vehicle_public_id', columns: ['public_id'])]
#[ORM\Index(name: 'idx_vehicle_deleted_at', columns: ['deleted_at'])]
#[ORM\HasLifecycleCallbacks]
class Vehicle implements SoftDeletableInterface
{
    use PublicIdTrait;
    use SoftDeleteTrait;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank(message: 'El nombre del vehiculo es obligatorio.')]
    #[Assert\Length(max: 120)]
    private string $name;

    #[ORM\Column(nullable: true)]
    private ?int $traccarDeviceId = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $maxWeightKg = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 4, nullable: true)]
    private ?string $maxVolumeM3 = null;

    #[ORM\Column(nullable: true)]
    private ?int $maxParcels = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $skills = [];

    public function __construct(string $name)
    {
        $now = new \DateTimeImmutable();
        $this->name = $name;
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getName(): string { return $this->name; }
    public function setName(string $name): void { $this->name = $name; }
    public function getTraccarDeviceId(): ?int { return $this->traccarDeviceId; }
    public function setTraccarDeviceId(?int $id): void { $this->traccarDeviceId = $id; }
    public function getMaxWeightKg(): ?float { return $this->maxWeightKg !== null ? (float) $this->maxWeightKg : null; }
    public function setMaxWeightKg(?float $maxWeightKg): void { $this->maxWeightKg = $maxWeightKg !== null ? (string) $maxWeightKg : null; }
    public function getMaxVolumeM3(): ?float { return $this->maxVolumeM3 !== null ? (float) $this->maxVolumeM3 : null; }
    public function setMaxVolumeM3(?float $maxVolumeM3): void { $this->maxVolumeM3 = $maxVolumeM3 !== null ? (string) $maxVolumeM3 : null; }
    public function getMaxParcels(): ?int { return $this->maxParcels; }
    public function setMaxParcels(?int $maxParcels): void { $this->maxParcels = $maxParcels; }
    public function isActive(): bool { return $this->isActive; }
    public function setActive(bool $isActive): void { $this->isActive = $isActive; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    /** @return VehicleSkill[] */
    public function getSkills(): array
    {
        return array_filter(
            array_map(
                static fn (int $v): ?VehicleSkill => VehicleSkill::tryFrom($v),
                $this->skills ?? [],
            ),
        );
    }

    /** @param VehicleSkill[] $skills */
    public function setSkills(array $skills): void
    {
        $this->skills = array_map(
            static fn (VehicleSkill $s): int => $s->value,
            $skills,
        );
    }

    #[ORM\PrePersist]
    public function touchCreatedAt(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function touchUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}

