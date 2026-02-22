<?php

declare(strict_types=1);

namespace App\Controller\Customer;

use App\Entity\Customer;
use App\Entity\Shipment;
use App\Entity\ShipmentEvent;
use App\Enum\ShipmentEventType;
use App\Repository\ShipmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/customer/shipments')]
#[IsGranted('ROLE_CUSTOMER')]
class CustomerShipmentController extends AbstractController
{
    private const int ITEMS_PER_PAGE = 20;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ShipmentRepository $shipmentRepository,
    ) {}

    #[Route('', name: 'customer_shipments_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $customer = $this->getUser()->getCustomer();

        if (!$customer instanceof Customer) {
            throw $this->createAccessDeniedException('No tiene un cliente asociado.');
        }

        $page = max(1, $request->query->getInt('page', 1));
        $limit = self::ITEMS_PER_PAGE;
        $searchQuery = $request->query->getString('q', '');
        $statusFilter = $request->query->getString('status', '');
        $dateFrom = $request->query->getString('date_from', '');
        $dateTo = $request->query->getString('date_to', '');

        // Shipment implements CustomerScopedEntityInterface, so tenant filter is auto-applied
        $qb = $this->em->createQueryBuilder()
            ->select('s')
            ->from(Shipment::class, 's')
            ->orderBy('s.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $countQb = $this->em->createQueryBuilder()
            ->select('COUNT(s.id)')
            ->from(Shipment::class, 's');

        // Search filter
        if ($searchQuery !== '') {
            $pattern = '%' . mb_strtolower($searchQuery) . '%';
            $qb->andWhere('LOWER(s.reference) LIKE :search OR LOWER(s.recipientName) LIKE :search')
                ->setParameter('search', $pattern);
            $countQb->andWhere('LOWER(s.reference) LIKE :search OR LOWER(s.recipientName) LIKE :search')
                ->setParameter('search', $pattern);
        }

        // Date range filters
        if ($dateFrom !== '') {
            try {
                $from = new \DateTimeImmutable($dateFrom . ' 00:00:00');
                $qb->andWhere('s.createdAt >= :dateFrom')->setParameter('dateFrom', $from);
                $countQb->andWhere('s.createdAt >= :dateFrom')->setParameter('dateFrom', $from);
            } catch (\Exception) {
                // ignore invalid date
            }
        }

        if ($dateTo !== '') {
            try {
                $to = new \DateTimeImmutable($dateTo . ' 23:59:59');
                $qb->andWhere('s.createdAt <= :dateTo')->setParameter('dateTo', $to);
                $countQb->andWhere('s.createdAt <= :dateTo')->setParameter('dateTo', $to);
            } catch (\Exception) {
                // ignore invalid date
            }
        }

        // Status filter (via subquery on latest event)
        if ($statusFilter !== '' && ShipmentEventType::tryFrom($statusFilter) !== null) {
            $qb->andWhere(
                $qb->expr()->exists(
                    'SELECT 1 FROM ' . ShipmentEvent::class . ' se_filter WHERE se_filter.shipment = s AND se_filter.eventType = :statusFilter AND se_filter.createdAt = (SELECT MAX(se_sub.createdAt) FROM ' . ShipmentEvent::class . ' se_sub WHERE se_sub.shipment = s)'
                )
            )->setParameter('statusFilter', $statusFilter);

            $countQb->andWhere(
                $countQb->expr()->exists(
                    'SELECT 1 FROM ' . ShipmentEvent::class . ' se_filter2 WHERE se_filter2.shipment = s AND se_filter2.eventType = :statusFilter AND se_filter2.createdAt = (SELECT MAX(se_sub2.createdAt) FROM ' . ShipmentEvent::class . ' se_sub2 WHERE se_sub2.shipment = s)'
                )
            )->setParameter('statusFilter', $statusFilter);
        }

        $shipments = $qb->getQuery()->getResult();

        $total = (int) $countQb->getQuery()->getSingleScalarResult();
        $totalPages = max(1, (int) ceil($total / $limit));

        // Fetch latest event for each shipment
        $latestEvents = [];
        if (\count($shipments) > 0) {
            $rows = $this->em->createQueryBuilder()
                ->select('IDENTITY(se.shipment) as shipmentId, se.eventType, se.createdAt')
                ->from(ShipmentEvent::class, 'se')
                ->where('se.shipment IN (:shipments)')
                ->setParameter('shipments', $shipments)
                ->orderBy('se.createdAt', 'DESC')
                ->getQuery()
                ->getResult();

            // Keep only the most recent event per shipment
            foreach ($rows as $row) {
                $sid = $row['shipmentId'];
                if (!isset($latestEvents[$sid])) {
                    $latestEvents[$sid] = $row['eventType'];
                }
            }
        }

        // Build filter params for pagination links
        $filterParams = array_filter([
            'q' => $searchQuery,
            'status' => $statusFilter,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ], fn(string $v): bool => $v !== '');

        return $this->render('customer/shipment/index.html.twig', [
            'shipments' => $shipments,
            'latestEvents' => $latestEvents,
            'page' => $page,
            'totalPages' => $totalPages,
            'searchQuery' => $searchQuery,
            'statusFilter' => $statusFilter,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'filterParams' => $filterParams,
        ]);
    }

    #[Route('/{publicId}', name: 'customer_shipments_show', methods: ['GET'])]
    public function show(string $publicId): Response
    {
        $customer = $this->getUser()->getCustomer();

        if (!$customer instanceof Customer) {
            throw $this->createAccessDeniedException('No tiene un cliente asociado.');
        }

        // Auto-filtered by CustomerTenantFilter — only this customer's shipments
        $shipment = $this->shipmentRepository->findOneByPublicId($publicId);

        if (!$shipment instanceof Shipment) {
            throw $this->createNotFoundException('Envio no encontrado.');
        }

        // Load all events ordered by createdAt ASC
        $events = $this->em->createQueryBuilder()
            ->select('se')
            ->from(ShipmentEvent::class, 'se')
            ->where('se.shipment = :shipment')
            ->setParameter('shipment', $shipment)
            ->orderBy('se.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('customer/shipment/show.html.twig', [
            'shipment' => $shipment,
            'events' => $events,
        ]);
    }
}
