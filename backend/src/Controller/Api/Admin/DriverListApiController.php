<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/drivers')]
#[IsGranted('ROLE_ADMIN')]
class DriverListApiController extends AbstractController
{
    private const int ITEMS_PER_PAGE = 25;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'api_admin_drivers_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', self::ITEMS_PER_PAGE)));
        $activeFilter = $request->query->getString('active', '');
        $dateFrom = $request->query->getString('date_from', '');
        $dateTo = $request->query->getString('date_to', '');

        $qb = $this->em->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->where('JSON_TEXT(u.roles) LIKE :role')
            ->setParameter('role', '%ROLE_DRIVER%')
            ->orderBy('u.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $countQb = $this->em->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(User::class, 'u')
            ->where('JSON_TEXT(u.roles) LIKE :role')
            ->setParameter('role', '%ROLE_DRIVER%');

        if ($activeFilter !== '') {
            $active = $activeFilter === 'true';
            $qb->andWhere('u.isActive = :active')->setParameter('active', $active);
            $countQb->andWhere('u.isActive = :active')->setParameter('active', $active);
        }

        if ($dateFrom !== '') {
            try {
                $from = new \DateTimeImmutable($dateFrom . ' 00:00:00');
                $qb->andWhere('u.createdAt >= :dateFrom')->setParameter('dateFrom', $from);
                $countQb->andWhere('u.createdAt >= :dateFrom')->setParameter('dateFrom', $from);
            } catch (\Exception) {}
        }

        if ($dateTo !== '') {
            try {
                $to = new \DateTimeImmutable($dateTo . ' 23:59:59');
                $qb->andWhere('u.createdAt <= :dateTo')->setParameter('dateTo', $to);
                $countQb->andWhere('u.createdAt <= :dateTo')->setParameter('dateTo', $to);
            } catch (\Exception) {}
        }

        /** @var User[] $drivers */
        $drivers = $qb->getQuery()->getResult();

        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        $totalPages = max(1, (int) ceil($total / $limit));

        $items = [];
        foreach ($drivers as $driver) {
            $items[] = [
                'publicId' => $driver->getPublicIdString(),
                'email' => $driver->getEmail(),
                'name' => $driver->getName(),
                'active' => $driver->isActive(),
                'createdAt' => $driver->getCreatedAt()->format('c'),
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
