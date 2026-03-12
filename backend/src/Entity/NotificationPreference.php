<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\PublicIdTrait;
use App\Enum\NotificationChannel;
use App\Enum\NotificationTriggerType;
use App\Repository\NotificationPreferenceRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationPreferenceRepository::class)]
#[ORM\Table(name: 'notification_preference')]
#[ORM\UniqueConstraint(name: 'uniq_notification_preference_public_id', columns: ['public_id'])]
#[ORM\UniqueConstraint(name: 'uniq_notif_pref_customer_trigger_channel', columns: ['customer_id', 'trigger_type', 'channel'])]
#[ORM\HasLifecycleCallbacks]
class NotificationPreference implements CustomerScopedEntityInterface
{
    use PublicIdTrait;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Customer $customer;

    #[ORM\Column(length: 30, enumType: NotificationTriggerType::class)]
    private NotificationTriggerType $triggerType;

    #[ORM\Column(length: 20, enumType: NotificationChannel::class)]
    private NotificationChannel $channel;

    #[ORM\Column]
    private bool $enabled;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $messageTemplate;

    #[ORM\Column(type: Types::JSON)]
    private array $timingConfig;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        Customer $customer,
        NotificationTriggerType $triggerType,
        NotificationChannel $channel,
        bool $enabled = true,
        ?string $messageTemplate = null,
        array $timingConfig = [],
    ) {
        $this->customer = $customer;
        $this->triggerType = $triggerType;
        $this->channel = $channel;
        $this->enabled = $enabled;
        $this->messageTemplate = $messageTemplate;
        $this->timingConfig = $timingConfig;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getCustomer(): Customer { return $this->customer; }
    public function getTriggerType(): NotificationTriggerType { return $this->triggerType; }
    public function getChannel(): NotificationChannel { return $this->channel; }
    public function isEnabled(): bool { return $this->enabled; }
    public function setEnabled(bool $enabled): void { $this->enabled = $enabled; $this->updatedAt = new DateTimeImmutable(); }
    public function getMessageTemplate(): ?string { return $this->messageTemplate; }
    public function setMessageTemplate(?string $messageTemplate): void { $this->messageTemplate = $messageTemplate; $this->updatedAt = new DateTimeImmutable(); }
    public function getTimingConfig(): array { return $this->timingConfig; }
    public function setTimingConfig(array $timingConfig): void { $this->timingConfig = $timingConfig; $this->updatedAt = new DateTimeImmutable(); }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): DateTimeImmutable { return $this->updatedAt; }
}
