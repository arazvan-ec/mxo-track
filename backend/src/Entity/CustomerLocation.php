<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\PublicIdTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_customer_location_public_id', columns: ['public_id'])]
#[ORM\HasLifecycleCallbacks]
class CustomerLocation implements CustomerScopedEntityInterface
{
    use PublicIdTrait;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Customer $customer;

    #[ORM\Column(length: 150)]
    private string $name;

    #[ORM\Column(length: 255)]
    private string $address;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $latitude = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $longitude = null;

    #[ORM\Column]
    private bool $isDefault = false;

    #[ORM\Column]
    private bool $isActive = true;

    public function __construct(Customer $customer, string $name, string $address)
    {
        $this->customer = $customer;
        $this->name = $name;
        $this->address = $address;
    }

    public function getCustomer(): Customer { return $this->customer; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): void { $this->name = $name; }
    public function getAddress(): string { return $this->address; }
    public function setAddress(string $address): void { $this->address = $address; }
    public function getLatitude(): ?float { return $this->latitude; }
    public function setLatitude(?float $latitude): void { $this->latitude = $latitude; }
    public function getLongitude(): ?float { return $this->longitude; }
    public function setLongitude(?float $longitude): void { $this->longitude = $longitude; }
    public function isDefault(): bool { return $this->isDefault; }
    public function setDefault(bool $isDefault): void { $this->isDefault = $isDefault; }
    public function isActive(): bool { return $this->isActive; }
    public function setActive(bool $isActive): void { $this->isActive = $isActive; }
}
