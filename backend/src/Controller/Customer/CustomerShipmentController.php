<?php

declare(strict_types=1);

namespace App\Controller\Customer;

use App\Entity\Customer;
use App\Entity\Shipment;
use App\Entity\ShipmentEvent;
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
            throw $this->createAccessDeniedException('No tiene un almacen asociado.');
        }

        $page = max(1, $request->query->getInt('page', 1));
        $limit = self::ITEMS_PER_PAGE;

        // Shipment implements CustomerScopedEntityInterface, so tenant filter is auto-applied
        $qb = $this->em->createQueryBuilder()
            ->select('s')
            ->from(Shipment::class, 's')
            ->orderBy('s.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        $shipments = $qb->getQuery()->getResult();

        // Count query
        $total = (int) $this->em->createQueryBuilder()
            ->select('COUNT(s.id)')
            ->from(Shipment::class, 's')
            ->getQuery()
            ->getSingleScalarResult();

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

        return $this->render('customer/shipment/index.html.twig', [
            'shipments' => $shipments,
            'latestEvents' => $latestEvents,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    #[Route('/{publicId}', name: 'customer_shipments_show', methods: ['GET'])]
    public function show(string $publicId): Response
    {
        $customer = $this->getUser()->getCustomer();

        if (!$customer instanceof Customer) {
            throw $this->createAccessDeniedException('No tiene un almacen asociado.');
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
