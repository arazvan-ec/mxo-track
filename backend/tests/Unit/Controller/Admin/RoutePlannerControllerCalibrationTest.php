<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\Admin;

use App\Controller\Admin\RoutePlannerController;
use App\Service\ServiceTimeCalibrationService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(RoutePlannerController::class)]
final class RoutePlannerControllerCalibrationTest extends TestCase
{
    #[Test]
    public function calibrations_returns_service_time_data(): void
    {
        $calibrationService = $this->createMock(ServiceTimeCalibrationService::class);
        $calibrationService->expects(self::once())
            ->method('getCalibratedServiceTimes')
            ->with(42, 50, 2)
            ->willReturn([
                ['address' => '123 Main St', 'avgSeconds' => 300, 'sampleCount' => 5, 'minSeconds' => 200, 'maxSeconds' => 400],
                ['address' => '456 Oak Ave', 'avgSeconds' => 450, 'sampleCount' => 3, 'minSeconds' => 300, 'maxSeconds' => 600],
            ]);

        $controller = $this->getMockBuilder(RoutePlannerController::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $request = new Request(['customer_id' => '42']);

        $response = $controller->calibrations($request, $calibrationService);

        self::assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        self::assertCount(2, $data);
        self::assertSame('123 Main St', $data[0]['address']);
        self::assertSame(300, $data[0]['avgSeconds']);
    }

    #[Test]
    public function calibrations_requires_customer_id(): void
    {
        $calibrationService = $this->createMock(ServiceTimeCalibrationService::class);
        $calibrationService->expects(self::never())->method('getCalibratedServiceTimes');

        $controller = $this->getMockBuilder(RoutePlannerController::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $request = new Request();

        $response = $controller->calibrations($request, $calibrationService);

        self::assertSame(400, $response->getStatusCode());
    }
}
