<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\PublicIdTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\VehicleRepository::class)]
#[ORM\Table(name: 'vehicle')]
#[ORM\UniqueConstraint(name: 'uniq_vehicle_public_id', columns: ['public_id'])]
#[ORM\HasLifecycleCallbacks]
class Vehicle
{
    use PublicIdTrait;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(nullable: true)]
    private ?int $traccarDeviceId = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $name)
    {
        $now = new \DateTimeImmutable();
        $this->name = $name;
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getName(): string { return $this->name; }
    public function getTraccarDeviceId(): ?int { return $this->traccarDeviceId; }
    public function setTraccarDeviceId(?int $id): void { $this->traccarDeviceId = $id; }
    public function isActive(): bool { return $this->isActive; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

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

