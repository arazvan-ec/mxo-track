<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Entity\Customer;
use App\Entity\User;
use App\Enum\ClientFrequency;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/customers')]
#[IsGranted('ROLE_ADMIN')]
class CustomerListApiController extends AbstractController
{
    private const int ITEMS_PER_PAGE = 25;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'api_admin_customers_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', self::ITEMS_PER_PAGE)));
        $activeFilter = $request->query->getString('active', '');
        $searchFilter = trim($request->query->getString('search', ''));
        $frequencyFilter = $request->query->getString('frequency', '');

        $qb = $this->em->createQueryBuilder()
            ->select('c')
            ->from(Customer::class, 'c')
            ->orderBy('c.name', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $countQb = $this->em->createQueryBuilder()
            ->select('COUNT(c.id)')
            ->from(Customer::class, 'c');

        if ($activeFilter !== '') {
            $active = $activeFilter === 'true';
            $qb->andWhere('c.isActive = :active')->setParameter('active', $active);
            $countQb->andWhere('c.isActive = :active')->setParameter('active', $active);
        }

        if ($searchFilter !== '') {
            $qb->andWhere('LOWER(c.name) LIKE :search')->setParameter('search', '%' . mb_strtolower($searchFilter) . '%');
            $countQb->andWhere('LOWER(c.name) LIKE :search')->setParameter('search', '%' . mb_strtolower($searchFilter) . '%');
        }

        if ($frequencyFilter !== '') {
            $frequency = ClientFrequency::tryFrom($frequencyFilter);
            if ($frequency !== null) {
                $qb->andWhere('c.frequency = :frequency')->setParameter('frequency', $frequency);
                $countQb->andWhere('c.frequency = :frequency')->setParameter('frequency', $frequency);
            }
        }

        /** @var Customer[] $customers */
        $customers = $qb->getQuery()->getResult();

        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        $totalPages = max(1, (int) ceil($total / $limit));

        // Aggregate user counts + primary emails
        $userCounts = [];
        $customerEmails = [];
        if (\count($customers) > 0) {
            $customerIds = array_map(static fn (Customer $c) => $c->getId(), $customers);

            $userCountRows = $this->em->createQueryBuilder()
                ->select('IDENTITY(u.customer) AS customer_id, COUNT(u.id) AS user_count')
                ->from(User::class, 'u')
                ->where('u.customer IN (:ids)')
                ->setParameter('ids', $customerIds)
                ->groupBy('u.customer')
                ->getQuery()
                ->getArrayResult();

            foreach ($userCountRows as $row) {
                $userCounts[$row['customer_id']] = (int) $row['user_count'];
            }

            $emailRows = $this->em->createQueryBuilder()
                ->select('IDENTITY(u.customer) AS customer_id, MIN(u.email) AS email')
                ->from(User::class, 'u')
                ->where('u.customer IN (:ids)')
                ->andWhere('JSON_TEXT(u.roles) LIKE :role')
                ->setParameter('ids', $customerIds)
                ->setParameter('role', '%ROLE_CUSTOMER%')
                ->groupBy('u.customer')
                ->getQuery()
                ->getArrayResult();

            foreach ($emailRows as $row) {
                $customerEmails[$row['customer_id']] = $row['email'];
            }
        }

        $items = [];
        foreach ($customers as $customer) {
            $items[] = [
                'publicId' => $customer->getPublicIdString(),
                'name' => $customer->getName(),
                'address' => $customer->getAddress(),
                'email' => $customerEmails[$customer->getId()] ?? null,
                'phone' => $customer->getContactPhone(),
                'active' => $customer->isActive(),
                'userCount' => $userCounts[$customer->getId()] ?? 0,
            ];
        }

        return $this->json([
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => $totalPages,
        ]);
    }

    #[Route('/filters', name: 'api_admin_customers_filters', methods: ['GET'])]
    public function filters(): JsonResponse
    {
        $frequencies = array_map(
            fn (ClientFrequency $f) => ['value' => $f->value, 'label' => $f->label()],
            ClientFrequency::cases(),
        );

        return $this->json([
            'frequencies' => $frequencies,
        ]);
    }
}
