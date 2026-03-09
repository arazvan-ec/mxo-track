<?php

declare(strict_types=1);

namespace App\Controller;

use App\Application\Tracking\PublicTrackingService;
use App\Entity\DeliverySlot;
use App\Entity\ShipmentEvent;
use App\Enum\ShipmentEventType;
use App\Notification\DeliverySlotService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PublicTrackingController extends AbstractController
{
    public function __construct(
        private readonly PublicTrackingService $trackingService,
        private readonly DeliverySlotService $deliverySlotService,
        private readonly EntityManagerInterface $entityManager,
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
