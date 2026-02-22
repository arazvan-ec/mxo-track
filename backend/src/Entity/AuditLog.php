<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\PublicIdTrait;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'audit_log')]
#[ORM\UniqueConstraint(name: 'uniq_audit_log_public_id', columns: ['public_id'])]
#[ORM\Index(name: 'idx_audit_log_entity', columns: ['entity_type', 'entity_id'])]
#[ORM\Index(name: 'idx_audit_log_action', columns: ['action'])]
#[ORM\Index(name: 'idx_audit_log_created_at', columns: ['created_at'])]
#[ORM\HasLifecycleCallbacks]
class AuditLog
{
    use PublicIdTrait;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'actor_user_id', nullable: true)]
    private ?User $actor;

    #[ORM\Column(length: 80)]
    private string $action;

    #[ORM\Column(length: 80)]
    private string $entityType;

    #[ORM\Column(length: 40)]
    private string $entityId;

    #[ORM\Column(type: 'json')]
    private array $payload = [];

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $changes = null;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct(?User $actor, string $action, string $entityType, string $entityId, array $payload = [])
    {
        $this->actor = $actor;
        $this->action = $action;
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        $this->payload = $payload;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getActor(): ?User
    {
        return $this->actor;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): void
    {
        $this->ipAddress = $ipAddress;
    }

    public function getChanges(): ?array
    {
        return $this->changes;
    }

    public function setChanges(?array $changes): void
    {
        $this->changes = $changes;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
