<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\PublicIdTrait;
use App\Repository\NotificationRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'notification')]
#[ORM\UniqueConstraint(name: 'uniq_notification_public_id', columns: ['public_id'])]
#[ORM\Index(name: 'idx_notification_user_read', columns: ['user_id', 'is_read'])]
#[ORM\HasLifecycleCallbacks]
class Notification
{
    use PublicIdTrait;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 60)]
    private string $type;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: Types::TEXT)]
    private string $message;

    #[ORM\Column(length: 20)]
    private string $channel = 'in_app';

    #[ORM\Column]
    private bool $isRead = false;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $payload = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $readAt = null;

    public function __construct(User $user, string $type, string $title, string $message, string $channel = 'in_app', ?array $payload = null)
    {
        $this->user = $user;
        $this->type = $type;
        $this->title = $title;
        $this->message = $message;
        $this->channel = $channel;
        $this->payload = $payload;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getUser(): User { return $this->user; }
    public function getType(): string { return $this->type; }
    public function getTitle(): string { return $this->title; }
    public function getMessage(): string { return $this->message; }
    public function getChannel(): string { return $this->channel; }

    public function isRead(): bool { return $this->isRead; }

    public function getPayload(): ?array { return $this->payload; }

    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
    public function getReadAt(): ?DateTimeImmutable { return $this->readAt; }

    public function markAsRead(): void
    {
        if (!$this->isRead) {
            $this->isRead = true;
            $this->readAt = new DateTimeImmutable();
        }
    }
}
