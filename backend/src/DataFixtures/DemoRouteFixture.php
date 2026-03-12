<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Route;
use App\Entity\RouteStop;
use App\Service\DemoScenarioBuilder;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

final class DemoRouteFixture extends Fixture implements DependentFixtureInterface
{
    public function __construct(private readonly DemoScenarioBuilder $scenarioBuilder)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $result = $this->scenarioBuilder->buildScenario();

        $manager->persist($result->customer);
        $manager->persist($result->warehouse);
        $manager->persist($result->customerUser);

        foreach ($result->vehicles as $vehicle) {
            $manager->persist($vehicle);
        }
        foreach ($result->drivers as $driver) {
            $manager->persist($driver);
        }

        // Create a route with all shipments assigned as stops
        $route = new Route('Ruta Madrid #1');
        $route->setDriver($result->drivers[0]);
        $route->setVehicle($result->vehicles[0]);
        $route->setCustomer($result->customer);
        $route->setOriginLocation($result->warehouse);
        $manager->persist($route);

        $originStop = new RouteStop($route, 0, $result->warehouse->getAddress());
        $originStop->setLatitude($result->warehouse->getLatitude());
        $originStop->setLongitude($result->warehouse->getLongitude());
        $originStop->setOrigin(true);
        $manager->persist($originStop);

        foreach ($result->shipments as $i => $shipment) {
            $manager->persist($shipment);

            $routeStop = new RouteStop($route, $i + 1, $shipment->getAddress());
            $routeStop->setLatitude($shipment->getLatitude());
            $routeStop->setLongitude($shipment->getLongitude());
            $routeStop->setRecipientName($shipment->getRecipientName());
            $routeStop->setRecipientPhone($shipment->getRecipientPhone());
            $routeStop->setShipment($shipment);
            $manager->persist($routeStop);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [AdminUserFixture::class];
    }
}
