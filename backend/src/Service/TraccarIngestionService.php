<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Route;
use App\Entity\Vehicle;
use App\Entity\VehicleCheckpoint;
use App\Entity\VehicleLastPosition;
use App\Entity\VehiclePosition;
use App\Enum\RouteStatus;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Throwable;

final class TraccarIngestionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly HubInterface $hub,
    ) {
    }

    /** @param list<array<string,mixed>> $positions */
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
            $deviceTime = new DateTimeImmutable((string) ($position['deviceTime'] ?? 'now'));
            $serverTime = new DateTimeImmutable((string) ($position['serverTime'] ?? 'now'));
            $lat = (float) ($position['latitude'] ?? 0.0);
            $lng = (float) ($position['longitude'] ?? 0.0);

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

            $speed = (float) ($position['speed'] ?? 0.0);
            $course = (float) ($position['course'] ?? 0.0);
            $accuracy = (float) ($position['accuracy'] ?? 0.0);

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
            $checkpoint->setLastTraccarPositionId(isset($position['id']) ? (int) $position['id'] : null);

            try {
                $this->entityManager->flush();
            } catch (UniqueConstraintViolationException) {
                $this->entityManager->clear();
                $last = null;
                $checkpoint = null;
                continue;
            }

            try {
                $this->hub->publish(new Update(
                    sprintf('/vehicles/%s/position', $vehicle->getPublicIdString()),
                    json_encode([
                        'vehicleId' => $vehicle->getPublicIdString(),
                        'lat' => $lat,
                        'lng' => $lng,
                        'speed' => $speed,
                        'course' => $course,
                        'accuracy' => $accuracy,
                        'deviceTime' => $deviceTime->format(DATE_ATOM),
                        'receivedAt' => $serverTime->format(DATE_ATOM),
                    ], JSON_THROW_ON_ERROR),
                ));
            } catch (Throwable) {
                // no romper ingesta por fallo temporal de Mercure
            }
        }

        return $created;
    }
}
