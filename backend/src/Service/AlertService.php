<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Route\Model\Route;
use App\Entity\Vehicle;
use App\Entity\VehicleLastPosition;
use App\Enum\RouteStopStatus;
use Doctrine\ORM\EntityManagerInterface;

final class AlertService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    public function checkVehicleOffline(Vehicle $vehicle, int $thresholdMinutes = 30): bool
    {
        $lastPosition = $this->em->getRepository(VehicleLastPosition::class)->findOneBy([
            'vehicle' => $vehicle,
        ]);

        if ($lastPosition === null) {
            return true;
        }

        $diff = (new \DateTimeImmutable())->getTimestamp() - $lastPosition->getDeviceTime()->getTimestamp();

        return $diff > ($thresholdMinutes * 60);
    }

    public function checkExcessiveExceptions(Route $route, int $threshold = 3): bool
    {
        $count = $this->em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM route_stop WHERE route_id = :rid AND status = :status',
            ['rid' => $route->getId(), 'status' => RouteStopStatus::EXCEPTION->value]
        );

        return (int) $count >= $threshold;
    }

    /**
     * @return array<array{vehicle: Vehicle, minutesOffline: int}>
     */
    public function getOfflineVehicles(int $thresholdMinutes = 30): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('v', 'vlp')
            ->from(Vehicle::class, 'v')
            ->leftJoin(VehicleLastPosition::class, 'vlp', 'WITH', 'vlp.vehicle = v')
            ->where('v.isActive = true')
            ->getQuery()
            ->getResult();

        $offline = [];
        $now = new \DateTimeImmutable();

        foreach ($rows as $row) {
            /** @var Vehicle $vehicle */
            $vehicle = $row instanceof Vehicle ? $row : $row[0];
            $lastPosition = $row instanceof Vehicle ? null : ($row[1] ?? null);

            if (!$lastPosition instanceof VehicleLastPosition) {
                $offline[] = ['vehicle' => $vehicle, 'minutesOffline' => -1];
                continue;
            }

            $diffMinutes = (int) (($now->getTimestamp() - $lastPosition->getDeviceTime()->getTimestamp()) / 60);
            if ($diffMinutes >= $thresholdMinutes) {
                $offline[] = ['vehicle' => $vehicle, 'minutesOffline' => $diffMinutes];
            }
        }

        return $offline;
    }
}
