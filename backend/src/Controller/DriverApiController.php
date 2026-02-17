<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\Driver\DeliverStopInput;
use App\Dto\Driver\ExceptionStopInput;
use App\Entity\Pod;
use App\Entity\Route as RouteEntity;
use App\Entity\RouteStop;
use App\Entity\Shipment;
use App\Entity\ShipmentEvent;
use App\Entity\User;
use App\Enum\ExceptionCode;
use App\Enum\ShipmentEventType;
use App\Http\ApiErrorResponder;
use App\Service\AuditLogger;
use App\Service\DeliveryEvidenceFactory;
use App\Service\DriverActionService;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/driver')]
class DriverApiController extends AbstractController
{
    #[Route('/routes', methods: ['GET'])]
    public function routes(EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var User $driver */
        $driver = $this->getUser();

        $routes = $entityManager->getRepository(RouteEntity::class)->findBy(['driver' => $driver]);
        $items = array_map(static fn (RouteEntity $route): array => [
            'id' => (string) $route->getId(),
            'status' => $route->getStatus()->value,
        ], $routes);

        return $this->json(['items' => $items]);
    }

    #[Route('/routes/{routeId}/start', methods: ['POST'])]
    public function start(string $routeId, EntityManagerInterface $entityManager, ApiErrorResponder $errorResponder): JsonResponse
    {
        $route = $entityManager->find(RouteEntity::class, $routeId);
        if (!$route instanceof RouteEntity) {
            return $errorResponder->notFound('route_not_found', 'Ruta no encontrada.');
        }

        $route->start();
        $entityManager->flush();

        return $this->json(['ok' => true, 'status' => $route->getStatus()->value]);
    }

    #[Route('/routes/{routeId}/finish', methods: ['POST'])]
    public function finish(string $routeId, EntityManagerInterface $entityManager, ApiErrorResponder $errorResponder): JsonResponse
    {
        $route = $entityManager->find(RouteEntity::class, $routeId);
        if (!$route instanceof RouteEntity) {
            return $errorResponder->notFound('route_not_found', 'Ruta no encontrada.');
        }

        $route->finish();
        $entityManager->flush();

        return $this->json(['ok' => true, 'status' => $route->getStatus()->value]);
    }

    #[Route('/routes/{routeId}/stops', methods: ['GET'])]
    public function stops(string $routeId, EntityManagerInterface $entityManager, ApiErrorResponder $errorResponder): JsonResponse
    {
        $route = $entityManager->find(RouteEntity::class, $routeId);
        if (!$route instanceof RouteEntity) {
            return $errorResponder->notFound('route_not_found', 'Ruta no encontrada.');
        }

        $stops = $entityManager->getRepository(RouteStop::class)->findBy(['route' => $route], ['sequence' => 'ASC']);
        $items = array_map(static fn (RouteStop $stop): array => [
            'id' => (string) $stop->getId(),
            'status' => $stop->getStatus()->value,
        ], $stops);

        return $this->json(['items' => $items]);
    }

    #[Route('/stops/{stopId}/deliver', methods: ['POST'])]
    public function deliver(
        string $stopId,
        Request $request,
        DriverActionService $actionService,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
        ApiErrorResponder $errorResponder,
        ValidatorInterface $validator,
        DeliveryEvidenceFactory $deliveryEvidenceFactory,
    ): JsonResponse {
        $payload = $this->decodePayload($request, $errorResponder);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $input = DeliverStopInput::fromArray($payload);
        $violations = $validator->validate($input);
        if (count($violations) > 0) {
            return $errorResponder->unprocessableEntity('validation_failed', $violations);
        }

        /** @var User $driver */
        $driver = $this->getUser();

        $stop = $entityManager->find(RouteStop::class, $stopId);
        if (!$stop instanceof RouteStop) {
            return $errorResponder->notFound('stop_not_found', 'Parada no encontrada.');
        }

        $created = $actionService->register($driver, $input->clientActionId, 'DELIVER', $stop);
        if (!$created) {
            return $this->json(['ok' => true, 'idempotent' => true]);
        }

        $stop->markDelivered();

        if (!$input->confirmedByDriver) {
            return $errorResponder->badRequest('driver_confirmation_required', 'El driver debe confirmar explícitamente la entrega.');
        }

        $pod = new Pod($stop, $driver, $input->signedByName, $input->recipientIdEncoded);
        $entityManager->persist($pod);

        if ($input->shipmentId !== null) {
            $shipment = $entityManager->find(Shipment::class, $input->shipmentId);
            if ($shipment instanceof Shipment) {
                $entityManager->persist(new ShipmentEvent($shipment, ShipmentEventType::DELIVERED, ['stop_id' => $stopId, 'confirmation_mode' => 'recipient_id_encoded']));
            }
        }

        $auditLogger->log($driver, 'DRIVER_DELIVER', 'route_stop', (string) $stop->getId(), [
            'client_action_id' => $input->clientActionId,
            'shipment_id' => $input->shipmentId ?? '',
            'delivery_evidence' => $deliveryEvidenceFactory->build(
                $input->recipientIdEncoded,
                $input->confirmedByDriver,
                (string) $stop->getId(),
                $input->clientActionId,
                (string) $driver->getId(),
                (string) ($request->getClientIp() ?? ''),
                (string) $request->headers->get('User-Agent', ''),
            ),
        ]);

        $entityManager->flush();

        return $this->json(['ok' => true, 'idempotent' => false], 201);
    }

