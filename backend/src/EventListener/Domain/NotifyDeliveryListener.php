<?php

declare(strict_types=1);

namespace App\EventListener\Domain;

use App\Domain\Event\StopDelivered;
use App\Domain\Event\StopExceptionReported;
use App\Entity\Route;
use App\Repository\RouteRepository;
use App\Service\WebhookDispatcher;
use App\Service\WebhookNotificationService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Triggers webhook notifications when delivery events occur.
 *
 * Sends to both the legacy per-customer webhook URL (WebhookNotificationService)
 * and to all registered WebhookEndpoint entities (WebhookDispatcher).
 */
final readonly class NotifyDeliveryListener
{
    public function __construct(
        private WebhookNotificationService $webhookService,
        private WebhookDispatcher $webhookDispatcher,
        private RouteRepository $routeRepo,
    ) {}

    #[AsEventListener]
    public function onStopDelivered(StopDelivered $event): void
    {
        $route = $this->routeRepo->findOneByPublicId($event->routePublicId);
        if (!$route instanceof Route || $route->getCustomer() === null) {
            return;
        }

        $payload = [
            'stop_public_id' => $event->stopPublicId,
            'shipment_public_id' => $event->shipmentPublicId,
            'route_public_id' => $event->routePublicId,
            'pod_public_id' => $event->podPublicId,
            'occurred_at' => $event->occurredAt->format(\DATE_ATOM),
        ];

        $this->webhookService->sendWebhook($route->getCustomer(), 'shipment.delivered', $payload);
        $this->webhookDispatcher->dispatch($route->getCustomer(), 'shipment.delivered', $payload);
    }

    #[AsEventListener]
    public function onStopExceptionReported(StopExceptionReported $event): void
    {
        $route = $this->routeRepo->findOneByPublicId($event->routePublicId);
        if (!$route instanceof Route || $route->getCustomer() === null) {
            return;
        }

        $payload = [
            'stop_public_id' => $event->stopPublicId,
            'shipment_public_id' => $event->shipmentPublicId,
            'route_public_id' => $event->routePublicId,
            'reason' => $event->reason->value,
            'notes' => $event->notes,
            'occurred_at' => $event->occurredAt->format(\DATE_ATOM),
        ];

        $this->webhookService->sendWebhook($route->getCustomer(), 'shipment.exception', $payload);
        $this->webhookDispatcher->dispatch($route->getCustomer(), 'shipment.exception', $payload);
    }
}
