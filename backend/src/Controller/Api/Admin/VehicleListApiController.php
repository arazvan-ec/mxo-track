<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Entity\Vehicle;
use App\Entity\VehicleLastPosition;
use App\Service\Admin\FilterDefinition;
use App\Service\Admin\ListFilterApplier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/vehicles')]
#[IsGranted('ROLE_ADMIN')]
class VehicleListApiController extends AbstractController
{
    private const int ITEMS_PER_PAGE = 25;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ListFilterApplier $filterApplier,
    ) {}

    #[Route('', name: 'api_admin_vehicles_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', self::ITEMS_PER_PAGE)));
        $activeFilter = $request->query->getString('active', '');
        $dateFrom = $request->query->getString('date_from', '');
        $dateTo = $request->query->getString('date_to', '');

        $qb = $this->em->createQueryBuilder()
            ->select('v')
            ->from(Vehicle::class, 'v')
            ->orderBy('v.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $countQb = $this->em->createQueryBuilder()
            ->select('COUNT(v.id)')
            ->from(Vehicle::class, 'v');

        $this->filterApplier->apply($qb, $countQb, [
            FilterDefinition::boolean('v.isActive', 'active', $activeFilter),
            FilterDefinition::dateFrom('v.createdAt', 'dateFrom', $dateFrom),
            FilterDefinition::dateTo('v.createdAt', 'dateTo', $dateTo),
        ]);

        /** @var Vehicle[] $vehicles */
        $vehicles = $qb->getQuery()->getResult();

        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        $totalPages = max(1, (int) ceil($total / $limit));

        // Fetch last positions
        $lastPositions = [];
        if (\count($vehicles) > 0) {
            $positions = $this->em->getRepository(VehicleLastPosition::class)->findBy([
                'vehicle' => $vehicles,
            ]);
            foreach ($positions as $pos) {
                $lastPositions[$pos->getVehicle()->getId()] = $pos;
            }
        }

        $items = [];
        foreach ($vehicles as $vehicle) {
            $pos = $lastPositions[$vehicle->getId()] ?? null;
            $items[] = [
                'publicId' => $vehicle->getPublicIdString(),
                'name' => $vehicle->getName(),
                'traccarDeviceId' => $vehicle->getTraccarDeviceId(),
                'active' => $vehicle->isActive(),
                'maxWeightKg' => $vehicle->getMaxWeightKg(),
                'maxVolumeM3' => $vehicle->getMaxVolumeM3(),
                'maxParcels' => $vehicle->getMaxParcels(),
                'lastPosition' => $pos ? [
                    'lat' => $pos->getLat(),
                    'lng' => $pos->getLng(),
                ] : null,
                'createdAt' => $vehicle->getCreatedAt()->format('c'),
            ];
        }

        return $this->json([
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => $totalPages,
        ]);
    }
}
