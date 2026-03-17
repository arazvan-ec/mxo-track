<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Domain\Route\Event\RouteOptimized;
use App\Entity\Route;
use App\Repository\RouteRepository;
use App\Service\WebPushService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Sends a push notification to the driver when their route is reoptimized.
 */
final readonly class PushNotifyReoptimizeSubscriber
{
    public function __construct(
        private RouteRepository $routeRepo,
        private WebPushService $pushService,
        private LoggerInterface $logger,
    ) {}

    #[AsEventListener]
    public function onRouteOptimized(RouteOptimized $event): void
    {
        $route = $this->routeRepo->findOneByPublicId($event->routePublicId);

        if (!$route instanceof Route) {
            $this->logger->warning('PushNotifyReoptimizeSubscriber: route {id} not found.', [
                'id' => $event->routePublicId,
            ]);

            return;
        }

        $driver = $route->getDriver();

        if ($driver === null) {
            return;
        }

        $routeName = $route->getName();

        $this->pushService->sendToDriver(
            $driver,
            'Ruta actualizada',
            sprintf('Tu ruta %s ha sido reoptimizada. Revisa el nuevo orden de paradas.', $routeName),
            [
                'type' => 'route_reoptimized',
                'route_public_id' => $event->routePublicId,
                'improvement_percent' => $event->improvementPercent,
            ],
        );
    }
}
