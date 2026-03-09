<?php

declare(strict_types=1);

namespace App\EventListener\Domain;

use App\Domain\Event\StopDelivered;
use App\Domain\Event\StopExceptionReported;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Logs delivery and exception events for observability.
 * Note: The detailed AuditLog entity write is done inside DeliveryService
 * (it needs request context). This listener handles any additional
 * cross-cutting audit concerns (e.g., external audit trail, analytics).
 */
final readonly class AuditDeliveryListener
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    #[AsEventListener]
    public function onStopDelivered(StopDelivered $event): void
    {
        $this->logger->info('Stop delivered', [
            'stop_public_id' => $event->stopPublicId,
            'shipment_public_id' => $event->shipmentPublicId,
            'route_public_id' => $event->routePublicId,
            'driver_user_id' => $event->driverUserId,
            'pod_public_id' => $event->podPublicId,
        ]);
    }

    #[AsEventListener]
    public function onStopExceptionReported(StopExceptionReported $event): void
    {
        $this->logger->info('Stop exception reported', [
            'stop_public_id' => $event->stopPublicId,
            'shipment_public_id' => $event->shipmentPublicId,
            'route_public_id' => $event->routePublicId,
            'driver_user_id' => $event->driverUserId,
            'reason' => $event->reason->value,
            'notes' => $event->notes,
        ]);
    }
}
