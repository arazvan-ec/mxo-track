<?php

declare(strict_types=1);

namespace App\Tests\Unit\View;

use App\View\MapViewData;
use App\View\MapViewOptions;
use App\View\RouteViewData;
use App\View\StopViewData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MapViewData::class)]
#[CoversClass(RouteViewData::class)]
#[CoversClass(StopViewData::class)]
#[CoversClass(MapViewOptions::class)]
final class MapViewDataTest extends TestCase
{
    #[Test]
    public function stopViewDataSerializesToArray(): void
    {
        $stop = new StopViewData(
            sequence: 1,
            address: 'Calle Mayor 1',
            recipientName: 'Juan',
            recipientPhone: '600000000',
            lat: 40.416775,
            lng: -3.703790,
            status: 'PENDING',
            isOrigin: false,
        );

        $array = $stop->toArray();

        self::assertSame(1, $array['sequence']);
        self::assertSame('Calle Mayor 1', $array['address']);
        self::assertSame('Juan', $array['recipientName']);
        self::assertSame('PENDING', $array['status']);
        self::assertFalse($array['isOrigin']);
        self::assertArrayNotHasKey('deliveredAt', $array);
        self::assertArrayNotHasKey('exceptionCode', $array);
    }

    #[Test]
    public function stopViewDataIncludesDeliveryInfo(): void
    {
        $stop = new StopViewData(
            sequence: 2,
            address: 'Gran Via 50',
            recipientName: null,
            recipientPhone: null,
            lat: 40.42,
            lng: -3.70,
            status: 'DELIVERED',
            isOrigin: false,
            deliveredAt: '2026-03-13T10:00:00+00:00',
        );

        $array = $stop->toArray();

        self::assertSame('DELIVERED', $array['status']);
        self::assertSame('2026-03-13T10:00:00+00:00', $array['deliveredAt']);
    }

    #[Test]
    public function routeViewDataSerializesToArray(): void
    {
        $stop = new StopViewData(
            sequence: 1,
            address: 'Test',
            recipientName: null,
            recipientPhone: null,
            lat: 40.0,
            lng: -3.0,
            status: 'PENDING',
            isOrigin: false,
        );

        $routeView = new RouteViewData(
            publicId: 'abc123',
            name: 'Ruta 1',
            color: '#3b82f6',
            vehicleName: 'Furgoneta A',
            driverName: null,
            status: 'PLANNED',
            stops: [$stop],
            polyline: 'encoded_line',
            metrics: ['distanceAfterKm' => 35.0],
        );

        $array = $routeView->toArray();

        self::assertSame('abc123', $array['publicId']);
        self::assertSame('Ruta 1', $array['name']);
        self::assertSame('#3b82f6', $array['color']);
        self::assertSame('encoded_line', $array['polyline']);
        self::assertCount(1, $array['stops']);
        self::assertSame(35.0, $array['metrics']['distanceAfterKm']);
        self::assertArrayNotHasKey('comparisonPolyline', $array);
    }

    #[Test]
    public function mapViewDataSerializesToArrayAndJson(): void
    {
        $options = new MapViewOptions(showOptimizationMetrics: true);

        $routeView = new RouteViewData(
            publicId: 'route1',
            name: 'Test',
            color: '#10b981',
            vehicleName: null,
            driverName: null,
            status: 'PLANNED',
            stops: [],
        );

        $mapView = new MapViewData(
            routes: [$routeView],
            options: $options,
            globalMetrics: ['totalDistanceKm' => 50.0],
            mercureTopic: '/routes/route1/view/admin',
        );

        $array = $mapView->toArray();

        self::assertArrayHasKey('routes', $array);
        self::assertCount(1, $array['routes']);
        self::assertSame(50.0, $array['globalMetrics']['totalDistanceKm']);
        self::assertSame('/routes/route1/view/admin', $array['mercureTopic']);

        // JSON
        $json = $mapView->toJson();
        $decoded = json_decode($json, true);
        self::assertIsArray($decoded);
        self::assertArrayHasKey('routes', $decoded);
    }

