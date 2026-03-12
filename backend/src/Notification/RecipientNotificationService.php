<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\Route;
use App\Entity\RouteStop;
use App\Notification\Message\SendRecipientNotificationMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class RecipientNotificationService
{
    public function __construct(
        private readonly MessageBusInterface $bus,
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

        $customerId = $route->getCustomer()?->getId();

        /** @var RouteStop $stop */
        foreach ($stops as $stop) {
            if ($stop->isOrigin()) {
                continue;
            }

            if (!$this->hasPhone($stop)) {
                continue;
            }

            $this->bus->dispatch(new SendRecipientNotificationMessage(
                $stop->getId(),
                'route_started',
                $customerId,
            ));
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

        $customerId = $stop->getRoute()->getCustomer()?->getId();

        $this->bus->dispatch(new SendRecipientNotificationMessage(
            $stop->getId(),
            'approaching',
            $customerId,
        ));
    }

    /**
     * Send delivery confirmation with rating link.
     */
    public function notifyDelivered(RouteStop $stop): void
    {
        if (!$this->hasPhone($stop)) {
            return;
        }

        $customerId = $stop->getRoute()->getCustomer()?->getId();

        $this->bus->dispatch(new SendRecipientNotificationMessage(
            $stop->getId(),
            'delivered',
            $customerId,
        ));
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
