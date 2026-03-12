<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\PublicIdTrait;
use App\Entity\Concerns\SoftDeleteTrait;
use App\Enum\ClientFrequency;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_customer_public_id', columns: ['public_id'])]
#[ORM\Index(name: 'idx_customer_deleted_at', columns: ['deleted_at'])]
#[ORM\HasLifecycleCallbacks]
class Customer implements SoftDeletableInterface
{
    use PublicIdTrait;
    use SoftDeleteTrait;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank(message: 'El nombre del cliente es obligatorio.')]
    #[Assert\Length(max: 150)]
    private string $name;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $contactPhone = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $webhookUrl = null;

    #[ORM\Column(length: 20, enumType: ClientFrequency::class, nullable: true)]
    private ?ClientFrequency $frequency = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $preferredDeliverySlot = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(nullable: true)]
    private ?int $notificationQuota = null;

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
    public function getFrequency(): ?ClientFrequency { return $this->frequency; }
    public function setFrequency(?ClientFrequency $frequency): void { $this->frequency = $frequency; }
    public function getPreferredDeliverySlot(): ?string { return $this->preferredDeliverySlot; }
    public function setPreferredDeliverySlot(?string $slot): void { $this->preferredDeliverySlot = $slot; }
    public function isActive(): bool { return $this->isActive; }
    public function setActive(bool $isActive): void { $this->isActive = $isActive; }
    public function getNotificationQuota(): ?int { return $this->notificationQuota; }
    public function setNotificationQuota(?int $notificationQuota): void { $this->notificationQuota = $notificationQuota; }
}
