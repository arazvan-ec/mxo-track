<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\DriverAvailabilityService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/drivers')]
#[IsGranted('ROLE_ADMIN')]
class DriverAvailabilityController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly DriverAvailabilityService $availabilityService,
    ) {}

    #[Route('/{publicId}/availability', name: 'admin_drivers_availability', methods: ['GET'])]
    public function show(string $publicId): Response
    {
        $driver = $this->findDriverOrFail($publicId);
        $schedule = $this->availabilityService->getScheduleForDriver($driver);

        $scheduleJson = $this->buildScheduleJson($schedule);

        return $this->render('admin/driver/availability.html.twig', [
            'driver' => $driver,
            'schedule' => $schedule,
            'scheduleJson' => json_encode($scheduleJson, JSON_THROW_ON_ERROR),
            'days' => self::dayNames(),
        ]);
    }

    #[Route('/{publicId}/availability', name: 'admin_drivers_availability_save', methods: ['POST'])]
    public function save(string $publicId, Request $request): Response
    {
        $driver = $this->findDriverOrFail($publicId);

        if (!$this->isCsrfTokenValid('availability-' . $publicId, $request->request->getString('_token'))) {
            $this->addFlash('error', 'Token CSRF invalido.');

            return $this->redirectToRoute('admin_drivers_availability', ['publicId' => $publicId]);
        }

        /** @var array<int, array<string, mixed>> $slots */
        $slots = $request->request->all('slots');
        $schedule = [];

        foreach ($slots as $slot) {
            $dayOfWeek = (int) ($slot['dayOfWeek'] ?? 0);
            $startTime = trim((string) ($slot['startTime'] ?? ''));
            $endTime = trim((string) ($slot['endTime'] ?? ''));
            $isAvailable = isset($slot['isAvailable']);
            $notes = trim((string) ($slot['notes'] ?? '')) ?: null;

            if ($startTime === '' || $endTime === '') {
                continue;
            }

            if (!preg_match('/^\d{2}:\d{2}$/', $startTime) || !preg_match('/^\d{2}:\d{2}$/', $endTime)) {
                continue;
            }

            $schedule[] = [
                'dayOfWeek' => $dayOfWeek,
                'startTime' => $startTime,
                'endTime' => $endTime,
                'isAvailable' => $isAvailable,
                'notes' => $notes,
            ];
        }

        $this->availabilityService->setWeeklySchedule($driver, $schedule);

        $this->addFlash('success', 'Horario actualizado correctamente.');

        return $this->redirectToRoute('admin_drivers_availability', ['publicId' => $publicId]);
    }

    private function findDriverOrFail(string $publicId): User
    {
        $driver = $this->userRepository->findOneByPublicId($publicId);

        if (!$driver instanceof User || !$driver->hasRole('ROLE_DRIVER')) {
            throw $this->createNotFoundException('Conductor no encontrado.');
        }

        return $driver;
    }

    /**
     * Converts the schedule array into a JSON-safe format for the frontend.
     *
     * @param array<int, \App\Entity\DriverAvailability[]> $schedule
     * @return array<int, array{_key: int, dayOfWeek: int, startTime: string, endTime: string, isAvailable: bool, notes: string}>
     */
    private function buildScheduleJson(array $schedule): array
    {
        $result = [];
        $key = 0;

        foreach ($schedule as $day => $entries) {
            foreach ($entries as $entry) {
                $result[] = [
                    '_key' => $key++,
                    'dayOfWeek' => $entry->getDayOfWeek(),
                    'startTime' => $entry->getStartTime(),
                    'endTime' => $entry->getEndTime(),
                    'isAvailable' => $entry->isAvailable(),
                    'notes' => $entry->getNotes() ?? '',
                ];
            }
        }

        return $result;
    }

    /**
     * @return array<int, string>
     */
    private static function dayNames(): array
    {
        return [
            0 => 'Lunes',
            1 => 'Martes',
            2 => 'Miercoles',
            3 => 'Jueves',
            4 => 'Viernes',
            5 => 'Sabado',
            6 => 'Domingo',
        ];
    }
}
