<?php

declare(strict_types=1);

namespace App\Controller;

use App\Application\Delivery\DeliveryContext;
use App\Application\Delivery\DeliveryService;
use App\Application\Delivery\DriverConfirmationRequiredException;
use App\Application\Delivery\DriverNotOwnerException;
use App\Application\Delivery\StopNotFoundException;
use App\Application\Route\RouteLifecycleService;
use App\Application\Route\RouteNotFoundException;
use App\Application\Route\RouteNotOwnedException;
use App\Dto\Driver\DeliverStopInput;
use App\Dto\Driver\ExceptionStopInput;
use App\Dto\Driver\StopFeedbackInput;
use App\Entity\DriverFeedback;
use App\Entity\Route as RouteEntity;
use App\Entity\RouteStop;
use App\Entity\User;
use App\Http\ApiErrorResponder;
use App\Repository\PodRepository;
use App\Repository\RouteRepository;
use App\Repository\RouteStopRepository;
use App\Service\DriverBriefingService;
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
    public function start(
        string $routePublicId,
        RouteLifecycleService $lifecycleService,
        ApiErrorResponder $errorResponder,
    ): JsonResponse {
        /** @var User $driver */
        $driver = $this->getUser();

        try {
            $route = $lifecycleService->startRoute($routePublicId, $driver);
        } catch (RouteNotFoundException|RouteNotOwnedException) {
            return $errorResponder->notFound('route_not_found', 'Ruta no encontrada.');
        }

        return $this->json(['ok' => true, 'status' => $route->getStatus()->value]);
    }

    #[Route('/routes/{routePublicId}/finish', methods: ['POST'])]
    public function finish(
        string $routePublicId,
        RouteLifecycleService $lifecycleService,
        ApiErrorResponder $errorResponder,
    ): JsonResponse {
        /** @var User $driver */
        $driver = $this->getUser();

        try {
            $route = $lifecycleService->finishRoute($routePublicId, $driver);
        } catch (RouteNotFoundException|RouteNotOwnedException) {
            return $errorResponder->notFound('route_not_found', 'Ruta no encontrada.');
        }

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
        DeliveryService $deliveryService,
        ApiErrorResponder $errorResponder,
        ValidatorInterface $validator,
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

        try {
            $result = $deliveryService->deliverStop(
                $stopPublicId,
                $input,
                $driver,
                new DeliveryContext(
                    clientIp: (string) ($request->getClientIp() ?? ''),
                    userAgent: (string) $request->headers->get('User-Agent', ''),
                ),
            );
        } catch (StopNotFoundException|DriverNotOwnerException) {
            return $errorResponder->notFound('stop_not_found', 'Parada no encontrada.');
        } catch (DriverConfirmationRequiredException) {
            return $errorResponder->badRequest('driver_confirmation_required', 'El driver debe confirmar explícitamente la entrega.');
        }

        return $this->json($result->toArray(), $result->idempotent ? 200 : 201);
    }

    #[Route('/stops/{stopPublicId}/exception', methods: ['POST'])]
    public function exception(
        string $stopPublicId,
        Request $request,
        DeliveryService $deliveryService,
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

        try {
            $result = $deliveryService->reportException($stopPublicId, $input, $driver);
        } catch (StopNotFoundException|DriverNotOwnerException) {
            return $errorResponder->notFound('stop_not_found', 'Parada no encontrada.');
        }

        return $this->json($result->toArray(), $result->idempotent ? 200 : 201);
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
        if ($pod === null) {
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
        if ($pod === null) {
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

    #[Route('/routes/{routePublicId}/stops/{stopPublicId}/feedback', methods: ['POST'])]
    public function stopFeedback(
        string $routePublicId,
        string $stopPublicId,
        Request $request,
        RouteRepository $routeRepository,
        RouteStopRepository $routeStopRepository,
        EntityManagerInterface $entityManager,
        ApiErrorResponder $errorResponder,
        ValidatorInterface $validator,
    ): JsonResponse {
        $payload = $this->decodePayload($request, $errorResponder);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $input = StopFeedbackInput::fromArray($payload);
        $violations = $validator->validate($input);
        if (count($violations) > 0) {
            return $errorResponder->unprocessableEntity('validation_failed', $violations);
        }

        $route = $routeRepository->findOneByPublicId($routePublicId);
        if (!$route instanceof RouteEntity) {
            return $errorResponder->notFound('route_not_found', 'Ruta no encontrada.');
        }

        /** @var User $driver */
        $driver = $this->getUser();
        if ($route->getDriver()?->getId() !== $driver->getId()) {
            return $errorResponder->notFound('route_not_found', 'Ruta no encontrada.');
        }

        $stop = $routeStopRepository->findOneByPublicId($stopPublicId);
        if (!$stop instanceof RouteStop) {
            return $errorResponder->notFound('stop_not_found', 'Parada no encontrada.');
        }

        if ($stop->getRoute()->getDriver()?->getId() !== $driver->getId()) {
            return $errorResponder->notFound('stop_not_found', 'Parada no encontrada.');
        }

        $feedback = new DriverFeedback(
            driver: $driver,
            stop: $stop,
            correctedLat: $input->correctedLat,
            correctedLng: $input->correctedLng,
            accessNotes: $input->accessNotes,
            actualServiceTimeSeconds: $input->actualServiceTimeSeconds,
            comment: $input->comment,
        );
        $entityManager->persist($feedback);
        $entityManager->flush();

        return $this->json([
            'ok' => true,
            'feedback_public_id' => $feedback->getPublicIdString(),
        ], 201);
    }

    #[Route('/routes/{routePublicId}/briefing', methods: ['GET'])]
    public function briefing(
        string $routePublicId,
        RouteRepository $routeRepository,
        DriverBriefingService $briefingService,
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

        $briefing = $briefingService->generateBriefing($route);

        return $this->json($briefing->toArray());
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
