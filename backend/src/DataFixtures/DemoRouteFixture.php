<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Customer;
use App\Entity\CustomerLocation;
use App\Entity\Route;
use App\Entity\RouteStop;
use App\Entity\Shipment;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Enum\UserRole;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class DemoRouteFixture extends Fixture implements DependentFixtureInterface
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

    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $customer = new Customer('Mxo almacen #1');
        $customer->setAddress('Polígono Industrial de Villaverde, Madrid');
        $customer->setContactPhone('910000001');
        $manager->persist($customer);

        $warehouse = new CustomerLocation($customer, 'Almacen Villaverde', 'Poligono Industrial de Villaverde, Madrid');
        $warehouse->setLatitude(40.3460);
        $warehouse->setLongitude(-3.6970);
        $warehouse->setDefault(true);
        $manager->persist($warehouse);

        $vehicle = new Vehicle('Mxo vehicle #1');
        $vehicle->setTraccarDeviceId(1002);
        $manager->persist($vehicle);

        $customerUser = new User('cliente1@mxo.local');
        $customerUser->setName('Mxo almacen #1');
        $customerUser->assignRole(UserRole::CUSTOMER);
        $customerUser->setPassword($this->passwordHasher->hashPassword($customerUser, 'contraseña'));
        $customerUser->setActive(true);
        $customerUser->setCustomer($customer);
        $manager->persist($customerUser);

        $driver = new User('driver1@mxo.local');
        $driver->setName('Mxo driver #1');
        $driver->assignRole(UserRole::DRIVER);
        $driver->setPassword($this->passwordHasher->hashPassword($driver, 'contraseña'));
        $driver->setActive(true);
        $driver->setCustomer($customer);
        $manager->persist($driver);

        $route = new Route('Ruta Madrid #1');
        $route->setDriver($driver);
        $route->setVehicle($vehicle);
        $route->setCustomer($customer);
        $route->setOriginLocation($warehouse);
        $manager->persist($route);

        $originStop = new RouteStop($route, 0, $warehouse->getAddress());
        $originStop->setLatitude($warehouse->getLatitude());
        $originStop->setLongitude($warehouse->getLongitude());
        $originStop->setOrigin(true);
        $manager->persist($originStop);

        foreach (self::STOPS as $i => $stop) {
            [$address, $lat, $lng, $recipientName, $recipientPhone] = $stop;
            $seq = $i + 1;

            $shipment = new Shipment(sprintf('MXO-SHP-%04d', $seq), $customer);
            $shipment->setRecipientName($recipientName);
            $shipment->setRecipientPhone($recipientPhone);
            $shipment->setAddress($address);
            $shipment->setLatitude($lat);
            $shipment->setLongitude($lng);
            $manager->persist($shipment);

            $routeStop = new RouteStop($route, $seq, $address);
            $routeStop->setLatitude($lat);
            $routeStop->setLongitude($lng);
            $routeStop->setRecipientName($recipientName);
            $routeStop->setRecipientPhone($recipientPhone);
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