    #[Route('/stops/{stopId}/exception', methods: ['POST'])]
    public function exception(
        string $stopId,
        Request $request,
        DriverActionService $actionService,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
        ApiErrorResponder $errorResponder,
        ValidatorInterface $validator,
    ): JsonResponse {
        $payload = $this->decodePayload($request, $errorResponder);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $input = ExceptionStopInput::fromArray($payload);
        $violations = $validator->validate($input);
        if (count($violations) > 0) {
            return $errorResponder->unprocessableEntity('validation_failed', $violations);
        }

        /** @var User $driver */
        $driver = $this->getUser();
        $stop = $entityManager->find(RouteStop::class, $stopId);

        if (!$stop instanceof RouteStop) {
            return $errorResponder->notFound('stop_not_found', 'Parada no encontrada.');
        }

        $created = $actionService->register($driver, $input->clientActionId, 'EXCEPTION', $stop);
        if (!$created) {
            return $this->json(['ok' => true, 'idempotent' => true]);
        }

        $reason = ExceptionCode::tryFrom($input->reason) ?? ExceptionCode::OTHER;
        $stop->markException($reason, $input->comment);

        if ($input->shipmentId !== null) {
            $shipment = $entityManager->find(Shipment::class, $input->shipmentId);
            if ($shipment instanceof Shipment) {
                $entityManager->persist(new ShipmentEvent($shipment, ShipmentEventType::EXCEPTION, [
                    'stop_id' => $stopId,
                    'reason' => $reason->value,
                    'comment' => $input->comment,
                ]));
            }
        }

        $auditLogger->log($driver, 'DRIVER_EXCEPTION', 'route_stop', (string) $stop->getId(), [
            'client_action_id' => $input->clientActionId,
            'reason' => $reason->value,
            'comment' => $input->comment,
        ]);

        $entityManager->flush();

        return $this->json(['ok' => true, 'idempotent' => false], 201);
    }

    #[Route('/stops/{stopId}/pod', methods: ['GET'])]
    public function podMetadata(string $stopId, EntityManagerInterface $entityManager, ApiErrorResponder $errorResponder): JsonResponse
    {
        $stop = $entityManager->find(RouteStop::class, $stopId);
        if (!$stop instanceof RouteStop) {
            return $errorResponder->notFound('stop_not_found', 'Parada no encontrada.');
        }

        $pod = $entityManager->getRepository(Pod::class)->findOneBy(['routeStop' => $stop]);
        if (!$pod instanceof Pod) {
            return $errorResponder->notFound('pod_not_found', 'Confirmación no encontrada.');
        }

        return $this->json([
            'pod_id' => (string) $pod->getId(),
            'download_url' => sprintf('/api/driver/stops/%s/pod/download', $stopId),
            'confirmation_mode' => 'recipient_id_encoded',
        ]);
    }

    #[Route('/stops/{stopId}/pod/download', methods: ['GET'])]
    public function podDownload(string $stopId, EntityManagerInterface $entityManager, ApiErrorResponder $errorResponder): JsonResponse
    {
        $stop = $entityManager->find(RouteStop::class, $stopId);
        if (!$stop instanceof RouteStop) {
            return $errorResponder->notFound('stop_not_found', 'Parada no encontrada.');
        }

        $pod = $entityManager->getRepository(Pod::class)->findOneBy(['routeStop' => $stop]);
        if (!$pod instanceof Pod) {
            return $errorResponder->notFound('pod_not_found', 'Confirmación no encontrada.');
        }

        return $this->json([
            'recipient_id_encoded' => $pod->getRecipientIdEncoded(),
            'confirmed_by_driver' => $pod->isConfirmedByDriver(),
        ]);
    }

    /**
     * @return array<string,mixed>|JsonResponse
     */
    private function decodePayload(Request $request, ApiErrorResponder $errorResponder): array|JsonResponse
    {
        try {
            $payload = json_decode((string) $request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $errorResponder->badRequest('invalid_json', 'JSON inválido.');
        }

        return is_array($payload) ? $payload : [];
    }
}
