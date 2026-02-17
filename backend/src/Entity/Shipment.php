<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\PublicIdTrait;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\ShipmentRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_shipment_public_id', columns: ['public_id'])]
#[ORM\HasLifecycleCallbacks]
class Shipment
{
    use PublicIdTrait;

    #[ORM\Column(length: 80, unique: true)]
    private string $reference;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Customer $customer;

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
}
