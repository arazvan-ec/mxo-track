<?php

declare(strict_types=1);

namespace App\Infrastructure\Route\Projection;

use App\Domain\Route\Repository\RouteEventRepositoryInterface;
use App\Domain\Route\Repository\RouteRepositoryInterface;
use App\Domain\Route\Repository\RouteStopRepositoryInterface;
use App\Entity\Route;
use App\Enum\RouteStopStatus;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Rebuilds projection tables from RouteEvent history.
 * Used for fixing inconsistencies or after schema changes.
 */
final readonly class ProjectionRebuilder
{
    public function __construct(
        private Connection $connection,
        private EntityManagerInterface $em,
        private RouteStopRepositoryInterface $stopRepo,
    ) {}

    /**
     * Rebuilds both projection tables from current entity state.
     *
     * @return array{routes: int, stops: int}
     */
    public function rebuildAll(): array
    {
        $this->connection->executeStatement('TRUNCATE TABLE route_current_state');
        $this->connection->executeStatement('TRUNCATE TABLE stop_current_status');

        $routes = $this->em->getRepository(Route::class)->findAll();
        $routeCount = 0;
        $stopCount = 0;

        foreach ($routes as $route) {
            $stops = $this->stopRepo->findByRoute($route);

            $total = 0;
            $delivered = 0;
            $exceptions = 0;
            $pending = 0;
            $skipped = 0;

            foreach ($stops as $stop) {
                try {
                    $stopPublicId = $stop->getPublicId()->toRfc4122();
                } catch (\Error) {
                    $stopPublicId = null;
                }

                $this->connection->executeStatement(
                    <<<'SQL'
                        INSERT INTO stop_current_status (stop_id, route_id, public_id, status, sequence, is_origin, delivered_at, exception_code, updated_at)
                        VALUES (:stop_id, :route_id, :public_id, :status, :sequence, :is_origin, :delivered_at, :exception_code, NOW())
                    SQL,
                    [
                        'stop_id' => $stop->getId(),
                        'route_id' => $route->getId(),
                        'public_id' => $stopPublicId,
                        'status' => $stop->getStatus()->value,
                        'sequence' => $stop->getSequence(),
                        'is_origin' => $stop->isOrigin() ? 'true' : 'false',
                        'delivered_at' => $stop->getDeliveredAt()?->format('Y-m-d H:i:s'),
                        'exception_code' => $stop->getExceptionCode()?->value,
                    ],
                );
                $stopCount++;

                if (!$stop->isOrigin()) {
                    $total++;
                    match ($stop->getStatus()) {
                        RouteStopStatus::DELIVERED => $delivered++,
                        RouteStopStatus::EXCEPTION => $exceptions++,
                        RouteStopStatus::SKIPPED => $skipped++,
                        default => $pending++,
                    };
                }
            }

            $this->connection->executeStatement(
                <<<'SQL'
                    INSERT INTO route_current_state (
                        route_id, public_id, status, name, driver_user_id, vehicle_id, customer_id,
                        total_distance_km, estimated_duration_minutes,
                        total_stops, delivered_stops, exception_stops, pending_stops, skipped_stops,
                        started_at, completed_at, updated_at
                    ) VALUES (
                        :route_id, :public_id, :status, :name, :driver_id, :vehicle_id, :customer_id,
                        :distance, :duration,
                        :total, :delivered, :exceptions, :pending, :skipped,
                        :started_at, :completed_at, NOW()
                    )
                SQL,
                [
                    'route_id' => $route->getId(),
                    'public_id' => $route->getPublicIdString(),
                    'status' => $route->getStatus()->value,
                    'name' => $route->getName(),
                    'driver_id' => $route->getDriver()?->getId(),
                    'vehicle_id' => $route->getVehicle()?->getId(),
                    'customer_id' => $route->getCustomer()?->getId(),
                    'distance' => $route->getTotalDistanceKm(),
                    'duration' => $route->getEstimatedDurationMinutes(),
                    'total' => $total,
                    'delivered' => $delivered,
                    'exceptions' => $exceptions,
                    'pending' => $pending,
                    'skipped' => $skipped,
                    'started_at' => $route->getStartAt()?->format('Y-m-d H:i:s'),
                    'completed_at' => $route->getEndAt()?->format('Y-m-d H:i:s'),
                ],
            );
            $routeCount++;
        }

        return ['routes' => $routeCount, 'stops' => $stopCount];
    }
}
