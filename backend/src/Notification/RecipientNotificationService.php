<?php

declare(strict_types=1);

namespace App\Notification;

use App\Domain\Route\Model\Route;
use App\Domain\Route\Model\RouteStop;
use App\Enum\NotificationTriggerType;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class RecipientNotificationService
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Send ETA notifications to all recipients when a route starts.
     */
    public function notifyRouteStarted(Route $route): void
    {
        $stops = $this->em->createQueryBuilder()
            ->select('s')
            ->from(RouteStop::class, 's')
            ->where('s.route = :route')
            ->setParameter('route', $route)
            ->orderBy('s.sequence', 'ASC')
            ->getQuery()
            ->getResult();

        /** @var RouteStop $stop */
        foreach ($stops as $stop) {
            if ($stop->isOrigin()) {
                continue;
            }

            $shipment = $stop->getShipment();
            if ($shipment === null || !$this->hasPhone($stop)) {
                continue;
            }

            $this->dispatcher->dispatchForShipment($shipment, NotificationTriggerType::OutForDelivery);
        }
    }

    /**
     * Send approaching notification when driver is nearby.
     */
    public function notifyApproaching(RouteStop $stop): void
    {
        if (!$this->hasPhone($stop)) {
            return;
        }

        $shipment = $stop->getShipment();
        if ($shipment === null) {
            return;
        }

        $this->dispatcher->dispatchForShipment($shipment, NotificationTriggerType::PresenceCheck);
    }

    /**
     * Send delivery confirmation with rating link.
     */
    public function notifyDelivered(RouteStop $stop): void
    {
        if (!$this->hasPhone($stop)) {
            return;
        }

        $shipment = $stop->getShipment();
        if ($shipment === null) {
            return;
        }

        $this->dispatcher->dispatchForShipment($shipment, NotificationTriggerType::Delivered);
    }

    private function hasPhone(RouteStop $stop): bool
    {
        $shipment = $stop->getShipment();
        if ($shipment === null) {
            return false;
        }

        $phone = $shipment->getRecipientPhone() ?? $stop->getRecipientPhone();

        return $phone !== null && $phone !== '';
    }
}
