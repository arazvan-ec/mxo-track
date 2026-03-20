<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\DriverAction;
use App\Domain\Route\Model\RouteStop;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class DriverActionService
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function register(User $driver, string $clientActionId, string $type, RouteStop $stop): bool
    {
        $exists = $this->entityManager->getRepository(DriverAction::class)->findOneBy([
            'driver' => $driver,
            'clientActionId' => Uuid::fromString($clientActionId),
        ]);

        if ($exists instanceof DriverAction) {
            return false;
        }

        $this->entityManager->persist(new DriverAction($driver, Uuid::fromString($clientActionId), $type, $stop));
        $this->entityManager->flush();

        return true;
    }
}
