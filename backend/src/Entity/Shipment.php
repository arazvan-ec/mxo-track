<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\PublicIdTrait;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\ShipmentRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_shipment_public_id', columns: ['public_id'])]
#[ORM\HasLifecycleCallbacks]
class Shipment implements CustomerScopedEntityInterface
{
    use PublicIdTrait;

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

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct(string $reference, Customer $customer)
    {
        $this->reference = $reference;
        $this->customer = $customer;
        $this->createdAt = new DateTimeImmutable();
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
}
