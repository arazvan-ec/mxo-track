<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Event\VehiclePositionReceived;
use App\Domain\Route\Model\Route;
use App\Entity\Vehicle;
use App\Entity\VehicleCheckpoint;
use App\Entity\VehicleLastPosition;
use App\Entity\VehiclePosition;
use App\Enum\RouteStatus;
use App\Tracking\DevicePosition;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use App\Realtime\RealtimePublisherInterface;
use App\Realtime\SseMessage;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;

final class TraccarIngestionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RealtimePublisherInterface $publisher,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /** @param list<DevicePosition> $positions */
    public function ingestForVehicle(Vehicle $vehicle, array $positions): int
    {
        $created = 0;

        $activeRoute = $this->entityManager->getRepository(Route::class)->findOneBy([
            'vehicle' => $vehicle,
            'status' => RouteStatus::ACTIVE,
        ]);

        $last = $this->entityManager->getRepository(VehicleLastPosition::class)->findOneBy(['vehicle' => $vehicle]);
        $checkpoint = $this->entityManager->getRepository(VehicleCheckpoint::class)->findOneBy(['vehicle' => $vehicle]);

        foreach ($positions as $position) {
            $deviceTime = $position->deviceTime;
            $serverTime = $position->serverTime;
            $lat = $position->latitude;
            $lng = $position->longitude;

            $exists = $this->entityManager->getRepository(VehiclePosition::class)->findOneBy([
                'vehicle' => $vehicle,
                'deviceTime' => $deviceTime,
            ]);
            if ($exists !== null) {
                continue;
            }

            $history = new VehiclePosition($vehicle, $lat, $lng, $deviceTime, $serverTime);
            if ($activeRoute !== null) {
                $history->setRoute($activeRoute);
            }
            $this->entityManager->persist($history);
            $created++;

            $speed = $position->speed;
            $course = $position->course;
            $accuracy = $position->accuracy;

            if ($last === null) {
                $last = VehicleLastPosition::fromTelemetry($vehicle, $lat, $lng, $speed, $course, $accuracy, $deviceTime, $serverTime);
                $this->entityManager->persist($last);
            } else {
                $last->refresh($lat, $lng, $speed, $course, $accuracy, $deviceTime, $serverTime);
            }

            if ($checkpoint === null) {
                $checkpoint = new VehicleCheckpoint($vehicle);
                $this->entityManager->persist($checkpoint);
            }
            $checkpoint->setLastDeviceTime($deviceTime);
            $checkpoint->setLastTraccarPositionId($position->rawId);

            try {
                $this->entityManager->flush();
            } catch (UniqueConstraintViolationException) {
                $this->entityManager->clear();
                $last = null;
                $checkpoint = null;
                continue;
            }

            try {
                $this->publisher->publish(new SseMessage(
                    data: [
                        'vehicleId' => $vehicle->getPublicIdString(),
                        'lat' => $lat,
                        'lng' => $lng,
                        'speed' => $speed,
                        'course' => $course,
                        'accuracy' => $accuracy,
                        'deviceTime' => $deviceTime->format(DATE_ATOM),
                        'receivedAt' => $serverTime->format(DATE_ATOM),
                    ],
                    topics: [sprintf('/vehicles/%s/position', $vehicle->getPublicIdString())],
                ));
            } catch (Throwable) {
                // no romper ingesta por fallo temporal de Mercure
            }

            $this->eventDispatcher->dispatch(new VehiclePositionReceived(
                vehiclePublicId: $vehicle->getPublicIdString(),
                latitude: $lat,
                longitude: $lng,
                speed: $speed,
                course: $course,
                deviceTime: $deviceTime,
            ));
        }

        return $created;
    }
}
