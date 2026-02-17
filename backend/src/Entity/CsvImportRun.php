<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'csv_import_run')]
class CsvImportRun
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Customer $customer;

    #[ORM\Column]
    private int $createdCount = 0;

    #[ORM\Column]
    private int $skippedCount = 0;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct(Customer $customer, int $createdCount, int $skippedCount)
    {
        $this->id = Uuid::v7();
        $this->customer = $customer;
        $this->createdCount = $createdCount;
        $this->skippedCount = $skippedCount;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getCustomer(): Customer { return $this->customer; }
    public function getCreatedCount(): int { return $this->createdCount; }
    public function getSkippedCount(): int { return $this->skippedCount; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
}
