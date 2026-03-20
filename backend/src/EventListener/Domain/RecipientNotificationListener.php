<?php

declare(strict_types=1);

namespace App\EventListener\Domain;

use App\Domain\Event\StopDelivered;
use App\Domain\Event\StopExceptionReported;
use App\Domain\Shipment\Model\Shipment;
use App\Enum\NotificationTriggerType;
use App\Notification\NotificationDispatcher;
use App\Repository\ShipmentRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final readonly class RecipientNotificationListener
{
    public function __construct(
        private ShipmentRepository $shipmentRepo,
        private NotificationDispatcher $dispatcher,
    ) {}

    #[AsEventListener]
    public function onStopDelivered(StopDelivered $event): void
    {
        $shipment = $this->shipmentRepo->findOneByPublicId($event->shipmentPublicId);
        if (!$shipment instanceof Shipment) {
            return;
        }

        $this->dispatcher->dispatchForShipment($shipment, NotificationTriggerType::Delivered);
    }

    #[AsEventListener]
    public function onStopExceptionReported(StopExceptionReported $event): void
    {
        $shipment = $this->shipmentRepo->findOneByPublicId($event->shipmentPublicId);
        if (!$shipment instanceof Shipment) {
            return;
        }

        $this->dispatcher->dispatchForShipment($shipment, NotificationTriggerType::DeliveryException);
    }
}
