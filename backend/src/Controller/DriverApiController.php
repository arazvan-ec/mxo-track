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
use App\Repository\PodRepository;
use App\Repository\RouteRepository;
use App\Repository\RouteStopRepository;
use App\Repository\ShipmentRepository;
use App\Service\AuditLogger;
use App\Service\DeliveryEvidenceFactory;
use App\Service\DriverActionService;
use App\Service\EtaService;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/driver')]
#[IsGranted('ROLE_DRIVER')]
class DriverApiController extends AbstractController
{
    #[Route('/routes', methods: ['GET'])]
    public function routes(EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var User $driver */
        $driver = $this->getUser();

        $routes = $entityManager->getRepository(RouteEntity::class)->findBy(['driver' => $driver]);
        $items = array_map(static fn (RouteEntity $route): array => [
            'public_id' => $route->getPublicIdString(),
            'status' => $route->getStatus()->value,
        ], $routes);

        return $this->json(['items' => $items]);
    }

    #[Route('/routes/{routePublicId}/start', methods: ['POST'])]
    public function start(string $routePublicId, RouteRepository $routeRepository, EntityManagerInterface $entityManager, ApiErrorResponder $errorResponder): JsonResponse
    {
        $route = $routeRepository->findOneByPublicId($routePublicId);
        if (!$route instanceof RouteEntity) {
            return $errorResponder->notFound('route_not_found', 'Ruta no encontrada.');
        }

        /** @var User $driver */
        $driver = $this->getUser();
        if ($route->getDriver()?->getId() !== $driver->getId()) {
            return $errorResponder->notFound('route_not_found', 'Ruta no encontrada.');
        }

        $route->start();
        $entityManager->flush();

        return $this->json(['ok' => true, 'status' => $route->getStatus()->value]);
    }

    #[Route('/routes/{routePublicId}/finish', methods: ['POST'])]
    public function finish(string $routePublicId, RouteRepository $routeRepository, EntityManagerInterface $entityManager, ApiErrorResponder $errorResponder): JsonResponse
    {
        $route = $routeRepository->findOneByPublicId($routePublicId);
        if (!$route instanceof RouteEntity) {
            return $errorResponder->notFound('route_not_found', 'Ruta no encontrada.');
        }

        /** @var User $driver */
        $driver = $this->getUser();
        if ($route->getDriver()?->getId() !== $driver->getId()) {
            return $errorResponder->notFound('route_not_found', 'Ruta no encontrada.');
        }

        $route->finish();
        $entityManager->flush();

        return $this->json(['ok' => true, 'status' => $route->getStatus()->value]);
    }

    #[Route('/routes/{routePublicId}/stops', methods: ['GET'])]
    public function stops(string $routePublicId, RouteRepository $routeRepository, EntityManagerInterface $entityManager, ApiErrorResponder $errorResponder): JsonResponse
    {
        $route = $routeRepository->findOneByPublicId($routePublicId);
        if (!$route instanceof RouteEntity) {
            return $errorResponder->notFound('route_not_found', 'Ruta no encontrada.');
        }

        /** @var User $driver */
        $driver = $this->getUser();
        if ($route->getDriver()?->getId() !== $driver->getId()) {
            return $errorResponder->notFound('route_not_found', 'Ruta no encontrada.');
        }

        $stops = $entityManager->getRepository(RouteStop::class)->findBy(['route' => $route], ['sequence' => 'ASC']);
        $items = array_map(static fn (RouteStop $stop): array => [
            'public_id' => $stop->getPublicIdString(),
            'status' => $stop->getStatus()->value,
        ], $stops);

        return $this->json(['items' => $items]);
    }

