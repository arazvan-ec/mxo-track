<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Customer;
use App\Entity\Vehicle;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $customer = new Customer('Cliente Demo');
        $vehicle = new Vehicle('Vehículo Demo Fase2');
        $vehicle->setTraccarDeviceId(1001);

        $manager->persist($customer);
        $manager->persist($vehicle);
        $manager->flush();
    }
}
