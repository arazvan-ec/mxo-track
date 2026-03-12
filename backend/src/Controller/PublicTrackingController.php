<?php

declare(strict_types=1);

namespace App\Controller;

use App\Application\Tracking\PublicTrackingService;
use App\Entity\DeliverySlot;
use App\Entity\ShipmentEvent;
use App\Enum\ShipmentEventType;
use App\Notification\DeliveryRatingService;
use App\Notification\DeliverySlotService;
use App\Notification\RescheduleConfirmedNotification;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PublicTrackingController extends AbstractController
{
    public function __construct(
        private readonly PublicTrackingService $trackingService,
        private readonly DeliverySlotService $deliverySlotService,
        private readonly DeliveryRatingService $deliveryRatingService,
        private readonly NotifierInterface $notifier,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {}

    #[Route('/track/{trackingToken}', name: 'public_tracking', methods: ['GET'])]
    public function track(string $trackingToken): Response
    {
        $info = $this->trackingService->trackByToken($trackingToken);

        if ($info === null) {
            throw $this->createNotFoundException('Envio no encontrado.');
        }

        return $this->render('tracking/public.html.twig', [
            'shipment' => $info->shipment,
            'events' => $info->events,
            'latestEvent' => $info->latestEvent,
            'approximatePosition' => $info->approximatePosition,
            'routeActive' => $info->routeActive,
        ]);
    }

    #[Route('/track/{trackingToken}/rate', name: 'public_tracking_rate_page', methods: ['GET'])]
    public function ratePage(string $trackingToken): Response
    {
        $info = $this->trackingService->trackByToken($trackingToken);

        if ($info === null) {
            throw $this->createNotFoundException('Envio no encontrado.');
        }

        $shipment = $info->shipment;

        if ($info->latestEvent === null || $info->latestEvent->getType() !== ShipmentEventType::DELIVERED) {
            $this->addFlash('error', 'Solo puedes valorar envios entregados.');

            return $this->redirectToRoute('public_tracking', ['trackingToken' => $trackingToken]);
        }

        $existingRating = $this->deliveryRatingService->getRatingForShipment($shipment);

        return $this->render('tracking/rate.html.twig', [
            'shipment' => $shipment,
            'existingRating' => $existingRating,
        ]);
    }

    #[Route('/track/{trackingToken}/rate', name: 'public_tracking_rate', methods: ['POST'])]
    public function rate(Request $request, string $trackingToken): JsonResponse
    {
        $info = $this->trackingService->trackByToken($trackingToken);

        if ($info === null) {
            return new JsonResponse(['error' => 'Envio no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        $shipment = $info->shipment;

        if ($info->latestEvent === null || $info->latestEvent->getType() !== ShipmentEventType::DELIVERED) {
            return new JsonResponse(['error' => 'Solo puedes valorar envios entregados.'], Response::HTTP_BAD_REQUEST);
        }

        $body = json_decode($request->getContent(), true);
        if (!\is_array($body)) {
            return new JsonResponse(['error' => 'JSON invalido.'], Response::HTTP_BAD_REQUEST);
        }

        $score = $body['score'] ?? null;
        if (!\is_int($score) || $score < 1 || $score > 5) {
            return new JsonResponse(['error' => 'Score debe ser un entero entre 1 y 5.'], Response::HTTP_BAD_REQUEST);
        }

        $comment = isset($body['comment']) && \is_string($body['comment']) ? mb_substr(trim($body['comment']), 0, 500) : null;
        $tags = isset($body['tags']) && \is_array($body['tags']) ? array_slice(array_filter($body['tags'], 'is_string'), 0, 5) : null;

        try {
            $rating = $this->deliveryRatingService->submitRating($shipment, $score, $comment, $tags, $shipment->getRecipientPhone());
        } catch (\LogicException) {
            return new JsonResponse(['error' => 'Ya has enviado una valoracion para este envio.'], Response::HTTP_CONFLICT);
        }

        return new JsonResponse([
            'success' => true,
            'rating' => [
                'score' => $rating->getScore(),
                'comment' => $rating->getComment(),
                'tags' => $rating->getTags(),
            ],
        ], Response::HTTP_CREATED);
    }

    #[Route('/track/{trackingToken}/reschedule', name: 'public_tracking_reschedule', methods: ['GET'])]
    public function reschedule(string $trackingToken): Response
    {
        $info = $this->trackingService->trackByToken($trackingToken);

        if ($info === null) {
            throw $this->createNotFoundException('Envio no encontrado.');
        }

        $shipment = $info->shipment;

        // Check for existing proposed slots, otherwise generate new ones
        $slots = $this->deliverySlotService->getAvailableSlots($shipment);

        if (\count($slots) === 0) {
            $slots = $this->deliverySlotService->proposeSlots($shipment, self::buildDefaultTimeWindows());
        }

        return $this->render('tracking/reschedule.html.twig', [
            'shipment' => $shipment,
            'slots' => $slots,
            'trackingToken' => $trackingToken,
        ]);
    }

    #[Route('/track/{trackingToken}/reschedule', name: 'public_tracking_reschedule_submit', methods: ['POST'])]
    public function rescheduleSubmit(Request $request, string $trackingToken): Response
    {
        $info = $this->trackingService->trackByToken($trackingToken);

        if ($info === null) {
            throw $this->createNotFoundException('Envio no encontrado.');
        }

        $shipment = $info->shipment;
        $slotPublicId = $request->request->getString('slot_id');
        $alternativeOption = $request->request->getString('alternative_option');

        // Build reschedule event payload
        $payload = [];

        if ($alternativeOption !== '') {
            // Alternative delivery option (porteria / vecino)
            $payload['alternative_option'] = $alternativeOption;
        } elseif ($slotPublicId !== '') {
            // Slot selection
            $slot = $this->entityManager->getRepository(DeliverySlot::class)->findOneBy([
                'publicId' => $slotPublicId,
                'shipment' => $shipment,
                'status' => DeliverySlot::STATUS_PROPOSED,
            ]);

            if ($slot === null) {
                $this->addFlash('error', 'La franja seleccionada ya no esta disponible.');

                return $this->redirectToRoute('public_tracking_reschedule', [
                    'trackingToken' => $trackingToken,
                ]);
            }

            $recipientPhone = $shipment->getRecipientPhone() ?? '';
            $this->deliverySlotService->selectSlot($slot, $recipientPhone);

            $payload['slot_public_id'] = $slotPublicId;
            $payload['slot_date'] = $slot->getSlotDate()->format('Y-m-d');
            $payload['slot_time_range'] = $slot->getTimeRange();
        } else {
            $this->addFlash('error', 'Selecciona una opcion de reprogramacion.');

            return $this->redirectToRoute('public_tracking_reschedule', [
                'trackingToken' => $trackingToken,
            ]);
        }

        // Create RESCHEDULE_REQUESTED event
        $event = new ShipmentEvent($shipment, ShipmentEventType::RESCHEDULE_REQUESTED, $payload);
        $this->entityManager->persist($event);
        $this->entityManager->flush();

        // Send SMS confirmation to recipient
        $recipientPhone = $shipment->getRecipientPhone();
        if ($recipientPhone !== null && $recipientPhone !== '') {
            try {
                $trackingUrl = $this->generateUrl('public_tracking', ['trackingToken' => $trackingToken], UrlGeneratorInterface::ABSOLUTE_URL);
                $slotDate = $payload['slot_date'] ?? $payload['alternative_option'] ?? '';
                $slotTimeRange = $payload['slot_time_range'] ?? '';

                $notification = new RescheduleConfirmedNotification(
                    $shipment->getRecipientName() ?? 'Cliente',
                    $slotDate,
                    $slotTimeRange,
                    $trackingUrl,
                );

                $this->notifier->send($notification, new Recipient('', $recipientPhone));
            } catch (\Throwable $e) {
                $this->logger->error('Failed to send reschedule SMS: {error}', ['error' => $e->getMessage()]);
            }
        }

        // Update delivery instructions for alternative options
        if (isset($payload['alternative_option'])) {
            $instructions = match ($payload['alternative_option']) {
                'porteria' => 'Dejar en porteria',
                'vecino' => 'Dejar con vecino',
                default => $payload['alternative_option'],
            };
            $shipment->setNotes($instructions);
            $this->entityManager->flush();
        }

        $this->addFlash('success', 'Tu entrega ha sido reprogramada correctamente.');

        return $this->redirectToRoute('public_tracking', [
            'trackingToken' => $trackingToken,
        ]);
    }

    /**
     * Build default time windows: tomorrow AM/PM, day after tomorrow AM/PM, next week AM/PM.
     *
     * @return array<array{date: \DateTimeImmutable, start: \DateTimeImmutable, end: \DateTimeImmutable}>
     */
    private static function buildDefaultTimeWindows(): array
    {
        $tomorrow = new \DateTimeImmutable('tomorrow');
        $dayAfter = new \DateTimeImmutable('+2 days');
        $nextWeek = new \DateTimeImmutable('+7 days');

        $am = [
            'start' => new \DateTimeImmutable('09:00'),
            'end' => new \DateTimeImmutable('13:00'),
        ];
        $pm = [
            'start' => new \DateTimeImmutable('14:00'),
            'end' => new \DateTimeImmutable('19:00'),
        ];

        return [
            ['date' => $tomorrow, 'start' => $am['start'], 'end' => $am['end']],
            ['date' => $tomorrow, 'start' => $pm['start'], 'end' => $pm['end']],
            ['date' => $dayAfter, 'start' => $am['start'], 'end' => $am['end']],
            ['date' => $dayAfter, 'start' => $pm['start'], 'end' => $pm['end']],
            ['date' => $nextWeek, 'start' => $am['start'], 'end' => $am['end']],
            ['date' => $nextWeek, 'start' => $pm['start'], 'end' => $pm['end']],
        ];
    }
}
