<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\PublicIdTrait;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'reoptimization_policy')]
#[ORM\UniqueConstraint(name: 'uniq_reoptimization_policy_public_id', columns: ['public_id'])]
#[ORM\UniqueConstraint(name: 'uniq_reoptimization_policy_customer', columns: ['customer_id'])]
#[ORM\HasLifecycleCallbacks]
class ReoptimizationPolicy
{
    use PublicIdTrait;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Customer $customer;

    #[ORM\Column(type: Types::JSON)]
    private array $triggers;

    #[ORM\Column]
    private int $delayThresholdMinutes;

    #[ORM\Column]
    private int $consecutiveExceptionThreshold;

    #[ORM\Column]
    private int $cooldownMinutes;

    #[ORM\Column]
    private bool $enabled;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        Customer $customer,
        array $triggers = [],
        int $delayThresholdMinutes = 30,
        int $consecutiveExceptionThreshold = 2,
        int $cooldownMinutes = 10,
        bool $enabled = true,
    ) {
        $this->customer = $customer;
        $this->triggers = $triggers;
        $this->delayThresholdMinutes = $delayThresholdMinutes;
        $this->consecutiveExceptionThreshold = $consecutiveExceptionThreshold;
        $this->cooldownMinutes = $cooldownMinutes;
        $this->enabled = $enabled;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function getTriggers(): array
    {
        return $this->triggers;
    }

    public function setTriggers(array $triggers): void
    {
        $this->triggers = $triggers;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getDelayThresholdMinutes(): int
    {
        return $this->delayThresholdMinutes;
    }

    public function setDelayThresholdMinutes(int $delayThresholdMinutes): void
    {
        $this->delayThresholdMinutes = $delayThresholdMinutes;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getConsecutiveExceptionThreshold(): int
    {
        return $this->consecutiveExceptionThreshold;
    }

    public function setConsecutiveExceptionThreshold(int $consecutiveExceptionThreshold): void
    {
        $this->consecutiveExceptionThreshold = $consecutiveExceptionThreshold;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getCooldownMinutes(): int
    {
        return $this->cooldownMinutes;
    }

    public function setCooldownMinutes(int $cooldownMinutes): void
    {
        $this->cooldownMinutes = $cooldownMinutes;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function allowsTrigger(string $trigger): bool
    {
        return in_array($trigger, $this->triggers, true);
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
