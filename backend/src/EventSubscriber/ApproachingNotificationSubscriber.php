<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Domain\Event\VehiclePositionReceived;
use App\Entity\RecipientNotification;
use App\Entity\Route;
use App\Entity\RouteStop;
use App\Entity\Vehicle;
use App\Enum\RouteStatus;
use App\Enum\RouteStopStatus;
use App\Notification\RecipientNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class ApproachingNotificationSubscriber implements EventSubscriberInterface
{
    private const float APPROACHING_DISTANCE_METERS = 500.0;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RecipientNotificationService $notificationService,
        private readonly LoggerInterface $logger,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [VehiclePositionReceived::class => 'onPositionReceived'];
    }

    public function onPositionReceived(VehiclePositionReceived $event): void
    {
        $vehicle = $this->em->getRepository(Vehicle::class)->findOneBy([
            'publicId' => $event->vehiclePublicId,
        ]);

        if ($vehicle === null) {
            return;
        }

        $activeRoute = $this->em->getRepository(Route::class)->findOneBy([
            'vehicle' => $vehicle,
            'status' => RouteStatus::ACTIVE,
        ]);

        if ($activeRoute === null) {
            return;
        }

        $nextStop = $this->getNextPendingStop($activeRoute);
        if ($nextStop === null || $nextStop->getLatitude() === null || $nextStop->getLongitude() === null) {
            return;
        }

        $distance = self::haversine(
            $event->latitude,
            $event->longitude,
            $nextStop->getLatitude(),
            $nextStop->getLongitude(),
        );

        if ($distance > self::APPROACHING_DISTANCE_METERS) {
            return;
        }

        // Check if we already sent an approaching notification for this stop's shipment
        $shipment = $nextStop->getShipment();
        if ($shipment === null) {
            return;
        }

        $alreadySent = $this->em->getRepository(RecipientNotification::class)->findOneBy([
            'shipment' => $shipment,
            'templateName' => 'pre_delivery',
        ]);

        if ($alreadySent !== null) {
            return;
        }

        try {
            $this->notificationService->notifyApproaching($nextStop);
            $this->logger->info('Approaching notification sent for stop #{sequence} on route {route}', [
                'sequence' => $nextStop->getSequence(),
                'route' => $activeRoute->getPublicIdString(),
                'distance_m' => round($distance),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send approaching notification: {error}', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function getNextPendingStop(Route $route): ?RouteStop
    {
        return $this->em->createQueryBuilder()
            ->select('s')
            ->from(RouteStop::class, 's')
            ->where('s.route = :route')
            ->andWhere('s.status = :status')
            ->andWhere('s.isOrigin = false')
            ->setParameter('route', $route)
            ->setParameter('status', RouteStopStatus::PENDING)
            ->orderBy('s.sequence', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private static function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6_371_000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
