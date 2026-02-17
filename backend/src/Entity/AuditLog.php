<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'audit_log')]
class AuditLog
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'actor_user_id', nullable: false)]
    private User $actor;

    #[ORM\Column(length: 80)]
    private string $action;

    #[ORM\Column(length: 80)]
    private string $entityType;

    #[ORM\Column(type: 'uuid')]
    private Uuid $entityId;

    #[ORM\Column(type: 'json')]
    private array $payload = [];

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct(User $actor, string $action, string $entityType, Uuid $entityId, array $payload = [])
    {
        $this->id = Uuid::v7();
        $this->actor = $actor;
        $this->action = $action;
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        $this->payload = $payload;
        $this->createdAt = new DateTimeImmutable();
    }
}
