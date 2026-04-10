<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Entity\Vehicle;
use App\Entity\VehicleLastPosition;
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

        if ($activeFilter !== '') {
            $active = $activeFilter === 'true';
            $qb->andWhere('v.isActive = :active')->setParameter('active', $active);
            $countQb->andWhere('v.isActive = :active')->setParameter('active', $active);
        }

        if ($dateFrom !== '') {
            try {
                $from = new \DateTimeImmutable($dateFrom . ' 00:00:00');
                $qb->andWhere('v.createdAt >= :dateFrom')->setParameter('dateFrom', $from);
                $countQb->andWhere('v.createdAt >= :dateFrom')->setParameter('dateFrom', $from);
            } catch (\Exception) {}
        }

        if ($dateTo !== '') {
            try {
                $to = new \DateTimeImmutable($dateTo . ' 23:59:59');
                $qb->andWhere('v.createdAt <= :dateTo')->setParameter('dateTo', $to);
                $countQb->andWhere('v.createdAt <= :dateTo')->setParameter('dateTo', $to);
            } catch (\Exception) {}
        }

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
