<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener\Domain;

use App\Domain\Event\DeviationDetected;
use App\Entity\Customer;
use App\Domain\Route\Model\Route;
use App\EventListener\Domain\DeviationAlertListener;
use App\Realtime\RealtimePublisherInterface;
use App\Realtime\SseMessage;
use App\Repository\RouteRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeviationAlertListener::class)]
final class DeviationAlertListenerTest extends TestCase
{
    private RouteRepository $routeRepo;
    private RealtimePublisherInterface $publisher;
    private DeviationAlertListener $listener;

    protected function setUp(): void
    {
        $this->routeRepo = $this->createMock(RouteRepository::class);
        $this->publisher = $this->createMock(RealtimePublisherInterface::class);
        $this->listener = new DeviationAlertListener($this->routeRepo, $this->publisher);
    }

    #[Test]
    public function publishesRealtimeEventOnDeviation(): void
    {
        $customer = new Customer('Test Co');
        $route = new Route('Ruta Norte');
        $route->initializePublicId();
        $route->setCustomer($customer);

        $this->routeRepo->method('findOneByPublicId')->willReturn($route);

        $this->publisher->expects(self::once())
            ->method('publish')
            ->with(self::callback(function (SseMessage $msg): bool {
                return $msg->type === 'deviation_detected'
                    && str_contains($msg->data['message'], 'Ruta Norte')
                    && $msg->data['distance_meters'] === 800.0;
            }));

        $event = new DeviationDetected(
            routePublicId: $route->getPublicIdString(),
            vehiclePublicId: 'VEH123',
            latitude: 40.416,
            longitude: -3.715,
            distanceMeters: 800.0,
            thresholdMeters: 500.0,
        );

        $this->listener->onDeviationDetected($event);
    }

    #[Test]
    public function noOpWhenRouteNotFound(): void
    {
        $this->routeRepo->method('findOneByPublicId')->willReturn(null);

        $this->publisher->expects(self::never())->method('publish');

        $event = new DeviationDetected(
            routePublicId: 'nonexistent',
            vehiclePublicId: 'VEH123',
            latitude: 40.416,
            longitude: -3.715,
            distanceMeters: 800.0,
            thresholdMeters: 500.0,
        );

        $this->listener->onDeviationDetected($event);
    }

    #[Test]
    public function noOpWhenRouteHasNoCustomer(): void
    {
        $route = new Route('Ruta Sin Cliente');
        $route->initializePublicId();
        // No customer set

        $this->routeRepo->method('findOneByPublicId')->willReturn($route);

        $this->publisher->expects(self::never())->method('publish');

        $event = new DeviationDetected(
            routePublicId: $route->getPublicIdString(),
            vehiclePublicId: 'VEH123',
            latitude: 40.416,
            longitude: -3.715,
            distanceMeters: 800.0,
            thresholdMeters: 500.0,
        );

        $this->listener->onDeviationDetected($event);
    }
}
