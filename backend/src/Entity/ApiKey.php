<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\PublicIdTrait;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'api_key')]
#[ORM\UniqueConstraint(name: 'uniq_api_key_public_id', columns: ['public_id'])]
#[ORM\UniqueConstraint(name: 'uniq_api_key_key_hash', columns: ['key_hash'])]
#[ORM\HasLifecycleCallbacks]
class ApiKey implements CustomerScopedEntityInterface
{
    use PublicIdTrait;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Customer $customer;

    #[ORM\Column(length: 128, unique: true)]
    private string $keyHash;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private int $rateLimitPerMinute = 60;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $lastUsedAt = null;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct(Customer $customer, string $keyHash, string $name)
    {
        $this->customer = $customer;
        $this->keyHash = $keyHash;
        $this->name = $name;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function getKeyHash(): string
    {
        return $this->keyHash;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function getRateLimitPerMinute(): int
    {
        return $this->rateLimitPerMinute;
    }

    public function setRateLimitPerMinute(int $rateLimitPerMinute): void
    {
        $this->rateLimitPerMinute = $rateLimitPerMinute;
    }

    public function getLastUsedAt(): ?DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function touchLastUsed(): void
    {
        $this->lastUsedAt = new DateTimeImmutable();
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
