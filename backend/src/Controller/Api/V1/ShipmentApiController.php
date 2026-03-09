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
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[OA\Tag(name: 'Shipments')]
#[Route('/api/v1/shipments', name: 'api_v1_shipments_')]
class ShipmentApiController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ShipmentRepository $shipmentRepository,
        private readonly ApiErrorResponder $errorResponder,
    ) {
    }

    #[OA\Post(
        summary: 'Create one or more shipments',
        description: 'Creates a single shipment or a batch of shipments. Send a single object with a "reference" field, or an object with a "shipments" array.',
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'reference', type: 'string', example: 'SHP-001'),
                new OA\Property(property: 'address', type: 'string', example: 'Calle Gran Vía 1, Madrid'),
                new OA\Property(property: 'recipient_name', type: 'string', example: 'Juan García'),
                new OA\Property(property: 'recipient_phone', type: 'string', example: '+34612345678'),
                new OA\Property(property: 'latitude', type: 'number', format: 'float', example: 40.4168),
                new OA\Property(property: 'longitude', type: 'number', format: 'float', example: -3.7038),
                new OA\Property(property: 'total_weight_kg', type: 'number', format: 'float', example: 2.5),
                new OA\Property(property: 'total_volume_m3', type: 'number', format: 'float', example: 0.05),
                new OA\Property(property: 'total_parcels', type: 'integer', example: 1),
                new OA\Property(property: 'notes', type: 'string', example: 'Fragile'),
                new OA\Property(property: 'description', type: 'string', example: 'Electronics package'),
            ],
        ),
    )]
    #[OA\Response(
        response: 201,
        description: 'Shipment(s) created successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'shipments',
                    type: 'array',
                    items: new OA\Items(properties: [
                        new OA\Property(property: 'public_id', type: 'string', example: '01JABCDEF123456789'),
                        new OA\Property(property: 'reference', type: 'string', example: 'SHP-001'),
                        new OA\Property(property: 'tracking_token', type: 'string'),
                        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                    ]),
                ),
            ],
        ),
    )]
    #[OA\Response(
        response: 400,
        description: 'Validation error (invalid JSON, missing reference, duplicate reference)',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'missing_reference'),
                new OA\Property(property: 'message', type: 'string', example: 'Each shipment must have a reference.'),
            ],
        ),
    )]
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

    #[OA\Get(summary: 'List shipments', description: 'Returns a paginated list of shipments for the authenticated customer.')]
    #[OA\Parameter(name: 'page', in: 'query', required: false, description: 'Page number (default: 1)', schema: new OA\Schema(type: 'integer', minimum: 1, default: 1))]
    #[OA\Parameter(name: 'limit', in: 'query', required: false, description: 'Items per page (1-100, default: 20)', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 20))]
    #[OA\Response(
        response: 200,
        description: 'Paginated list of shipments',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'items',
                    type: 'array',
                    items: new OA\Items(properties: [
                        new OA\Property(property: 'public_id', type: 'string'),
                        new OA\Property(property: 'reference', type: 'string'),
                        new OA\Property(property: 'address', type: 'string', nullable: true),
                        new OA\Property(property: 'recipient_name', type: 'string', nullable: true),
                        new OA\Property(property: 'tracking_token', type: 'string'),
                        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                    ]),
                ),
                new OA\Property(property: 'total', type: 'integer'),
                new OA\Property(property: 'page', type: 'integer'),
                new OA\Property(property: 'limit', type: 'integer'),
            ],
        ),
    )]
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

    #[OA\Get(summary: 'Get shipment detail', description: 'Returns full details of a single shipment by its public ID.')]
    #[OA\Parameter(name: 'publicId', in: 'path', required: true, description: 'Shipment ULID', schema: new OA\Schema(type: 'string'))]
    #[OA\Response(
        response: 200,
        description: 'Shipment details',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'public_id', type: 'string'),
                new OA\Property(property: 'reference', type: 'string'),
                new OA\Property(property: 'recipient_name', type: 'string', nullable: true),
                new OA\Property(property: 'recipient_phone', type: 'string', nullable: true),
                new OA\Property(property: 'address', type: 'string', nullable: true),
                new OA\Property(property: 'latitude', type: 'number', format: 'float', nullable: true),
                new OA\Property(property: 'longitude', type: 'number', format: 'float', nullable: true),
                new OA\Property(property: 'notes', type: 'string', nullable: true),
                new OA\Property(property: 'description', type: 'string', nullable: true),
                new OA\Property(property: 'service_type', type: 'string'),
                new OA\Property(property: 'total_weight_kg', type: 'number', format: 'float', nullable: true),
                new OA\Property(property: 'total_volume_m3', type: 'number', format: 'float', nullable: true),
                new OA\Property(property: 'total_parcels', type: 'integer', nullable: true),
                new OA\Property(property: 'tracking_token', type: 'string'),
                new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
            ],
        ),
    )]
    #[OA\Response(
        response: 404,
        description: 'Shipment not found',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'shipment_not_found'),
                new OA\Property(property: 'message', type: 'string', example: 'Shipment not found.'),
            ],
        ),
    )]
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

    #[OA\Get(summary: 'Get shipment tracking events', description: 'Returns the full event timeline for a shipment.')]
    #[OA\Parameter(name: 'publicId', in: 'path', required: true, description: 'Shipment ULID', schema: new OA\Schema(type: 'string'))]
    #[OA\Response(
        response: 200,
        description: 'Tracking timeline',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'public_id', type: 'string'),
                new OA\Property(property: 'reference', type: 'string'),
                new OA\Property(
                    property: 'events',
                    type: 'array',
                    items: new OA\Items(properties: [
                        new OA\Property(property: 'type', type: 'string', example: 'CREATED'),
                        new OA\Property(property: 'payload', type: 'object', nullable: true),
                        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                    ]),
                ),
            ],
        ),
    )]
    #[OA\Response(
        response: 404,
        description: 'Shipment not found',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'shipment_not_found'),
                new OA\Property(property: 'message', type: 'string', example: 'Shipment not found.'),
            ],
        ),
    )]
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
