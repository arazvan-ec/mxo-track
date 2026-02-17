<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Shipment;
use App\Entity\ShipmentEvent;
use App\Entity\User;
use App\Http\ApiErrorResponder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/shipments')]
class ShipmentApiController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function list(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $criteria = [];
        if ($user->hasRole('ROLE_CUSTOMER') && $user->getCustomer() !== null) {
            $criteria['customer'] = $user->getCustomer();
        }

        $shipments = $entityManager->getRepository(Shipment::class)->findBy($criteria, ['createdAt' => 'DESC']);

        $query = mb_strtolower(trim((string) $request->query->get('query', '')));

        $items = [];
        foreach ($shipments as $shipment) {
            if ($query !== '' && !str_contains(mb_strtolower($shipment->getReference()), $query)) {
                continue;
            }

            $items[] = [
                'id' => (string) $shipment->getId(),
                'reference' => $shipment->getReference(),
            ];
        }

        return $this->json(['items' => $items]);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function detail(string $id, EntityManagerInterface $entityManager, ApiErrorResponder $errorResponder): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $shipment = $entityManager->find(Shipment::class, $id);
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
            'id' => (string) $shipment->getId(),
            'reference' => $shipment->getReference(),
            'events' => $timeline,
        ]);
    }
}