    #[Route('/stops/{stopPublicId}/deliver', methods: ['POST'])]
    public function deliver(
        string $stopPublicId,
        Request $request,
        DriverActionService $actionService,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
        ApiErrorResponder $errorResponder,
        ValidatorInterface $validator,
        DeliveryEvidenceFactory $deliveryEvidenceFactory,
        RouteStopRepository $routeStopRepository,
        ShipmentRepository $shipmentRepository,
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

        $stop = $routeStopRepository->findOneByPublicId($stopPublicId);
        if (!$stop instanceof RouteStop) {
            return $errorResponder->notFound('stop_not_found', 'Parada no encontrada.');
        }

        if ($stop->getRoute()->getDriver()?->getId() !== $driver->getId()) {
            return $errorResponder->notFound('stop_not_found', 'Parada no encontrada.');
        }

        $created = $actionService->register($driver, $input->clientActionId, 'DELIVER', $stop);
        if (!$created) {
            return $this->json(['ok' => true, 'idempotent' => true]);
        }

        if (!$input->confirmedByDriver) {
            return $errorResponder->badRequest('driver_confirmation_required', 'El driver debe confirmar explícitamente la entrega.');
        }

        $stop->markDelivered();

        $pod = new Pod($stop, $driver, $input->signedByName, $input->recipientIdEncoded);
        $entityManager->persist($pod);

        if ($input->shipmentPublicId !== null) {
            $shipment = $shipmentRepository->findOneByPublicId($input->shipmentPublicId);
            if ($shipment instanceof Shipment) {
                $entityManager->persist(new ShipmentEvent($shipment, ShipmentEventType::DELIVERED, [
                    'stop_public_id' => $stopPublicId,
                    'confirmation_mode' => 'recipient_id_encoded',
                ]));
            }
        }

        $auditLogger->log($driver, 'DRIVER_DELIVER', 'route_stop', (string) $stop->getId(), [
            'client_action_id' => $input->clientActionId,
            'shipment_public_id' => $input->shipmentPublicId ?? '',
            'delivery_evidence' => $deliveryEvidenceFactory->build(
                $input->recipientIdEncoded,
                $input->confirmedByDriver,
                $stopPublicId,
                $input->clientActionId,
                $driver->getPublicIdString(),
                (string) ($request->getClientIp() ?? ''),
                (string) $request->headers->get('User-Agent', ''),
            ),
        ]);

        $entityManager->flush();

        return $this->json(['ok' => true, 'idempotent' => false], 201);
    }

    #[Route('/stops/{stopPublicId}/exception', methods: ['POST'])]
    public function exception(
        string $stopPublicId,
        Request $request,
        DriverActionService $actionService,
        EntityManagerInterface $entityManager,
        AuditLogger $auditLogger,
        ApiErrorResponder $errorResponder,
        ValidatorInterface $validator,
        RouteStopRepository $routeStopRepository,
        ShipmentRepository $shipmentRepository,
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
        $stop = $routeStopRepository->findOneByPublicId($stopPublicId);

        if (!$stop instanceof RouteStop) {
            return $errorResponder->notFound('stop_not_found', 'Parada no encontrada.');
        }

        if ($stop->getRoute()->getDriver()?->getId() !== $driver->getId()) {
            return $errorResponder->notFound('stop_not_found', 'Parada no encontrada.');
        }

        $created = $actionService->register($driver, $input->clientActionId, 'EXCEPTION', $stop);
        if (!$created) {
            return $this->json(['ok' => true, 'idempotent' => true]);
        }

        $reason = ExceptionCode::tryFrom($input->reason) ?? ExceptionCode::OTHER;
        $stop->markException($reason, $input->comment);

        if ($input->shipmentPublicId !== null) {
            $shipment = $shipmentRepository->findOneByPublicId($input->shipmentPublicId);
            if ($shipment instanceof Shipment) {
                $entityManager->persist(new ShipmentEvent($shipment, ShipmentEventType::EXCEPTION, [
                    'stop_public_id' => $stopPublicId,
                    'reason' => $reason->value,
                    'comment' => $input->comment,
                ]));
            }
        }

        $auditLogger->log($driver, 'DRIVER_EXCEPTION', 'route_stop', (string) $stop->getId(), [
            'client_action_id' => $input->clientActionId,
            'shipment_public_id' => $input->shipmentPublicId ?? '',
            'reason' => $reason->value,
            'comment' => $input->comment,
        ]);

        $entityManager->flush();

        return $this->json(['ok' => true, 'idempotent' => false], 201);
    }

