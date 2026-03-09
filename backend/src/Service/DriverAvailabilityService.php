<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\DriverAvailability;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class DriverAvailabilityService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Returns drivers available on a given date and optionally at a specific time.
     *
     * @return User[]
     */
    public function getAvailableDrivers(\DateTimeInterface $date, ?string $startTime = null): array
    {
        // PHP: 1 (Monday) .. 7 (Sunday) → entity uses 0 (Monday) .. 6 (Sunday)
        $dayOfWeek = ((int) $date->format('N')) - 1;

        $qb = $this->em->createQueryBuilder()
            ->select('DISTINCT IDENTITY(da.driver)')
            ->from(DriverAvailability::class, 'da')
            ->where('da.dayOfWeek = :day')
            ->andWhere('da.isAvailable = true')
            ->setParameter('day', $dayOfWeek);

        if ($startTime !== null) {
            $qb->andWhere('da.startTime <= :time')
               ->andWhere('da.endTime > :time')
               ->setParameter('time', $startTime);
        }

        $driverIds = array_column($qb->getQuery()->getScalarResult(), 1);

        if (empty($driverIds)) {
            return [];
        }

        return $this->em->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->where('u.id IN (:ids)')
            ->andWhere('u.isActive = true')
            ->setParameter('ids', $driverIds)
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns the weekly schedule for a driver, indexed by day of week (0-6).
     *
     * @return array<int, DriverAvailability[]>
     */
    public function getScheduleForDriver(User $driver): array
    {
        /** @var DriverAvailability[] $entries */
        $entries = $this->em->createQueryBuilder()
            ->select('da')
            ->from(DriverAvailability::class, 'da')
            ->where('da.driver = :driver')
            ->orderBy('da.dayOfWeek', 'ASC')
            ->addOrderBy('da.startTime', 'ASC')
            ->setParameter('driver', $driver)
            ->getQuery()
            ->getResult();

        $schedule = [];
        for ($d = 0; $d <= 6; $d++) {
            $schedule[$d] = [];
        }

        foreach ($entries as $entry) {
            $schedule[$entry->getDayOfWeek()][] = $entry;
        }

        return $schedule;
    }

    /**
     * Replaces the entire weekly schedule for a driver.
     *
     * Each element in $schedule should be an array with keys:
     *   dayOfWeek (int 0-6), startTime (string HH:MM), endTime (string HH:MM),
     *   isAvailable (bool), notes (?string)
     *
     * @param array<array{dayOfWeek: int, startTime: string, endTime: string, isAvailable?: bool, notes?: ?string}> $schedule
     */
    public function setWeeklySchedule(User $driver, array $schedule): void
    {
        // Remove existing schedule
        $this->em->createQueryBuilder()
            ->delete(DriverAvailability::class, 'da')
            ->where('da.driver = :driver')
            ->setParameter('driver', $driver)
            ->getQuery()
            ->execute();

        foreach ($schedule as $slot) {
            $entry = new DriverAvailability(
                $driver,
                $slot['dayOfWeek'],
                $slot['startTime'],
                $slot['endTime'],
            );
            $entry->setAvailable($slot['isAvailable'] ?? true);
            $entry->setNotes($slot['notes'] ?? null);

            $this->em->persist($entry);
        }

        $this->em->flush();
    }
}
