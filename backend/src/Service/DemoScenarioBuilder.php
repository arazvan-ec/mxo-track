<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Customer;
use App\Entity\CustomerLocation;
use App\Entity\Shipment;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Enum\ShipmentPriority;
use App\Enum\UserRole;
use App\Enum\VehicleSkill;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class DemoScenarioBuilder
{
    private const STOPS = [
        ['Calle Gran Vía 1, 28013 Madrid', 40.4200, -3.7025, 'María García', '612345001'],
        ['Calle de Alcalá 50, 28014 Madrid', 40.4190, -3.6950, 'Carlos López', '612345002'],
        ['Calle de Serrano 45, 28001 Madrid', 40.4260, -3.6880, 'Ana Martínez', '612345003'],
        ['Calle de Goya 30, 28001 Madrid', 40.4240, -3.6830, 'Pedro Sánchez', '612345004'],
        ['Calle de Velázquez 60, 28001 Madrid', 40.4280, -3.6850, 'Laura Fernández', '612345005'],
        ['Paseo de la Castellana 100, 28046 Madrid', 40.4500, -3.6920, 'Javier Ruiz', '612345006'],
        ['Calle de Fuencarral 80, 28004 Madrid', 40.4270, -3.7010, 'Elena Torres', '612345007'],
        ['Calle de Hortaleza 55, 28004 Madrid', 40.4250, -3.6990, 'Miguel Díaz', '612345008'],
        ['Calle del Pez 20, 28004 Madrid', 40.4230, -3.7060, 'Carmen Moreno', '612345009'],
        ['Plaza de Chueca 5, 28004 Madrid', 40.4225, -3.6975, 'Roberto Jiménez', '612345010'],
        ['Calle de Atocha 40, 28012 Madrid', 40.4120, -3.6960, 'Isabel Navarro', '612345011'],
        ['Calle de Embajadores 70, 28012 Madrid', 40.4060, -3.7020, 'Francisco Romero', '612345012'],
        ['Calle de Toledo 35, 28005 Madrid', 40.4100, -3.7080, 'Lucía Vargas', '612345013'],
        ['Calle de Segovia 15, 28005 Madrid', 40.4130, -3.7130, 'Antonio Castillo', '612345014'],
        ['Calle Mayor 70, 28013 Madrid', 40.4160, -3.7100, 'Marta Ortega', '612345015'],
        ['Calle del Arenal 25, 28013 Madrid', 40.4175, -3.7070, 'Daniel Guerrero', '612345016'],
        ['Calle de Preciados 10, 28013 Madrid', 40.4185, -3.7040, 'Sofía Medina', '612345017'],
        ['Paseo del Prado 30, 28014 Madrid', 40.4140, -3.6930, 'Alejandro Vega', '612345018'],
        ['Calle de Lope de Vega 20, 28014 Madrid', 40.4155, -3.6960, 'Patricia Herrero', '612345019'],
        ['Calle de las Huertas 40, 28014 Madrid', 40.4145, -3.6975, 'Raúl Campos', '612345020'],
        ['Calle de Santa Isabel 30, 28012 Madrid', 40.4100, -3.6950, 'Cristina Peña', '612345021'],
        ['Calle de Argumosa 15, 28012 Madrid', 40.4085, -3.6985, 'Sergio Delgado', '612345022'],
        ['Ronda de Valencia 10, 28012 Madrid', 40.4065, -3.6965, 'Beatriz Fuentes', '612345023'],
        ['Calle de Alberto Aguilera 40, 28015 Madrid', 40.4300, -3.7100, 'Andrés Reyes', '612345024'],
        ['Calle de San Bernardo 60, 28015 Madrid', 40.4260, -3.7050, 'Natalia Blanco', '612345025'],
        ['Calle de la Princesa 25, 28008 Madrid', 40.4310, -3.7150, 'Óscar Ibáñez', '612345026'],
        ['Paseo de Rosales 50, 28008 Madrid', 40.4340, -3.7200, 'Paula Aguilar', '612345027'],
        ['Calle de Ferraz 30, 28008 Madrid', 40.4290, -3.7170, 'Víctor Caballero', '612345028'],
        ['Calle de Bravo Murillo 100, 28020 Madrid', 40.4450, -3.7040, 'Adriana Parra', '612345029'],
        ['Calle de Ríos Rosas 35, 28003 Madrid', 40.4410, -3.6990, 'Rubén Molina', '612345030'],
        ['Calle de Santa Engracia 70, 28010 Madrid', 40.4370, -3.6980, 'Irene Domínguez', '612345031'],
        ['Calle de Ponzano 40, 28003 Madrid', 40.4380, -3.6960, 'Álvaro Pascual', '612345032'],
        ['Calle de Zurbano 55, 28010 Madrid', 40.4350, -3.6940, 'Claudia Herrera', '612345033'],
        ['Calle de Génova 20, 28004 Madrid', 40.4280, -3.6930, 'Manuel Crespo', '612345034'],
        ['Calle de Sagasta 15, 28004 Madrid', 40.4290, -3.6970, 'Teresa Nieto', '612345035'],
        ['Calle del Conde de Peñalver 30, 28006 Madrid', 40.4310, -3.6810, 'Jorge Gallardo', '612345036'],
        ['Calle de Narváez 40, 28009 Madrid', 40.4220, -3.6780, 'Verónica Soto', '612345037'],
        ['Calle de O\'Donnell 25, 28009 Madrid', 40.4210, -3.6760, 'Iván Carrasco', '612345038'],
        ['Calle de Ibiza 15, 28009 Madrid', 40.4200, -3.6730, 'Alicia Ramos', '612345039'],
        ['Avenida de Menéndez Pelayo 60, 28007 Madrid', 40.4150, -3.6800, 'David Prieto', '612345040'],
    ];

    /** @var array<array{string, float, float, int, list<VehicleSkill>, int}> */
    private const VEHICLES = [
        ['Furgoneta Madrid #1', 1000.0, 8.0, 50, 'FRAGILE', 1002],
        ['Camión Refrigerado #1', 3000.0, 20.0, 100, 'REFRIGERATED,HEAVY_LOAD', 1003],
        ['Moto Express #1', 30.0, 0.5, 5, 'PEDESTRIAN_ACCESS', 1004],
    ];

    private const PRIORITY_DISTRIBUTION = [
        ShipmentPriority::CRITICAL->value => 10,
        ShipmentPriority::HIGH->value => 20,
        ShipmentPriority::NORMAL->value => 50,
        ShipmentPriority::LOW->value => 20,
    ];

    private const SKILL_CONFIGS = [
        ['skills' => [], 'weightRange' => [1.0, 15.0], 'volumeRange' => [0.01, 0.5], 'parcels' => 1],
        ['skills' => [VehicleSkill::REFRIGERATED], 'weightRange' => [5.0, 50.0], 'volumeRange' => [0.1, 1.0], 'parcels' => 2],
        ['skills' => [VehicleSkill::FRAGILE], 'weightRange' => [0.5, 5.0], 'volumeRange' => [0.01, 0.2], 'parcels' => 1],
        ['skills' => [VehicleSkill::HEAVY_LOAD], 'weightRange' => [50.0, 200.0], 'volumeRange' => [0.5, 3.0], 'parcels' => 3],
        ['skills' => [VehicleSkill::PEDESTRIAN_ACCESS], 'weightRange' => [0.5, 5.0], 'volumeRange' => [0.01, 0.1], 'parcels' => 1],
    ];

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {}

    public function buildScenario(int $shipmentCount = 40): DemoScenarioResult
    {
        $customer = $this->createCustomer();
        $warehouse = $this->createWarehouse($customer);
        $vehicles = $this->createVehicles();
        $drivers = $this->createDrivers($customer);
        $customerUser = $this->createCustomerUser($customer);
        $shipments = $this->createShipments($customer, $shipmentCount);

        return new DemoScenarioResult(
            customer: $customer,
            warehouse: $warehouse,
            vehicles: $vehicles,
            drivers: $drivers,
            shipments: $shipments,
            customerUser: $customerUser,
        );
    }

    private function createCustomer(): Customer
    {
        $customer = new Customer('Logística Express Madrid');
        $customer->setAddress('Polígono Industrial de Villaverde, Madrid');
        $customer->setContactPhone('910000001');

        return $customer;
    }

    private function createWarehouse(Customer $customer): CustomerLocation
    {
        $warehouse = new CustomerLocation($customer, 'Almacén Villaverde', 'Polígono Industrial de Villaverde, Madrid');
        $warehouse->setLatitude(40.3460);
        $warehouse->setLongitude(-3.6970);
        $warehouse->setDefault(true);

        return $warehouse;
    }

    /** @return Vehicle[] */
    private function createVehicles(): array
    {
        $vehicles = [];

        foreach (self::VEHICLES as [$name, $maxWeight, $maxVolume, $maxParcels, $skillsStr, $traccarId]) {
            $vehicle = new Vehicle($name);
            $vehicle->setMaxWeightKg($maxWeight);
            $vehicle->setMaxVolumeM3($maxVolume);
            $vehicle->setMaxParcels($maxParcels);
            $vehicle->setTraccarDeviceId($traccarId);

            $skills = array_filter(array_map(
                static fn (string $s): ?VehicleSkill => VehicleSkill::tryFrom(
                    constant(VehicleSkill::class . '::' . trim($s))->value,
                ),
                explode(',', $skillsStr),
            ));
            $vehicle->setSkills($skills);

            $vehicles[] = $vehicle;
        }

        return $vehicles;
    }

    /** @return User[] */
    private function createDrivers(Customer $customer): array
    {
        $drivers = [];

        foreach (['driver1@demo.local' => 'Demo Driver #1', 'driver2@demo.local' => 'Demo Driver #2'] as $email => $name) {
            $driver = new User($email);
            $driver->setName($name);
            $driver->assignRole(UserRole::DRIVER);
            $driver->setPassword($this->passwordHasher->hashPassword($driver, 'demo1234'));
            $driver->setActive(true);
            $driver->setCustomer($customer);
            $drivers[] = $driver;
        }

        return $drivers;
    }

    private function createCustomerUser(Customer $customer): User
    {
        $user = new User('cliente@demo.local');
        $user->setName('Logística Express Madrid');
        $user->assignRole(UserRole::CUSTOMER);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'demo1234'));
        $user->setActive(true);
        $user->setCustomer($customer);

        return $user;
    }

    /** @return Shipment[] */
    private function createShipments(Customer $customer, int $count): array
    {
        $shipments = [];
        $stops = self::STOPS;
        $stopCount = \count($stops);

        for ($i = 0; $i < $count; $i++) {
            $stop = $stops[$i % $stopCount];
            [$address, $lat, $lng, $recipientName, $recipientPhone] = $stop;

            $shipment = new Shipment(sprintf('DEMO-SHP-%04d', $i + 1), $customer);
            $shipment->setRecipientName($recipientName);
            $shipment->setRecipientPhone($recipientPhone);
            $shipment->setAddress($address);
            $shipment->setLatitude($lat);
            $shipment->setLongitude($lng);
            $shipment->setPriority($this->pickPriority($i));
            $shipment->setServiceTimeSeconds(300);

            $skillConfig = self::SKILL_CONFIGS[$i % \count(self::SKILL_CONFIGS)];
            if ($skillConfig['skills'] !== []) {
                $shipment->setRequiredSkills($skillConfig['skills']);
            }
            $shipment->setTotalWeightKg($this->randomInRange($skillConfig['weightRange']));
            $shipment->setTotalVolumeM3($this->randomInRange($skillConfig['volumeRange']));
            $shipment->setTotalParcels($skillConfig['parcels']);

            $shipments[] = $shipment;
        }

        return $shipments;
    }

    private function pickPriority(int $index): ShipmentPriority
    {
        $bucket = $index % 100;

        if ($bucket < 10) {
            return ShipmentPriority::CRITICAL;
        }
        if ($bucket < 30) {
            return ShipmentPriority::HIGH;
        }
        if ($bucket < 80) {
            return ShipmentPriority::NORMAL;
        }

        return ShipmentPriority::LOW;
    }

    /** @param array{float, float} $range */
    private function randomInRange(array $range): float
    {
        $randomizer = new \Random\Randomizer();

        return round($range[0] + $randomizer->getFloat(0.0, 1.0) * ($range[1] - $range[0]), 2);
    }
}
