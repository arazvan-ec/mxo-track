<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Customer;
use App\Entity\CustomerIntegration;
use App\Provider\ServiceType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class CustomerIntegrationFixture extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        // Find the demo customer created by AppFixtures
        $customer = $manager->getRepository(Customer::class)->findOneBy(['name' => 'Cliente Demo']);

        if ($customer === null) {
            return;
        }

        // Demo setup: greedy optimizer + google_directions routing (uses global API key)
        $manager->persist(new CustomerIntegration(
            $customer,
            ServiceType::RouteOptimizer,
            'greedy',
            [],
            true,
            0,
        ));

        $manager->persist(new CustomerIntegration(
            $customer,
            ServiceType::RoutingEngine,
            'google_directions',
            [],
            true,
            0,
        ));

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [AppFixtures::class];
    }
}
