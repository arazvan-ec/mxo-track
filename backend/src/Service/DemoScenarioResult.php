<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Customer;
use App\Entity\CustomerLocation;
use App\Domain\Shipment\Model\Shipment;
use App\Entity\User;
use App\Entity\Vehicle;

final readonly class DemoScenarioResult
{
    /**
     * @param Vehicle[]  $vehicles
     * @param User[]     $drivers
     * @param Shipment[] $shipments
     */
    public function __construct(
        public Customer $customer,
        public CustomerLocation $warehouse,
        public array $vehicles,
        public array $drivers,
        public array $shipments,
        public User $customerUser,
    ) {}
}
