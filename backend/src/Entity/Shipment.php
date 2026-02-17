<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
class Shipment
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 80, unique: true)]
    private string $reference;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Customer $customer;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct(string $reference, Customer $customer)
    {
        $this->id = Uuid::v7();
        $this->reference = $reference;
        $this->customer = $customer;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): Uuid { return $this->id; }
    public function getReference(): string { return $this->reference; }
    public function getCustomer(): Customer { return $this->customer; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
}
