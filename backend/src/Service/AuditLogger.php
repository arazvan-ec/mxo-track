<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AuditLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class AuditLogger
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function log(User $actor, string $action, string $entityType, string $entityId, array $payload = []): void
    {
        $this->entityManager->persist(new AuditLog($actor, $action, $entityType, Uuid::fromString($entityId), $payload));
    }
}
