<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\PublicIdTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'customer_vehicle')]
#[ORM\UniqueConstraint(name: 'uniq_customer_vehicle', columns: ['customer_id', 'vehicle_id'])]
#[ORM\UniqueConstraint(name: 'uniq_customer_vehicle_public_id', columns: ['public_id'])]
#[ORM\HasLifecycleCallbacks]
class CustomerVehicle
{
    use PublicIdTrait;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(name: 'customer_id', nullable: false, onDelete: 'CASCADE')]
    private Customer $customer;

    #[ORM\ManyToOne(targetEntity: Vehicle::class)]
    #[ORM\JoinColumn(name: 'vehicle_id', nullable: false, onDelete: 'CASCADE')]
    private Vehicle $vehicle;

    public function __construct(Customer $customer, Vehicle $vehicle)
    {
        $this->customer = $customer;
        $this->vehicle = $vehicle;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function getVehicle(): Vehicle
    {
        return $this->vehicle;
    }
}
