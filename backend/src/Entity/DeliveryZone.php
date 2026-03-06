<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\PublicIdTrait;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'delivery_zone')]
#[ORM\UniqueConstraint(name: 'uniq_delivery_zone_public_id', columns: ['public_id'])]
#[ORM\HasLifecycleCallbacks]
class DeliveryZone
{
    use PublicIdTrait;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Customer $customer = null;

    #[ORM\Column(length: 150)]
    private string $name;

    #[ORM\Column(type: 'float')]
    private float $centerLat;

    #[ORM\Column(type: 'float')]
    private float $centerLng;

    #[ORM\Column(type: 'float')]
    private float $radiusKm;

    #[ORM\Column]
    private int $deliveryCount;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct(string $name, float $centerLat, float $centerLng, float $radiusKm, int $deliveryCount)
    {
        $this->name = $name;
        $this->centerLat = $centerLat;
        $this->centerLng = $centerLng;
        $this->radiusKm = $radiusKm;
        $this->deliveryCount = $deliveryCount;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): void
    {
        $this->customer = $customer;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCenterLat(): float
    {
        return $this->centerLat;
    }

    public function setCenterLat(float $centerLat): void
    {
        $this->centerLat = $centerLat;
    }

    public function getCenterLng(): float
    {
        return $this->centerLng;
    }

    public function setCenterLng(float $centerLng): void
    {
        $this->centerLng = $centerLng;
    }

    public function getRadiusKm(): float
    {
        return $this->radiusKm;
    }

    public function setRadiusKm(float $radiusKm): void
    {
        $this->radiusKm = $radiusKm;
    }

    public function getDeliveryCount(): int
    {
        return $this->deliveryCount;
    }

    public function setDeliveryCount(int $deliveryCount): void
    {
        $this->deliveryCount = $deliveryCount;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