    #[Route('/stops/{stopPublicId}/pod', methods: ['GET'])]
    public function podMetadata(string $stopPublicId, RouteStopRepository $routeStopRepository, PodRepository $podRepository, ApiErrorResponder $errorResponder): JsonResponse
    {
        /** @var User $driver */
        $driver = $this->getUser();

        $stop = $routeStopRepository->findOneByPublicId($stopPublicId);
        if (!$stop instanceof RouteStop) {
            return $errorResponder->notFound('stop_not_found', 'Parada no encontrada.');
        }

        if ($stop->getRoute()->getDriver()?->getId() !== $driver->getId()) {
            return $errorResponder->notFound('stop_not_found', 'Parada no encontrada.');
        }

        $pod = $podRepository->findOneBy(['routeStop' => $stop]);
        if (!$pod instanceof Pod) {
            return $errorResponder->notFound('pod_not_found', 'Confirmación no encontrada.');
        }

        return $this->json([
            'pod_public_id' => $pod->getPublicIdString(),
            'download_url' => sprintf('/api/driver/stops/%s/pod/download', $stopPublicId),
            'confirmation_mode' => 'recipient_id_encoded',
        ]);
    }

    #[Route('/stops/{stopPublicId}/pod/download', methods: ['GET'])]
    public function podDownload(string $stopPublicId, RouteStopRepository $routeStopRepository, PodRepository $podRepository, ApiErrorResponder $errorResponder): JsonResponse
    {
        /** @var User $driver */
        $driver = $this->getUser();

        $stop = $routeStopRepository->findOneByPublicId($stopPublicId);
        if (!$stop instanceof RouteStop) {
            return $errorResponder->notFound('stop_not_found', 'Parada no encontrada.');
        }

        if ($stop->getRoute()->getDriver()?->getId() !== $driver->getId()) {
            return $errorResponder->notFound('stop_not_found', 'Parada no encontrada.');
        }

        $pod = $podRepository->findOneBy(['routeStop' => $stop]);
        if (!$pod instanceof Pod) {
            return $errorResponder->notFound('pod_not_found', 'Confirmación no encontrada.');
        }

        return $this->json([
            'recipient_id_encoded' => $pod->getRecipientIdEncoded(),
            'confirmed_by_driver' => $pod->isConfirmedByDriver(),
        ]);
    }

    #[Route('/routes/{routePublicId}/etas', methods: ['GET'])]
    public function etas(
        string $routePublicId,
        RouteRepository $routeRepository,
        EtaService $etaService,
        ApiErrorResponder $errorResponder,
    ): JsonResponse {
        $route = $routeRepository->findOneByPublicId($routePublicId);
        if (!$route instanceof RouteEntity) {
            return $errorResponder->notFound('route_not_found', 'Ruta no encontrada.');
        }

        /** @var User $driver */
        $driver = $this->getUser();
        if ($route->getDriver()?->getId() !== $driver->getId()) {
            return $errorResponder->notFound('route_not_found', 'Ruta no encontrada.');
        }

        $etas = $etaService->calculateEtas($route);

        $items = [];
        foreach ($etas as $stopPublicId => $data) {
            $items[$stopPublicId] = [
                'eta' => $data['eta']->format(\DATE_ATOM),
                'eta_formatted' => $data['eta']->format('H:i'),
                'remaining_minutes' => $data['remainingMinutes'],
                'distance_km' => $data['distanceKm'],
            ];
        }

        return $this->json(['items' => $items]);
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
