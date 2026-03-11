<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Route;

use App\Application\Route\BuildRoutesResult;
use PHPUnit\Framework\TestCase;

final class BuildRoutesResultTest extends TestCase
{
    public function testToArrayIncludesStopsData(): void
    {
        $routes = [
            [
                'route' => [
                    'publicId' => 'abc123',
                    'name' => 'Ruta 1',
                    'vehicle' => 'Furgoneta',
                    'totalDistanceKm' => 25.3,
                    'estimatedDurationMinutes' => 45,
                ],
                'stopsCount' => 3,
                'stops' => [
                    ['sequence' => 0, 'address' => 'Warehouse', 'latitude' => 40.34, 'longitude' => -3.69, 'isOrigin' => true, 'recipientName' => null],
                    ['sequence' => 1, 'address' => 'Stop 1', 'latitude' => 40.42, 'longitude' => -3.70, 'isOrigin' => false, 'recipientName' => 'María'],
                    ['sequence' => 2, 'address' => 'Stop 2', 'latitude' => 40.43, 'longitude' => -3.68, 'isOrigin' => false, 'recipientName' => 'Carlos'],
                ],
                'validation' => [
                    'valid' => true,
                    'errors' => [],
                    'totalWeightKg' => 150.0,
                    'totalVolumeM3' => 1.2,
                    'totalParcels' => 5,
                    'weightUtilization' => 0.15,
                    'volumeUtilization' => 0.15,
                    'parcelUtilization' => 0.10,
                ],
            ],
        ];

        $result = new BuildRoutesResult(1, $routes);
        $array = $result->toArray();

        self::assertArrayHasKey('routes', $array);
        self::assertCount(1, $array['routes']);

        $route = $array['routes'][0];
        self::assertArrayHasKey('stops', $route);
        self::assertCount(3, $route['stops']);
        self::assertSame(40.42, $route['stops'][1]['latitude']);
        self::assertSame('María', $route['stops'][1]['recipientName']);

        self::assertArrayHasKey('validation', $route);
        self::assertSame(150.0, $route['validation']['totalWeightKg']);
        self::assertSame(0.15, $route['validation']['weightUtilization']);
    }
}
