<?php

declare(strict_types=1);

namespace App\Controller;

use App\Application\Tracking\PublicTrackingService;
use App\Notification\DeliveryRatingService;
use App\Repository\ShipmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PublicTrackingController extends AbstractController
{
    public function __construct(
        private readonly PublicTrackingService $trackingService,
        private readonly DeliveryRatingService $ratingService,
        private readonly ShipmentRepository $shipmentRepo,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/track/{trackingToken}', name: 'public_tracking', methods: ['GET'])]
    public function track(string $trackingToken): Response
    {
        $info = $this->trackingService->trackByToken($trackingToken);

        if ($info === null) {
            throw $this->createNotFoundException('Envio no encontrado.');
        }

        $existingRating = $this->ratingService->getRatingForShipment($info->shipment);

        return $this->render('tracking/public.html.twig', [
            'shipment' => $info->shipment,
            'events' => $info->events,
            'latestEvent' => $info->latestEvent,
            'approximatePosition' => $info->approximatePosition,
            'routeActive' => $info->routeActive,
            'existingRating' => $existingRating,
        ]);
    }

    #[Route('/track/{trackingToken}/rate', name: 'public_tracking_rate', methods: ['POST'])]
    public function rate(string $trackingToken, Request $request): JsonResponse
    {
        $this->disableTenantFilter();

        $shipment = $this->shipmentRepo->findOneByTrackingToken($trackingToken);
        if ($shipment === null) {
            return new JsonResponse(['error' => 'Envio no encontrado'], 404);
        }

        $payload = json_decode((string) $request->getContent(), true);
        if (!$payload || !isset($payload['score'])) {
            return new JsonResponse(['error' => 'Se requiere una puntuacion'], 400);
        }

        $score = (int) $payload['score'];
        if ($score < 1 || $score > 5) {
            return new JsonResponse(['error' => 'La puntuacion debe ser entre 1 y 5'], 400);
        }

        $existing = $this->ratingService->getRatingForShipment($shipment);
        if ($existing !== null) {
            return new JsonResponse(['error' => 'Este envio ya tiene una valoracion'], 409);
        }

        $rating = $this->ratingService->submitRating(
            $shipment,
            $score,
            $payload['comment'] ?? null,
            $payload['tags'] ?? null,
            $payload['phone'] ?? null,
        );

        return new JsonResponse([
            'ok' => true,
            'publicId' => $rating->getPublicIdString(),
            'score' => $score,
        ], 201);
    }

    private function disableTenantFilter(): void
    {
        $filters = $this->em->getFilters();
        if ($filters->isEnabled('customer_tenant')) {
            $filters->disable('customer_tenant');
        }
    }
}
