<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Domain\Shipment\Model\Shipment;
use App\Entity\Customer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/shipments')]
#[IsGranted('ROLE_ADMIN')]
class ShipmentListApiController extends AbstractController
{
    private const int ITEMS_PER_PAGE = 25;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'api_admin_shipments_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', self::ITEMS_PER_PAGE)));
        $customerFilter = $request->query->getString('customer', '');

        $qb = $this->em->createQueryBuilder()
            ->select('s', 'c')
            ->from(Shipment::class, 's')
            ->join('s.customer', 'c')
            ->where('s.deletedAt IS NULL')
            ->orderBy('s.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $countQb = $this->em->createQueryBuilder()
            ->select('COUNT(s.id)')
            ->from(Shipment::class, 's')
            ->where('s.deletedAt IS NULL');

        if ($customerFilter !== '') {
            $customer = $this->em->getRepository(Customer::class)->findOneBy(['publicId' => $customerFilter]);
            if ($customer !== null) {
                $qb->andWhere('s.customer = :customer')->setParameter('customer', $customer);
                $countQb->andWhere('s.customer = :customer')->setParameter('customer', $customer);
            }
        }

        /** @var Shipment[] $shipments */
        $shipments = $qb->getQuery()->getResult();
        $total = (int) $countQb->getQuery()->getSingleScalarResult();
        $totalPages = max(1, (int) ceil($total / $limit));

        $items = [];
        foreach ($shipments as $shipment) {
            $items[] = [
                'publicId' => $shipment->getPublicIdString(),
                'reference' => $shipment->getReference(),
                'customerName' => $shipment->getCustomer()->getName(),
                'recipientName' => $shipment->getRecipientName() ?? '-',
                'address' => $shipment->getAddress() ?? '-',
                'priority' => $shipment->getPriority()->name,
                'totalWeightKg' => $shipment->getTotalWeightKg(),
                'totalVolumeM3' => $shipment->getTotalVolumeM3(),
                'totalParcels' => $shipment->getTotalParcels(),
                'createdAt' => $shipment->getCreatedAt()->format('c'),
            ];
        }

        return $this->json([
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => $totalPages,
        ]);
    }

    #[Route('/filters', name: 'api_admin_shipments_filters', methods: ['GET'])]
    public function filters(): JsonResponse
    {
        $customers = $this->em->createQueryBuilder()
            ->select('c')
            ->from(Customer::class, 'c')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->json([
            'customers' => array_map(fn (Customer $c) => [
                'publicId' => $c->getPublicIdString(),
                'name' => $c->getName(),
            ], $customers),
        ]);
    }
}
