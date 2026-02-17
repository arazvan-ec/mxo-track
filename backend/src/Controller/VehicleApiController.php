<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Entity\Vehicle;
use App\Entity\VehicleLastPosition;
use App\Entity\VehiclePosition;
use App\Http\ApiErrorResponder;
use App\Service\VisibilityScopeService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

class VehicleApiController extends AbstractController
{
    #[Route('/api/vehicles', name: 'api_vehicles', methods: ['GET'])]
    public function list(
        EntityManagerInterface $entityManager,
        VisibilityScopeService $visibilityScopeService,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        $repo = $entityManager->getRepository(Vehicle::class);
        if ($user->hasRole('ROLE_ADMIN') || $user->hasRole('ROLE_OPERATOR')) {
            $vehicles = $repo->findBy(['isActive' => true]);
        } else {
            $allowedIds = $visibilityScopeService->vehicleIdsFor($user);
            $vehicles = $allowedIds === []
                ? []
                : $repo->findBy(['id' => $allowedIds, 'isActive' => true]);
        }

        $items = [];
        foreach ($vehicles as $vehicle) {
            $last = $entityManager->getRepository(VehicleLastPosition::class)->findOneBy(['vehicle' => $vehicle]);
            $items[] = [
                'id' => (string) $vehicle->getId(),
                'name' => $vehicle->getName(),
                'traccar_device_id' => $vehicle->getTraccarDeviceId(),
                'last_position' => $last === null ? null : [
                    'lat' => $last->getLat(),
                    'lng' => $last->getLng(),
                    'speed' => $last->getSpeed(),
                    'course' => $last->getCourse(),
                    'accuracy' => $last->getAccuracy(),
                    'device_time' => $last->getDeviceTime()->format(DATE_ATOM),
                    'server_time' => $last->getServerTime()->format(DATE_ATOM),
                ],
            ];
        }

        return $this->json(['items' => $items]);
    }

    #[Route('/api/vehicles/{id}/last-position', name: 'api_vehicle_last', methods: ['GET'])]
    public function lastPosition(
        string $id,
        EntityManagerInterface $entityManager,
        VisibilityScopeService $visibilityScopeService,
        ApiErrorResponder $errorResponder,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        if (!$visibilityScopeService->canAccessVehicle($user, $id)) {
            return $errorResponder->notFound('vehicle_not_found', 'Vehículo no encontrado o no permitido.');
        }

        $vehicle = $entityManager->find(Vehicle::class, $id);
        if (!$vehicle instanceof Vehicle) {
            return $errorResponder->notFound('vehicle_not_found', 'Vehículo no encontrado.');
        }

        $last = $entityManager->getRepository(VehicleLastPosition::class)->findOneBy(['vehicle' => $vehicle]);

        return $this->json([
            'vehicle_id' => (string) $vehicle->getId(),
            'last_position' => $last === null ? null : [
                'lat' => $last->getLat(),
                'lng' => $last->getLng(),
                'speed' => $last->getSpeed(),
                'course' => $last->getCourse(),
                'accuracy' => $last->getAccuracy(),
                'device_time' => $last->getDeviceTime()->format(DATE_ATOM),
                'server_time' => $last->getServerTime()->format(DATE_ATOM),
            ],
        ]);
    }

    #[Route('/api/vehicles/{id}/positions', name: 'api_vehicle_positions', methods: ['GET'])]
    public function positions(
        string $id,
        Request $request,
        EntityManagerInterface $entityManager,
        VisibilityScopeService $visibilityScopeService,
        ApiErrorResponder $errorResponder,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        if (!$visibilityScopeService->canAccessVehicle($user, $id)) {
            return $errorResponder->notFound('vehicle_not_found', 'Vehículo no encontrado o no permitido.');
        }

        $vehicle = $entityManager->find(Vehicle::class, $id);
        if (!$vehicle instanceof Vehicle) {
            return $errorResponder->notFound('vehicle_not_found', 'Vehículo no encontrado.');
        }

        $from = $this->parseDate((string) $request->query->get('from', ''));
        $to = $this->parseDate((string) $request->query->get('to', ''));

        $limit = max(1, min(2000, (int) $request->query->get('limit', 500)));
        $offset = max(0, (int) $request->query->get('offset', 0));
        $order = strtoupper((string) $request->query->get('order', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $rows = $entityManager->getRepository(VehiclePosition::class)->findBy(
            ['vehicle' => $vehicle],
            ['deviceTime' => $order],
            $limit,
            $offset,
        );

        $items = [];
        foreach ($rows as $row) {
            $deviceTime = $row->getDeviceTime();
            if ($from !== null && $deviceTime < $from) {
                continue;
            }
            if ($to !== null && $deviceTime > $to) {
                continue;
            }

            $items[] = [
                'lat' => $row->getLat(),
                'lng' => $row->getLng(),
                'speed' => $row->getSpeed(),
                'course' => $row->getCourse(),
                'accuracy' => $row->getAccuracy(),
                'device_time' => $row->getDeviceTime()->format(DATE_ATOM),
                'server_time' => $row->getServerTime()->format(DATE_ATOM),
            ];
        }

        return $this->json([
            'vehicle_id' => $id,
            'paging' => [
                'limit' => $limit,
                'offset' => $offset,
                'order' => $order,
            ],
            'items' => $items,
        ]);
    }


    #[Route('/api/vehicles/{id}/positions.csv', name: 'api_vehicle_positions_csv', methods: ['GET'])]
    public function positionsExportCsv(
        string $id,
        Request $request,
        EntityManagerInterface $entityManager,
        VisibilityScopeService $visibilityScopeService,
        ApiErrorResponder $errorResponder,
    ): JsonResponse|StreamedResponse {
        /** @var User $user */
        $user = $this->getUser();

        if (!$visibilityScopeService->canAccessVehicle($user, $id)) {
            return $errorResponder->notFound('vehicle_not_found', 'Vehículo no encontrado o no permitido.');
        }

        $vehicle = $entityManager->find(Vehicle::class, $id);
        if (!$vehicle instanceof Vehicle) {
            return $errorResponder->notFound('vehicle_not_found', 'Vehículo no encontrado.');
        }

        $from = $this->parseDate((string) $request->query->get('from', ''));
        $to = $this->parseDate((string) $request->query->get('to', ''));
        $limit = max(1, min(5000, (int) $request->query->get('limit', 2000)));

        $rows = $entityManager->getRepository(VehiclePosition::class)->findBy(['vehicle' => $vehicle], ['deviceTime' => 'DESC'], $limit);

        $response = new StreamedResponse(function () use ($rows, $from, $to): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, ['device_time', 'server_time', 'lat', 'lng', 'speed', 'course', 'accuracy']);

            foreach ($rows as $row) {
                $deviceTime = $row->getDeviceTime();
                if ($from !== null && $deviceTime < $from) {
                    continue;
                }
                if ($to !== null && $deviceTime > $to) {
                    continue;
                }

                fputcsv($out, [
                    $row->getDeviceTime()->format(DATE_ATOM),
                    $row->getServerTime()->format(DATE_ATOM),
                    $row->getLat(),
                    $row->getLng(),
                    $row->getSpeed(),
                    $row->getCourse(),
                    $row->getAccuracy(),
                ]);
            }
            fclose($out);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="vehicle-%s-positions.csv"', $id));

        return $response;
    }

    private function parseDate(string $value): ?DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
