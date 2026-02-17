<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\PublicIdTrait;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'audit_log')]
#[ORM\UniqueConstraint(name: 'uniq_audit_log_public_id', columns: ['public_id'])]
#[ORM\HasLifecycleCallbacks]
class AuditLog
{
    use PublicIdTrait;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'actor_user_id', nullable: false)]
    private User $actor;

    #[ORM\Column(length: 80)]
    private string $action;

    #[ORM\Column(length: 80)]
    private string $entityType;

    #[ORM\Column(length: 40)]
    private string $entityId;

    #[ORM\Column(type: 'json')]
    private array $payload = [];

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct(User $actor, string $action, string $entityType, string $entityId, array $payload = [])
    {
        $this->actor = $actor;
        $this->action = $action;
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        $this->payload = $payload;
        $this->createdAt = new DateTimeImmutable();
    }
}
