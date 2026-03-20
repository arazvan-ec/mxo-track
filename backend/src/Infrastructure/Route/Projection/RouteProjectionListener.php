<?php

declare(strict_types=1);

namespace App\Infrastructure\Route\Projection;

use App\Domain\Event\RouteAssigned;
use App\Domain\Event\RouteCancelled;
use App\Domain\Event\RouteCompleted;
use App\Domain\Event\RouteOptimized;
use App\Domain\Event\RouteReoptimized;
use App\Domain\Event\RouteStarted;
use App\Domain\Event\RoutesBuilt;
use App\Domain\Event\StopDelivered;
use App\Domain\Event\StopExceptionReported;
use App\Domain\Event\StopSkipped;
use App\Domain\Route\Repository\RouteRepositoryInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Updates the route_current_state projection table from domain events.
 */
final readonly class RouteProjectionListener
{
    public function __construct(
        private Connection $connection,
        private RouteRepositoryInterface $routeRepo,
    ) {}

    #[AsEventListener]
    public function onRoutesBuilt(RoutesBuilt $event): void
    {
        foreach ($event->routePublicIds as $publicId) {
            $route = $this->routeRepo->findOneByPublicId($publicId);
            if (!$route) {
                continue;
            }

            $this->connection->executeStatement(
                <<<'SQL'
                    INSERT INTO route_current_state (route_id, public_id, status, name, customer_id, updated_at)
                    VALUES (:route_id, :public_id, :status, :name, :customer_id, NOW())
                    ON CONFLICT (route_id) DO UPDATE SET
                        status = EXCLUDED.status,
                        name = EXCLUDED.name,
                        customer_id = EXCLUDED.customer_id,
                        updated_at = NOW()
                SQL,
                [
                    'route_id' => $route->getId(),
                    'public_id' => $route->getPublicIdString(),
                    'status' => $route->getStatus()->value,
                    'name' => $route->getName(),
                    'customer_id' => $route->getCustomer()?->getId(),
                ],
            );
        }
    }

    #[AsEventListener]
    public function onRouteStarted(RouteStarted $event): void
    {
        $this->connection->executeStatement(
            'UPDATE route_current_state SET status = :status, started_at = NOW(), driver_user_id = :driver_id, updated_at = NOW() WHERE public_id = :public_id',
            [
                'status' => 'ACTIVE',
                'driver_id' => $event->driverUserId,
                'public_id' => $event->routePublicId,
            ],
        );
    }

    #[AsEventListener]
    public function onRouteCompleted(RouteCompleted $event): void
    {
        $this->connection->executeStatement(
            'UPDATE route_current_state SET status = :status, completed_at = NOW(), updated_at = NOW() WHERE public_id = :public_id',
            ['status' => 'DONE', 'public_id' => $event->routePublicId],
        );
    }

    #[AsEventListener]
    public function onRouteCancelled(RouteCancelled $event): void
    {
        $this->connection->executeStatement(
            'UPDATE route_current_state SET status = :status, updated_at = NOW() WHERE public_id = :public_id',
            ['status' => 'CANCELLED', 'public_id' => $event->routePublicId],
        );
    }

    #[AsEventListener]
    public function onRouteAssigned(RouteAssigned $event): void
    {
        $this->connection->executeStatement(
            'UPDATE route_current_state SET driver_user_id = :driver_id, vehicle_id = :vehicle_id, updated_at = NOW() WHERE public_id = :public_id',
            [
                'driver_id' => $event->driverUserId,
                'vehicle_id' => null, // Vehicle ID not available from event (only public ID)
                'public_id' => $event->routePublicId,
            ],
        );
    }

    #[AsEventListener]
    public function onRouteOptimized(RouteOptimized $event): void
    {
        $this->connection->executeStatement(
            'UPDATE route_current_state SET total_distance_km = :distance, estimated_duration_minutes = :duration, updated_at = NOW() WHERE public_id = :public_id',
            [
                'distance' => $event->distanceKm,
                'duration' => $event->durationMinutes,
                'public_id' => $event->routePublicId,
            ],
        );
    }

    #[AsEventListener]
    public function onRouteReoptimized(RouteReoptimized $event): void
    {
        $this->connection->executeStatement(
            'UPDATE route_current_state SET total_distance_km = :distance, estimated_duration_minutes = :duration, updated_at = NOW() WHERE public_id = :public_id',
            [
                'distance' => $event->distanceKm,
                'duration' => $event->durationMinutes,
                'public_id' => $event->routePublicId,
            ],
        );
    }

    #[AsEventListener]
    public function onStopDelivered(StopDelivered $event): void
    {
        $this->updateStopCounters($event->routePublicId);
    }

    #[AsEventListener]
    public function onStopExceptionReported(StopExceptionReported $event): void
    {
        $this->updateStopCounters($event->routePublicId);
    }

    #[AsEventListener]
    public function onStopSkipped(StopSkipped $event): void
    {
        $this->updateStopCounters($event->routePublicId);
    }

    private function updateStopCounters(string $routePublicId): void
    {
        $this->connection->executeStatement(
            <<<'SQL'
                UPDATE route_current_state rcs SET
                    total_stops = sub.total,
                    delivered_stops = sub.delivered,
                    exception_stops = sub.exceptions,
                    pending_stops = sub.pending,
                    skipped_stops = sub.skipped,
                    updated_at = NOW()
                FROM (
                    SELECT
                        COUNT(*) FILTER (WHERE NOT scs.is_origin) AS total,
                        COUNT(*) FILTER (WHERE scs.status = 'DELIVERED') AS delivered,
                        COUNT(*) FILTER (WHERE scs.status = 'EXCEPTION') AS exceptions,
                        COUNT(*) FILTER (WHERE scs.status = 'PENDING') AS pending,
                        COUNT(*) FILTER (WHERE scs.status = 'SKIPPED') AS skipped
                    FROM stop_current_status scs
                    WHERE scs.route_id = rcs.route_id
                ) sub
                WHERE rcs.public_id = :public_id
            SQL,
            ['public_id' => $routePublicId],
        );
    }
}
