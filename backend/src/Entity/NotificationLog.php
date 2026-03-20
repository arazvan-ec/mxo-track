<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Shipment\Model\Shipment;
use App\Entity\Concerns\PublicIdTrait;
use App\Enum\NotificationChannel;
use App\Enum\NotificationLogStatus;
use App\Enum\NotificationTriggerType;
use App\Repository\NotificationLogRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationLogRepository::class)]
#[ORM\Table(name: 'notification_log')]
#[ORM\UniqueConstraint(name: 'uniq_notification_log_public_id', columns: ['public_id'])]
#[ORM\Index(name: 'idx_notif_dedup', columns: ['shipment_id', 'trigger_type', 'channel'])]
#[ORM\Index(name: 'idx_notif_throttle', columns: ['recipient_phone', 'channel', 'created_at'])]
#[ORM\Index(name: 'idx_notif_quota', columns: ['customer_id', 'channel', 'created_at'])]
#[ORM\HasLifecycleCallbacks]
class NotificationLog implements CustomerScopedEntityInterface
{
    use PublicIdTrait;

    #[ORM\ManyToOne(targetEntity: Shipment::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Shipment $shipment;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Customer $customer;

    #[ORM\Column(length: 20, enumType: NotificationChannel::class)]
    private NotificationChannel $channel;

    #[ORM\Column(length: 30, enumType: NotificationTriggerType::class)]
    private NotificationTriggerType $triggerType;

    #[ORM\Column(length: 20)]
    private string $recipientPhone;

    #[ORM\Column(type: Types::TEXT)]
    private string $messageContent;

    #[ORM\Column(length: 20, enumType: NotificationLogStatus::class)]
    private NotificationLogStatus $status;

    #[ORM\Column(type: Types::JSON)]
    private array $providerResponse = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    public function __construct(
        Shipment $shipment,
        Customer $customer,
        NotificationChannel $channel,
        NotificationTriggerType $triggerType,
        string $recipientPhone,
        string $messageContent,
        NotificationLogStatus $status,
        array $providerResponse = [],
    ) {
        $this->shipment = $shipment;
        $this->customer = $customer;
        $this->channel = $channel;
        $this->triggerType = $triggerType;
        $this->recipientPhone = $recipientPhone;
        $this->messageContent = $messageContent;
        $this->status = $status;
        $this->providerResponse = $providerResponse;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getShipment(): Shipment { return $this->shipment; }
    public function getCustomer(): Customer { return $this->customer; }
    public function getChannel(): NotificationChannel { return $this->channel; }
    public function getTriggerType(): NotificationTriggerType { return $this->triggerType; }
    public function getRecipientPhone(): string { return $this->recipientPhone; }
    public function getMessageContent(): string { return $this->messageContent; }
    public function getStatus(): NotificationLogStatus { return $this->status; }
    public function getProviderResponse(): array { return $this->providerResponse; }
    public function setProviderResponse(array $providerResponse): void { $this->providerResponse = $providerResponse; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
}