    #[Test]
    public function emptyMapViewDataSerializesCorrectly(): void
    {
        $options = new MapViewOptions();
        $mapView = new MapViewData(routes: [], options: $options);

        $array = $mapView->toArray();

        self::assertSame([], $array['routes']);
        self::assertArrayNotHasKey('origin', $array);
        self::assertArrayNotHasKey('globalMetrics', $array);
        self::assertArrayNotHasKey('mercureTopic', $array);
    }

    #[Test]
    public function mapViewOptionsDefaultValues(): void
    {
        $options = new MapViewOptions();

        self::assertFalse($options->showOptimizationMetrics);
        self::assertFalse($options->showTimingBreakdown);
        self::assertFalse($options->showVehicleTracking);
        self::assertTrue($options->showStopStatus);
        self::assertFalse($options->showCapacityValidation);
        self::assertFalse($options->showOriginalOrder);
        self::assertTrue($options->showPolylines);
        self::assertFalse($options->showOptimizationLog);
        self::assertNull($options->comparisonMode);
        self::assertNull($options->vehiclePublicId);
    }

    #[Test]
    public function mapViewDataIncludesVehicleDataWhenTrackingEnabled(): void
    {
        $options = new MapViewOptions(
            showVehicleTracking: true,
            vehiclePublicId: 'veh-abc123',
            vehiclePosition: ['lat' => 40.42, 'lng' => -3.70, 'speed' => 45.0, 'course' => 180.0],
        );

        $routeView = new RouteViewData(
            publicId: 'route1',
            name: 'Test',
            color: '#10b981',
            vehicleName: null,
            driverName: null,
            status: 'ACTIVE',
            stops: [],
        );

        $mapView = new MapViewData(
            routes: [$routeView],
            options: $options,
            mercureUrl: 'https://mercure.example.com/.well-known/mercure',
        );

        $array = $mapView->toArray();

        self::assertSame('veh-abc123', $array['vehiclePublicId']);
        self::assertSame(40.42, $array['vehiclePosition']['lat']);
        self::assertSame(-3.70, $array['vehiclePosition']['lng']);
        self::assertSame('https://mercure.example.com/.well-known/mercure', $array['mercureUrl']);
    }

    #[Test]
    public function withMercureUrlReturnsNewInstanceWithUrl(): void
    {
        $options = new MapViewOptions();
        $original = new MapViewData(routes: [], options: $options, mercureTopic: '/routes/x/view/customer');

        $withUrl = $original->withMercureUrl('https://mercure.example.com/.well-known/mercure');

        self::assertNotSame($original, $withUrl);
        self::assertNull($original->mercureUrl);
        self::assertSame('https://mercure.example.com/.well-known/mercure', $withUrl->mercureUrl);
        self::assertSame('/routes/x/view/customer', $withUrl->mercureTopic);

        $array = $withUrl->toArray();
        self::assertSame('https://mercure.example.com/.well-known/mercure', $array['mercureUrl']);
    }

    #[Test]
    public function mapViewDataExcludesVehicleDataWhenTrackingDisabled(): void
    {
        $options = new MapViewOptions(showVehicleTracking: false);

        $mapView = new MapViewData(
            routes: [],
            options: $options,
        );

        $array = $mapView->toArray();

        self::assertArrayNotHasKey('vehiclePublicId', $array);
        self::assertArrayNotHasKey('vehiclePosition', $array);
        self::assertArrayNotHasKey('mercureUrl', $array);
    }

    #[Test]
    public function routeViewDataWithAllOptionalFields(): void
    {
        $routeView = new RouteViewData(
            publicId: 'route1',
            name: 'Full Route',
            color: '#f59e0b',
            vehicleName: 'Van 1',
            driverName: 'Carlos',
            status: 'ACTIVE',
            stops: [],
            polyline: 'line',
            metrics: ['savings' => 30.0],
            timing: ['totalTimeMinutes' => 90],
            validation: ['valid' => true],
            originalStops: [['seq' => 0, 'address' => 'A']],
            comparisonPolyline: 'actual_line',
        );

        $array = $routeView->toArray();

        self::assertSame('line', $array['polyline']);
        self::assertSame('actual_line', $array['comparisonPolyline']);
        self::assertSame(30.0, $array['metrics']['savings']);
        self::assertSame(90, $array['timing']['totalTimeMinutes']);
    }
}
