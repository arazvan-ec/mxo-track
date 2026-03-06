<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\PublicIdTrait;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'csv_import_run')]
#[ORM\UniqueConstraint(name: 'uniq_csv_import_run_public_id', columns: ['public_id'])]
#[ORM\HasLifecycleCallbacks]
class CsvImportRun implements CustomerScopedEntityInterface
{
    use PublicIdTrait;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Customer $customer;

    #[ORM\Column]
    private int $createdCount = 0;

    #[ORM\Column]
    private int $skippedCount = 0;

    #[ORM\Column(nullable: true)]
    private ?int $qualityScore = null;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct(Customer $customer, int $createdCount, int $skippedCount)
    {
        $this->customer = $customer;
        $this->createdCount = $createdCount;
        $this->skippedCount = $skippedCount;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getCustomer(): Customer { return $this->customer; }
    public function getCreatedCount(): int { return $this->createdCount; }
    public function getSkippedCount(): int { return $this->skippedCount; }
    public function getQualityScore(): ?int { return $this->qualityScore; }
    public function setQualityScore(?int $qualityScore): void { $this->qualityScore = $qualityScore; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
}
