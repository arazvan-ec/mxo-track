<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\PublicIdTrait;
use App\Provider\ServiceType;
use App\Repository\CustomerIntegrationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CustomerIntegrationRepository::class)]
#[ORM\Table(name: 'customer_integration')]
#[ORM\UniqueConstraint(name: 'uniq_ci_customer_service_priority', columns: ['customer_id', 'service_type', 'priority'])]
#[ORM\Index(name: 'idx_ci_customer_service', columns: ['customer_id', 'service_type', 'enabled'])]
#[ORM\HasLifecycleCallbacks]
class CustomerIntegration implements CustomerScopedEntityInterface
{
    use PublicIdTrait;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Customer $customer;

    #[ORM\Column(length: 30, enumType: ServiceType::class)]
    private ServiceType $serviceType;

    #[ORM\Column(length: 50)]
    private string $providerType;

    #[ORM\Column(type: 'encrypted_json')]
    private array $config;

    #[ORM\Column]
    private bool $enabled;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $priority;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        Customer $customer,
        ServiceType $serviceType,
        string $providerType,
        array $config = [],
        bool $enabled = true,
        int $priority = 0,
    ) {
        $this->customer = $customer;
        $this->serviceType = $serviceType;
        $this->providerType = $providerType;
        $this->config = $config;
        $this->enabled = $enabled;
        $this->priority = $priority;

        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function getServiceType(): ServiceType
    {
        return $this->serviceType;
    }

    public function getProviderType(): string
    {
        return $this->providerType;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function setConfig(array $config): void
    {
        $this->config = $config;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
