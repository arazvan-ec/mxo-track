<?php

declare(strict_types=1);

namespace App\DataFixtures;

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
        $result = $this->scenarioBuilder->buildScenario(200);

        $manager->persist($result->customer);
        $manager->persist($result->warehouse);
        $manager->persist($result->customerUser);

        foreach ($result->vehicles as $vehicle) {
            $manager->persist($vehicle);
        }
        foreach ($result->drivers as $driver) {
            $manager->persist($driver);
        }
        foreach ($result->shipments as $shipment) {
            $manager->persist($shipment);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [AdminUserFixture::class];
    }
}
