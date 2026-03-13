<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\RouteEventRepository;
use App\Repository\RouteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/routes')]
#[IsGranted('ROLE_ADMIN')]
class RouteEventApiController extends AbstractController
{
    public function __construct(
        private readonly RouteRepository $routeRepo,
        private readonly RouteEventRepository $eventRepo,
    ) {}

    #[Route('/{publicId}/events', name: 'api_route_events', methods: ['GET'])]
    public function events(string $publicId): JsonResponse
    {
        $route = $this->routeRepo->findOneByPublicId($publicId);

        if (!$route) {
            return new JsonResponse(['error' => 'Route not found'], Response::HTTP_NOT_FOUND);
        }

        $events = $this->eventRepo->findByRoute($route);

        $data = array_map(static fn ($e) => [
            'type' => $e->getEventType()->value,
            'actor_type' => $e->getActorType(),
            'actor_email' => $e->getActorUser()?->getEmail(),
            'payload' => $e->getPayload(),
            'snapshot_metrics' => $e->getSnapshotMetrics(),
            'occurred_at' => $e->getOccurredAt()->format('c'),
        ], $events);

        return new JsonResponse(['events' => $data]);
    }
}
