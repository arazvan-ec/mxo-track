<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\PublicIdTrait;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'route_plan_template')]
#[ORM\UniqueConstraint(name: 'uniq_route_plan_template_public_id', columns: ['public_id'])]
#[ORM\HasLifecycleCallbacks]
class RoutePlanTemplate implements CustomerScopedEntityInterface
{
    use PublicIdTrait;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'El nombre de la plantilla es obligatorio.')]
    #[Assert\Length(max: 100)]
    private string $name;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(name: 'customer_id', nullable: false, onDelete: 'CASCADE')]
    private Customer $customer;

    /** @var array<int, array{address: string, latitude: float|null, longitude: float|null, sequence: int}> */
    #[ORM\Column(type: 'json')]
    private array $templateData = [];

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $updatedAt = null;

    public function __construct(string $name, Customer $customer)
    {
        $this->name = $name;
        $this->customer = $customer;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function setCustomer(Customer $customer): void
    {
        $this->customer = $customer;
    }

    /** @return array<int, array{address: string, latitude: float|null, longitude: float|null, sequence: int}> */
    public function getTemplateData(): array
    {
        return $this->templateData;
    }

    /** @param array<int, array{address: string, latitude: float|null, longitude: float|null, sequence: int}> $templateData */
    public function setTemplateData(array $templateData): void
    {
        $this->templateData = $templateData;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
