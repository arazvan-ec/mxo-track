<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\AuditLog;
use App\Entity\Customer;
use App\Entity\Route;
use App\Entity\Shipment;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsDoctrineListener(event: Events::preUpdate)]
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
final class AuditSubscriber
{
    /** @var array<string, array<string, array{old: mixed, new: mixed}>> */
    private array $pendingChanges = [];

    private const array AUDITED_ENTITIES = [
        User::class,
        Route::class,
        Shipment::class,
        Customer::class,
    ];

    public function __construct(
        private readonly Security $security,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$this->isAuditable($entity)) {
            return;
        }

        $changeSet = $args->getEntityChangeSet();
        $changes = [];

        foreach ($changeSet as $field => [$old, $new]) {
            // Skip password hash from audit trail
            if ($field === 'passwordHash') {
                $changes[$field] = ['old' => '***', 'new' => '***'];
                continue;
            }

            $changes[$field] = [
                'old' => $this->normalizeValue($old),
                'new' => $this->normalizeValue($new),
            ];
        }

        if ($changes !== []) {
            $oid = spl_object_id($entity);
            $this->pendingChanges[(string) $oid] = $changes;
        }
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$this->isAuditable($entity)) {
            return;
        }

        $this->createAuditEntry($args->getObjectManager(), $entity, 'CREATE');
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$this->isAuditable($entity)) {
            return;
        }

        $oid = (string) spl_object_id($entity);
        $changes = $this->pendingChanges[$oid] ?? null;
        unset($this->pendingChanges[$oid]);

        $this->createAuditEntry($args->getObjectManager(), $entity, 'UPDATE', $changes);
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$this->isAuditable($entity)) {
            return;
        }

        $this->createAuditEntry($args->getObjectManager(), $entity, 'DELETE');
    }

    private function isAuditable(object $entity): bool
    {
        foreach (self::AUDITED_ENTITIES as $class) {
            if ($entity instanceof $class) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, array{old: mixed, new: mixed}>|null $changes
     */
    private function createAuditEntry(EntityManagerInterface $em, object $entity, string $action, ?array $changes = null): void
    {
        // Avoid infinite recursion: don't audit AuditLog itself
        if ($entity instanceof AuditLog) {
            return;
        }

        $actor = null;
        $securityUser = $this->security->getUser();
        if ($securityUser instanceof User) {
            $actor = $em->getReference(User::class, $securityUser->getId());
        }

        $entityType = $this->resolveEntityType($entity);
        $entityId = $this->resolveEntityId($entity);

        $auditLog = new AuditLog($actor, $action, $entityType, $entityId);

        $request = $this->requestStack->getCurrentRequest();
        if ($request !== null) {
            $auditLog->setIpAddress($request->getClientIp());
        }

        if ($changes !== null) {
            $auditLog->setChanges($changes);
        }

        $em->persist($auditLog);
    }

    private function resolveEntityType(object $entity): string
    {
        $className = $entity::class;
        $parts = explode('\\', $className);

        return end($parts);
    }

    private function resolveEntityId(object $entity): string
    {
        if (method_exists($entity, 'getPublicIdString')) {
            return $entity->getPublicIdString();
        }

        if (method_exists($entity, 'getId')) {
            return (string) ($entity->getId() ?? '0');
        }

        return '0';
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DATE_ATOM);
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        if (is_object($value)) {
            if (method_exists($value, 'getPublicIdString')) {
                return $value->getPublicIdString();
            }
            if (method_exists($value, '__toString')) {
                return (string) $value;
            }

            return $value::class;
        }

        return $value;
    }
}
