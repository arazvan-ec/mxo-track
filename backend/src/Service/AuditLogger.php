<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AuditLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class AuditLogger
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function log(?User $actor, string $action, string $entityType, string $entityId, array $payload = [], ?array $changes = null): void
    {
        $auditLog = new AuditLog($actor, $action, $entityType, $entityId, $payload);

        $request = $this->requestStack->getCurrentRequest();
        if ($request !== null) {
            $auditLog->setIpAddress($request->getClientIp());
        }

        if ($changes !== null) {
            $auditLog->setChanges($changes);
        }

        $this->entityManager->persist($auditLog);
    }
}
