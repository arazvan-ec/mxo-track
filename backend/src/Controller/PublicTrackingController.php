<?php

declare(strict_types=1);

namespace App\Controller;

use App\Application\Tracking\PublicTrackingService;
use App\Notification\DeliveryRatingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
}
