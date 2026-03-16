<?php

declare(strict_types=1);

namespace App\EventListener\Domain;

use App\Domain\Route\Event\RouteCompleted;
use App\Domain\Route\Event\RouteStarted;
use App\Domain\Event\StopDelivered;
use App\Domain\Event\StopExceptionReported;
use App\Entity\Route;
use App\Repository\RouteRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Throwable;

/**
 * Publishes route progress updates to Mercure when stops are delivered/excepted
 * or when routes start/complete.
 */
final readonly class MercureRouteProgressListener
{
    public function __construct(
        private HubInterface $hub,
        private RouteRepository $routeRepo,
    ) {}

    #[AsEventListener]
    public function onStopDelivered(StopDelivered $event): void
    {
        $this->publishRouteUpdate($event->routePublicId, 'stop_delivered', [
            'stop_public_id' => $event->stopPublicId,
        ]);
    }

    #[AsEventListener]
    public function onStopExceptionReported(StopExceptionReported $event): void
    {
        $this->publishRouteUpdate($event->routePublicId, 'stop_exception', [
            'stop_public_id' => $event->stopPublicId,
            'reason' => $event->reason->value,
        ]);
    }

    #[AsEventListener]
    public function onRouteStarted(RouteStarted $event): void
    {
        $this->publishRouteUpdate($event->routePublicId, 'route_started');
    }

    #[AsEventListener]
    public function onRouteCompleted(RouteCompleted $event): void
    {
        $this->publishRouteUpdate($event->routePublicId, 'route_completed');
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function publishRouteUpdate(string $routePublicId, string $type, array $extra = []): void
    {
        $route = $this->routeRepo->findOneByPublicId($routePublicId);
        if (!$route instanceof Route) {
            return;
        }

        $customerId = $route->getCustomer()?->getId();
        if ($customerId === null) {
            return;
        }

        try {
            $this->hub->publish(new Update(
                sprintf('/customers/%s/routes', $customerId),
                json_encode(array_merge([
                    'type' => $type,
                    'route_public_id' => $routePublicId,
                ], $extra), JSON_THROW_ON_ERROR),
            ));
        } catch (Throwable) {
            // Don't break the flow on Mercure failure
        }
    }
}
