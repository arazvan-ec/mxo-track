<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\DeliverySlot;
use App\Entity\Shipment;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class DeliverySlotService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Create proposed delivery slots for a shipment.
     *
     * @param array<array{date: \DateTimeImmutable, start: \DateTimeImmutable, end: \DateTimeImmutable}> $timeWindows
     * @return DeliverySlot[]
     */
    public function proposeSlots(Shipment $shipment, array $timeWindows): array
    {
        $slots = [];

        foreach ($timeWindows as $window) {
            $slot = new DeliverySlot(
                $shipment,
                $window['date'],
                $window['start'],
                $window['end'],
            );
            $this->entityManager->persist($slot);
            $slots[] = $slot;
        }

        $this->entityManager->flush();

        $this->logger->info('Proposed {count} delivery slots for shipment {shipment}', [
            'count' => count($slots),
            'shipment' => $shipment->getReference(),
        ]);

        return $slots;
    }

    public function selectSlot(DeliverySlot $slot, string $recipientPhone): void
    {
        if ($slot->getStatus() !== DeliverySlot::STATUS_PROPOSED) {
            throw new \LogicException(sprintf(
                'Cannot select slot in status "%s", expected "%s"',
                $slot->getStatus(),
                DeliverySlot::STATUS_PROPOSED,
            ));
        }

        // Expire other proposed slots for the same shipment
        $this->expireOtherSlots($slot);

        $slot->select($recipientPhone);
        $this->entityManager->flush();

        $this->logger->info('Delivery slot {slot} selected for shipment {shipment}', [
            'slot' => $slot->getPublicIdString(),
            'shipment' => $slot->getShipment()->getReference(),
        ]);
    }

    public function confirmSlot(DeliverySlot $slot): void
    {
        if ($slot->getStatus() !== DeliverySlot::STATUS_SELECTED) {
            throw new \LogicException(sprintf(
                'Cannot confirm slot in status "%s", expected "%s"',
                $slot->getStatus(),
                DeliverySlot::STATUS_SELECTED,
            ));
        }

        $slot->confirm();
        $this->entityManager->flush();

        $this->logger->info('Delivery slot {slot} confirmed', [
            'slot' => $slot->getPublicIdString(),
        ]);
    }

    /**
     * @return DeliverySlot[]
     */
    public function getAvailableSlots(Shipment $shipment): array
    {
        return $this->entityManager->getRepository(DeliverySlot::class)->findBy([
            'shipment' => $shipment,
            'status' => DeliverySlot::STATUS_PROPOSED,
        ]);
    }

    private function expireOtherSlots(DeliverySlot $selectedSlot): void
    {
        $otherSlots = $this->entityManager->getRepository(DeliverySlot::class)->findBy([
            'shipment' => $selectedSlot->getShipment(),
            'status' => DeliverySlot::STATUS_PROPOSED,
        ]);

        foreach ($otherSlots as $otherSlot) {
            if ($otherSlot->getId() !== $selectedSlot->getId()) {
                $otherSlot->expire();
            }
        }
    }
}
