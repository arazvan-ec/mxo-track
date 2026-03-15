<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener\Domain;

use App\Domain\Event\DeviationDetected;
use App\Entity\Customer;
use App\Entity\RealtimeEvent;
use App\Entity\Route;
use App\EventListener\Domain\DeviationAlertListener;
use App\Repository\RouteRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeviationAlertListener::class)]
final class DeviationAlertListenerTest extends TestCase
{
    private RouteRepository $routeRepo;
    private EntityManagerInterface $em;
    private DeviationAlertListener $listener;

    protected function setUp(): void
    {
        $this->routeRepo = $this->createMock(RouteRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->listener = new DeviationAlertListener($this->routeRepo, $this->em);
    }

    #[Test]
    public function persistsRealtimeEventOnDeviation(): void
    {
        $customer = new Customer('Test Co');
        $route = new Route('Ruta Norte');
        $route->initializePublicId();
        $route->setCustomer($customer);

        $this->routeRepo->method('findOneByPublicId')->willReturn($route);

        $this->em->expects(self::once())
            ->method('persist')
            ->with(self::callback(function (RealtimeEvent $e): bool {
                return $e->getEventType() === 'deviation_detected'
                    && str_contains($e->getData()['message'], 'Ruta Norte')
                    && $e->getData()['distance_meters'] === 800.0;
            }));

        $this->em->expects(self::once())->method('flush');

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

        $this->em->expects(self::never())->method('persist');

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

        $this->em->expects(self::never())->method('persist');

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
