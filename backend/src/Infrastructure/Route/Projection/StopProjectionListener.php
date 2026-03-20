<?php

declare(strict_types=1);

namespace App\Infrastructure\Route\Projection;

use App\Domain\Event\RoutesBuilt;
use App\Domain\Event\StopDelivered;
use App\Domain\Event\StopExceptionReported;
use App\Domain\Event\StopSkipped;
use App\Domain\Event\StopsReordered;
use App\Domain\Route\Repository\RouteRepositoryInterface;
use App\Domain\Route\Repository\RouteStopRepositoryInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Updates the stop_current_status projection table from domain events.
 */
final readonly class StopProjectionListener
{
    public function __construct(
        private Connection $connection,
        private RouteRepositoryInterface $routeRepo,
        private RouteStopRepositoryInterface $stopRepo,
    ) {}

    #[AsEventListener]
    public function onRoutesBuilt(RoutesBuilt $event): void
    {
        foreach ($event->routePublicIds as $publicId) {
            $route = $this->routeRepo->findOneByPublicId($publicId);
            if (!$route) {
                continue;
            }

            $stops = $this->stopRepo->findByRoute($route);
            foreach ($stops as $stop) {
                try {
                    $stopPublicId = $stop->getPublicId()->toRfc4122();
                } catch (\Error) {
                    $stopPublicId = null;
                }

                $this->connection->executeStatement(
                    <<<'SQL'
                        INSERT INTO stop_current_status (stop_id, route_id, public_id, status, sequence, is_origin, updated_at)
                        VALUES (:stop_id, :route_id, :public_id, :status, :sequence, :is_origin, NOW())
                        ON CONFLICT (stop_id) DO UPDATE SET
                            status = EXCLUDED.status,
                            sequence = EXCLUDED.sequence,
                            is_origin = EXCLUDED.is_origin,
                            updated_at = NOW()
                    SQL,
                    [
                        'stop_id' => $stop->getId(),
                        'route_id' => $route->getId(),
                        'public_id' => $stopPublicId,
                        'status' => $stop->getStatus()->value,
                        'sequence' => $stop->getSequence(),
                        'is_origin' => $stop->isOrigin() ? 'true' : 'false',
                    ],
                );
            }
        }
    }

    #[AsEventListener]
    public function onStopDelivered(StopDelivered $event): void
    {
        $stop = $this->stopRepo->findOneByPublicId($event->stopPublicId);
        if (!$stop) {
            return;
        }

        $this->connection->executeStatement(
            'UPDATE stop_current_status SET status = :status, delivered_at = NOW(), updated_at = NOW() WHERE stop_id = :stop_id',
            ['status' => 'DELIVERED', 'stop_id' => $stop->getId()],
        );
    }

    #[AsEventListener]
    public function onStopExceptionReported(StopExceptionReported $event): void
    {
        $stop = $this->stopRepo->findOneByPublicId($event->stopPublicId);
        if (!$stop) {
            return;
        }

        $this->connection->executeStatement(
            'UPDATE stop_current_status SET status = :status, exception_code = :code, updated_at = NOW() WHERE stop_id = :stop_id',
            ['status' => 'EXCEPTION', 'code' => $event->reason->value, 'stop_id' => $stop->getId()],
        );
    }

    #[AsEventListener]
    public function onStopSkipped(StopSkipped $event): void
    {
        $stop = $this->stopRepo->findOneByPublicId($event->stopPublicId);
        if (!$stop) {
            return;
        }

        $this->connection->executeStatement(
            'UPDATE stop_current_status SET status = :status, updated_at = NOW() WHERE stop_id = :stop_id',
            ['status' => 'SKIPPED', 'stop_id' => $stop->getId()],
        );
    }

    #[AsEventListener]
    public function onStopsReordered(StopsReordered $event): void
    {
        $route = $this->routeRepo->findOneByPublicId($event->routePublicId);
        if (!$route) {
            return;
        }

        $stops = $this->stopRepo->findByRoute($route);
        foreach ($stops as $stop) {
            $this->connection->executeStatement(
                'UPDATE stop_current_status SET sequence = :sequence, updated_at = NOW() WHERE stop_id = :stop_id',
                ['sequence' => $stop->getSequence(), 'stop_id' => $stop->getId()],
            );
        }
    }
}
