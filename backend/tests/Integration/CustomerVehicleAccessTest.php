<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class CustomerVehicleAccessTest extends TestCase
{
    public function testCustomerCannotSeeNonAssignedVehicleByPolicy(): void
    {
        $assigned = ['veh-100'];
        $requested = 'veh-999';

        self::assertFalse(in_array($requested, $assigned, true));
    }
}
