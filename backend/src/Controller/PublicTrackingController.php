<?php

declare(strict_types=1);

namespace App\Controller;

use App\Application\Tracking\PublicTrackingService;
use App\Notification\DeliveryRatingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PublicTrackingController extends AbstractController
{
    public function __construct(
        private readonly PublicTrackingService $trackingService,
        private readonly DeliveryRatingService $ratingService,
    ) {}

    #[Route('/track/{trackingToken}', name: 'public_tracking', methods: ['GET'])]
    public function track(string $trackingToken): Response
    {
        $info = $this->trackingService->trackByToken($trackingToken);

        if ($info === null) {
            throw $this->createNotFoundException('Envio no encontrado.');
        }

        $currentStatus = $info->latestEvent?->getEventType()->value ?? 'CREATED';
        $existingRating = $this->ratingService->getRatingForShipment($info->shipment);

        return $this->render('tracking/public.html.twig', [
            'shipment' => $info->shipment,
            'events' => $info->events,
            'latestEvent' => $info->latestEvent,
            'approximatePosition' => $info->approximatePosition,
            'routeActive' => $info->routeActive,
            'currentStatus' => $currentStatus,
            'existingRating' => $existingRating,
        ]);
    }

    #[Route('/track/{trackingToken}/rate', name: 'public_tracking_rate', methods: ['POST'])]
    public function rate(string $trackingToken, Request $request): JsonResponse
    {
        $info = $this->trackingService->trackByToken($trackingToken);

        if ($info === null) {
            return new JsonResponse(['error' => 'Envio no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        $currentStatus = $info->latestEvent?->getEventType()->value ?? 'CREATED';

        if ($currentStatus !== 'DELIVERED') {
            return new JsonResponse(
                ['error' => 'Solo se puede valorar un envio entregado.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $existing = $this->ratingService->getRatingForShipment($info->shipment);

        if ($existing !== null) {
            return new JsonResponse(
                ['error' => 'Este envio ya tiene una valoracion.'],
                Response::HTTP_CONFLICT,
            );
        }

        $data = $request->toArray();
        $score = (int) ($data['score'] ?? 0);

        if ($score < 1 || $score > 5) {
            return new JsonResponse(
                ['error' => 'La puntuacion debe estar entre 1 y 5.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $comment = isset($data['comment']) && \is_string($data['comment'])
            ? trim($data['comment']) ?: null
            : null;

        $tags = isset($data['tags']) && \is_array($data['tags'])
            ? array_values(array_filter($data['tags'], 'is_string'))
            : null;

        if ($tags !== null && \count($tags) === 0) {
            $tags = null;
        }

        $rating = $this->ratingService->submitRating(
            $info->shipment,
            $score,
            $comment,
            $tags,
        );

        return new JsonResponse([
            'success' => true,
            'rating' => [
                'score' => $rating->getScore(),
                'comment' => $rating->getComment(),
                'tags' => $rating->getTags(),
            ],
        ], Response::HTTP_CREATED);
    }
}
