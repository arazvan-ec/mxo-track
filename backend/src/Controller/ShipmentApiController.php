<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Shipment;
use App\Entity\ShipmentEvent;
use App\Entity\User;
use App\Http\ApiErrorResponder;
use App\Repository\ShipmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/shipments', name: 'api_shipments_')]
class ShipmentApiController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function list(EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $criteria = [];
        if ($user->hasRole('ROLE_CUSTOMER') && !$user->hasRole('ROLE_ADMIN')) {
            $customer = $user->getCustomer();
            if ($customer === null) {
                return $this->json(['items' => []]);
            }
            $criteria['customer'] = $customer;
        }

        $shipments = $entityManager->getRepository(Shipment::class)->findBy($criteria, ['createdAt' => 'DESC'], 200);

        $items = [];
        foreach ($shipments as $shipment) {
            $items[] = [
                'public_id' => $shipment->getPublicIdString(),
                'reference' => $shipment->getReference(),
            ];
        }

        return $this->json(['items' => $items]);
    }

    #[Route('/{publicId}', methods: ['GET'])]
    public function detail(string $publicId, ShipmentRepository $shipmentRepository, EntityManagerInterface $entityManager, ApiErrorResponder $errorResponder): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $shipment = $shipmentRepository->findOneByPublicId($publicId);
        if (!$shipment instanceof Shipment) {
            return $errorResponder->notFound('shipment_not_found', 'Envío no encontrado.');
        }

        if ($user->hasRole('ROLE_CUSTOMER') && $user->getCustomer() !== $shipment->getCustomer()) {
            return $errorResponder->notFound('shipment_not_found', 'Envío no permitido.');
        }

        $events = $entityManager->getRepository(ShipmentEvent::class)->findBy(['shipment' => $shipment], ['createdAt' => 'ASC']);

        $timeline = array_map(static fn (ShipmentEvent $event): array => [
            'type' => $event->getEventType()->value,
            'payload' => $event->getPayload(),
            'created_at' => $event->getCreatedAt()->format(DATE_ATOM),
        ], $events);

        return $this->json([
            'public_id' => $shipment->getPublicIdString(),
            'reference' => $shipment->getReference(),
            'events' => $timeline,
        ]);
    }
}
