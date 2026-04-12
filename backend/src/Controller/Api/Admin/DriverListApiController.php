<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Entity\User;
use App\Service\Admin\FilterDefinition;
use App\Service\Admin\ListFilterApplier;
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
        private readonly ListFilterApplier $filterApplier,
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

        $this->filterApplier->apply($qb, $countQb, [
            FilterDefinition::boolean('u.isActive', 'active', $activeFilter),
            FilterDefinition::dateFrom('u.createdAt', 'dateFrom', $dateFrom),
            FilterDefinition::dateTo('u.createdAt', 'dateTo', $dateTo),
        ]);

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
