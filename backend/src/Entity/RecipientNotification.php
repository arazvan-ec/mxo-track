<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Shipment\Model\Shipment;
use App\Entity\Concerns\PublicIdTrait;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'recipient_notification')]
#[ORM\UniqueConstraint(name: 'uniq_recipient_notification_public_id', columns: ['public_id'])]
#[ORM\Index(name: 'idx_recipient_notification_shipment', columns: ['shipment_id'])]
#[ORM\Index(name: 'idx_recipient_notification_status', columns: ['status'])]
#[ORM\HasLifecycleCallbacks]
class RecipientNotification
{
    use PublicIdTrait;

    #[ORM\ManyToOne(targetEntity: Shipment::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Shipment $shipment;

    #[ORM\Column(length: 20)]
    private string $channel;

    #[ORM\Column(length: 60)]
    private string $templateName;

    #[ORM\Column(length: 50)]
    private string $recipient;

    #[ORM\Column(length: 20)]
    private string $status;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $sentAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    public function __construct(Shipment $shipment, string $channel, string $templateName, string $recipient)
    {
        $this->shipment = $shipment;
        $this->channel = $channel;
        $this->templateName = $templateName;
        $this->recipient = $recipient;
        $this->status = 'sent';
        $this->createdAt = new DateTimeImmutable();
    }

    public function getShipment(): Shipment { return $this->shipment; }
    public function getChannel(): string { return $this->channel; }
    public function getTemplateName(): string { return $this->templateName; }
    public function getRecipient(): string { return $this->recipient; }
    public function getStatus(): string { return $this->status; }
    public function getSentAt(): ?DateTimeImmutable { return $this->sentAt; }
    public function getErrorMessage(): ?string { return $this->errorMessage; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }

    public function markSent(): void
    {
        $this->status = 'sent';
        $this->sentAt = new DateTimeImmutable();
    }

    public function markFailed(string $errorMessage): void
    {
        $this->status = 'failed';
        $this->errorMessage = $errorMessage;
    }

    public function markDelivered(): void
    {
        $this->status = 'delivered';
    }
}
