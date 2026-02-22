<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\PublicIdTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_customer_public_id', columns: ['public_id'])]
#[ORM\HasLifecycleCallbacks]
class Customer
{
    use PublicIdTrait;

    #[ORM\Column(length: 150)]
    private string $name;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $contactPhone = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $webhookUrl = null;

    #[ORM\Column]
    private bool $isActive = true;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getName(): string { return $this->name; }
    public function setName(string $name): void { $this->name = $name; }
    public function getAddress(): ?string { return $this->address; }
    public function setAddress(?string $address): void { $this->address = $address; }
    public function getContactPhone(): ?string { return $this->contactPhone; }
    public function setContactPhone(?string $contactPhone): void { $this->contactPhone = $contactPhone; }
    public function getWebhookUrl(): ?string { return $this->webhookUrl; }
    public function setWebhookUrl(?string $webhookUrl): void { $this->webhookUrl = $webhookUrl; }
    public function isActive(): bool { return $this->isActive; }
    public function setActive(bool $isActive): void { $this->isActive = $isActive; }
}
