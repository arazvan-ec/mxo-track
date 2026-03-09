<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Customer;
use App\Entity\RouteStop;
use App\Entity\Shipment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route as SymfonyRoute;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[SymfonyRoute('/admin/route-planner')]
#[IsGranted('ROLE_ADMIN')]
class RoutePlannerController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    #[SymfonyRoute('', name: 'admin_route_planner_index', methods: ['GET'])]
    public function index(): Response
    {
        return new Response('Route planner index - TODO');
    }

    #[SymfonyRoute('/shipments', name: 'admin_route_planner_shipments', methods: ['GET'])]
    public function shipments(Request $request): JsonResponse
    {
        $customerPublicId = $request->query->getString('customer', '');

        $qb = $this->em->createQueryBuilder()
            ->select('s')
            ->from(Shipment::class, 's')
            ->leftJoin(RouteStop::class, 'rs', 'WITH', 'rs.shipment = s')
            ->where('rs.id IS NULL')
            ->andWhere('s.deletedAt IS NULL')
            ->andWhere('s.latitude IS NOT NULL')
            ->andWhere('s.longitude IS NOT NULL')
            ->orderBy('s.createdAt', 'DESC');

        if ($customerPublicId !== '') {
            $customer = $this->em->getRepository(Customer::class)
                ->findOneBy(['publicId' => $customerPublicId]);

            if ($customer === null) {
                return new JsonResponse(['shipments' => []]);
            }

            $qb->andWhere('s.customer = :customer')
                ->setParameter('customer', $customer);
        }

        $shipments = $qb->setMaxResults(200)->getQuery()->getResult();

        $data = [];
        foreach ($shipments as $shipment) {
            $data[] = [
                'publicId' => $shipment->getPublicIdString(),
                'reference' => $shipment->getReference(),
                'address' => $shipment->getAddress(),
                'recipientName' => $shipment->getRecipientName(),
                'latitude' => $shipment->getLatitude(),
                'longitude' => $shipment->getLongitude(),
                'totalWeightKg' => $shipment->getTotalWeightKg(),
                'totalVolumeM3' => $shipment->getTotalVolumeM3(),
                'totalParcels' => $shipment->getTotalParcels(),
            ];
        }

        return new JsonResponse(['shipments' => $data]);
    }

    #[SymfonyRoute('/preview', name: 'admin_route_planner_preview', methods: ['POST'])]
    public function preview(): JsonResponse
    {
        return new JsonResponse(['routes' => []]);
    }

    #[SymfonyRoute('/confirm', name: 'admin_route_planner_confirm', methods: ['POST'])]
    public function confirm(): Response
    {
        return $this->redirectToRoute('admin_routes_index');
    }
}
