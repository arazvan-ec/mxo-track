<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Domain\Route\Event\RouteStarted;
use App\Entity\Route;
use App\Notification\RecipientNotificationService;
use App\Repository\RouteRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Listens for route activation and triggers ETA notifications to delivery recipients.
 */
final readonly class RouteActivatedNotificationSubscriber
{
    public function __construct(
        private RecipientNotificationService $notificationService,
        private RouteRepository $routeRepository,
        private LoggerInterface $logger,
    ) {
    }

    #[AsEventListener]
    public function onRouteStarted(RouteStarted $event): void
    {
        $route = $this->routeRepository->findOneByPublicId($event->routePublicId);

        if (!$route instanceof Route) {
            $this->logger->warning('RouteActivatedNotificationSubscriber: route {id} not found', [
                'id' => $event->routePublicId,
            ]);

            return;
        }

        try {
            $this->notificationService->notifyRouteStarted($route);

            $this->logger->info('ETA notifications sent for route {id}', [
                'id' => $event->routePublicId,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send ETA notifications for route {id}: {error}', [
                'id' => $event->routePublicId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
