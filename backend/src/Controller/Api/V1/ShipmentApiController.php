<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Entity\Shipment;
use App\Entity\ShipmentEvent;
use App\Enum\ShipmentEventType;
use App\Http\ApiErrorResponder;
use App\Repository\ShipmentRepository;
use App\Security\ApiKeyUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/v1/shipments', name: 'api_v1_shipments_')]
class ShipmentApiController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ShipmentRepository $shipmentRepository,
        private readonly ApiErrorResponder $errorResponder,
    ) {
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var ApiKeyUser $user */
        $user = $this->getUser();
        $customer = $user->getCustomer();

        $body = json_decode($request->getContent(), true);
        if (!is_array($body)) {
            return $this->errorResponder->badRequest('invalid_json', 'Request body must be valid JSON.');
        }

        // Support both single shipment and array of shipments
        $items = isset($body['reference']) ? [$body] : ($body['shipments'] ?? [$body]);
        $created = [];

        foreach ($items as $item) {
            $reference = $item['reference'] ?? null;
            if (!is_string($reference) || $reference === '') {
                return $this->errorResponder->badRequest('missing_reference', 'Each shipment must have a reference.');
            }

            // Check uniqueness
            $existing = $this->shipmentRepository->findOneBy(['reference' => $reference]);
            if ($existing !== null) {
                return $this->errorResponder->badRequest('duplicate_reference', sprintf('Shipment with reference "%s" already exists.', $reference));
            }

            $shipment = new Shipment($reference, $customer);
            $shipment->setRecipientName($item['recipient_name'] ?? null);
            $shipment->setRecipientPhone($item['recipient_phone'] ?? null);
            $shipment->setAddress($item['address'] ?? null);
            $shipment->setNotes($item['notes'] ?? null);
            $shipment->setDescription($item['description'] ?? null);

            if (isset($item['latitude'])) {
                $shipment->setLatitude((float) $item['latitude']);
            }
            if (isset($item['longitude'])) {
                $shipment->setLongitude((float) $item['longitude']);
            }
            if (isset($item['total_weight_kg'])) {
                $shipment->setTotalWeightKg((float) $item['total_weight_kg']);
            }
            if (isset($item['total_volume_m3'])) {
                $shipment->setTotalVolumeM3((float) $item['total_volume_m3']);
            }
            if (isset($item['total_parcels'])) {
                $shipment->setTotalParcels((int) $item['total_parcels']);
            }

            $this->em->persist($shipment);

            // Create initial CREATED event
            $event = new ShipmentEvent($shipment, ShipmentEventType::CREATED, [
                'source' => 'api_v1',
            ]);
            $this->em->persist($event);

            $created[] = $shipment;
        }

        $this->em->flush();

        $result = array_map(static fn (Shipment $s): array => [
            'public_id' => $s->getPublicIdString(),
            'reference' => $s->getReference(),
            'tracking_token' => $s->getTrackingToken(),
            'created_at' => $s->getCreatedAt()->format(\DATE_ATOM),
        ], $created);

        return new JsonResponse(
            ['shipments' => $result],
            Response::HTTP_CREATED,
        );
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', 20)));
        $offset = ($page - 1) * $limit;

        $qb = $this->em->createQueryBuilder()
            ->select('s')
            ->from(Shipment::class, 's')
            ->orderBy('s.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $shipments = $qb->getQuery()->getResult();

        $total = (int) $this->em->createQueryBuilder()
            ->select('COUNT(s.id)')
            ->from(Shipment::class, 's')
            ->getQuery()
            ->getSingleScalarResult();

        $items = array_map(static fn (Shipment $s): array => [
            'public_id' => $s->getPublicIdString(),
            'reference' => $s->getReference(),
            'address' => $s->getAddress(),
            'recipient_name' => $s->getRecipientName(),
            'tracking_token' => $s->getTrackingToken(),
            'created_at' => $s->getCreatedAt()->format(\DATE_ATOM),
        ], $shipments);

        return new JsonResponse([
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    #[Route('/{publicId}', name: 'detail', methods: ['GET'])]
    public function detail(string $publicId): JsonResponse
    {
        $shipment = $this->shipmentRepository->findOneByPublicId($publicId);
        if (!$shipment instanceof Shipment) {
            return $this->errorResponder->notFound('shipment_not_found', 'Shipment not found.');
        }

        return new JsonResponse([
            'public_id' => $shipment->getPublicIdString(),
            'reference' => $shipment->getReference(),
            'recipient_name' => $shipment->getRecipientName(),
            'recipient_phone' => $shipment->getRecipientPhone(),
            'address' => $shipment->getAddress(),
            'latitude' => $shipment->getLatitude(),
            'longitude' => $shipment->getLongitude(),
            'notes' => $shipment->getNotes(),
            'description' => $shipment->getDescription(),
            'service_type' => $shipment->getServiceType()->value,
            'total_weight_kg' => $shipment->getTotalWeightKg(),
            'total_volume_m3' => $shipment->getTotalVolumeM3(),
            'total_parcels' => $shipment->getTotalParcels(),
            'tracking_token' => $shipment->getTrackingToken(),
            'created_at' => $shipment->getCreatedAt()->format(\DATE_ATOM),
        ]);
    }

    #[Route('/{publicId}/tracking', name: 'tracking', methods: ['GET'])]
    public function tracking(string $publicId): JsonResponse
    {
        $shipment = $this->shipmentRepository->findOneByPublicId($publicId);
        if (!$shipment instanceof Shipment) {
            return $this->errorResponder->notFound('shipment_not_found', 'Shipment not found.');
        }

        $events = $this->em->getRepository(ShipmentEvent::class)->findBy(
            ['shipment' => $shipment],
            ['createdAt' => 'ASC'],
        );

        $timeline = array_map(static fn (ShipmentEvent $e): array => [
            'type' => $e->getEventType()->value,
            'payload' => $e->getPayload(),
            'created_at' => $e->getCreatedAt()->format(\DATE_ATOM),
        ], $events);

        return new JsonResponse([
            'public_id' => $shipment->getPublicIdString(),
            'reference' => $shipment->getReference(),
            'events' => $timeline,
        ]);
    }
}
