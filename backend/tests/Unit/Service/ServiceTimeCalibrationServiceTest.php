<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\ServiceTimeCalibrationService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServiceTimeCalibrationService::class)]
final class ServiceTimeCalibrationServiceTest extends TestCase
{
    private Connection $connection;
    private ServiceTimeCalibrationService $service;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->service = new ServiceTimeCalibrationService($this->connection);
    }

    #[Test]
    public function calculates_average_service_time_per_address(): void
    {
        // DB returns pre-computed averages per address
        $result = $this->createMock(Result::class);
        $result->method('fetchAllAssociative')->willReturn([
            ['address' => '123 Main St', 'avg_seconds' => 300.0, 'sample_count' => 5, 'min_seconds' => 200.0, 'max_seconds' => 400.0],
            ['address' => '456 Oak Ave', 'avg_seconds' => 450.0, 'sample_count' => 3, 'min_seconds' => 300.0, 'max_seconds' => 600.0],
        ]);

        $this->connection->method('executeQuery')->willReturn($result);

        $calibrations = $this->service->getCalibratedServiceTimes(customerId: 1, limit: 50);

        self::assertCount(2, $calibrations);
        self::assertSame('123 Main St', $calibrations[0]['address']);
        self::assertSame(300, $calibrations[0]['avgSeconds']);
        self::assertSame(5, $calibrations[0]['sampleCount']);
        self::assertSame('456 Oak Ave', $calibrations[1]['address']);
        self::assertSame(450, $calibrations[1]['avgSeconds']);
    }

    #[Test]
    public function returns_empty_when_no_data(): void
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchAllAssociative')->willReturn([]);

        $this->connection->method('executeQuery')->willReturn($result);

        $calibrations = $this->service->getCalibratedServiceTimes(customerId: 1, limit: 50);

        self::assertSame([], $calibrations);
    }

    #[Test]
    public function filters_by_minimum_samples(): void
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchAllAssociative')->willReturn([
            ['address' => '123 Main St', 'avg_seconds' => 300.0, 'sample_count' => 5, 'min_seconds' => 200.0, 'max_seconds' => 400.0],
        ]);

        $this->connection->method('executeQuery')->willReturn($result);

        // The SQL HAVING clause filters by minSamples — already handled at DB level
        $calibrations = $this->service->getCalibratedServiceTimes(customerId: 1, limit: 50, minSamples: 3);

        self::assertCount(1, $calibrations);
        self::assertSame(5, $calibrations[0]['sampleCount']);
    }

    #[Test]
    public function rounds_average_to_integer_seconds(): void
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchAllAssociative')->willReturn([
            ['address' => '123 Main St', 'avg_seconds' => 312.7, 'sample_count' => 4, 'min_seconds' => 250.0, 'max_seconds' => 380.0],
        ]);

        $this->connection->method('executeQuery')->willReturn($result);

        $calibrations = $this->service->getCalibratedServiceTimes(customerId: 1, limit: 50);

        self::assertSame(313, $calibrations[0]['avgSeconds']);
    }

    // --- Tests for getCalibratedServiceTimesWithFeedback ---

    #[Test]
    public function with_feedback_returns_empty_when_no_feedback_and_no_historical(): void
    {
        $feedbackResult = $this->createMock(Result::class);
        $feedbackResult->method('fetchAllAssociative')->willReturn([]);

        $historicalResult = $this->createMock(Result::class);
        $historicalResult->method('fetchAllAssociative')->willReturn([]);

        $this->connection->method('executeQuery')->willReturnOnConsecutiveCalls(
            $feedbackResult,
            $historicalResult,
        );

        $calibrations = $this->service->getCalibratedServiceTimesWithFeedback(customerId: 1);

        self::assertSame([], $calibrations);
    }

    #[Test]
    public function with_feedback_returns_avg_from_driver_feedback(): void
    {
        $feedbackResult = $this->createMock(Result::class);
        $feedbackResult->method('fetchAllAssociative')->willReturn([
            ['address' => '100 Feedback Rd', 'avg_seconds' => 240.0, 'sample_count' => 3, 'min_seconds' => 180.0, 'max_seconds' => 300.0],
        ]);

        $historicalResult = $this->createMock(Result::class);
        $historicalResult->method('fetchAllAssociative')->willReturn([]);

        $this->connection->method('executeQuery')->willReturnOnConsecutiveCalls(
            $feedbackResult,
            $historicalResult,
        );

        $calibrations = $this->service->getCalibratedServiceTimesWithFeedback(customerId: 1);

        self::assertCount(1, $calibrations);
        self::assertSame('100 Feedback Rd', $calibrations[0]['address']);
        self::assertSame(240, $calibrations[0]['avgSeconds']);
        self::assertSame(3, $calibrations[0]['sampleCount']);
    }

    #[Test]
    public function with_feedback_feedback_overrides_historical_for_same_address(): void
    {
        $feedbackResult = $this->createMock(Result::class);
        $feedbackResult->method('fetchAllAssociative')->willReturn([
            ['address' => '123 Main St', 'avg_seconds' => 180.0, 'sample_count' => 4, 'min_seconds' => 120.0, 'max_seconds' => 240.0],
        ]);

        $historicalResult = $this->createMock(Result::class);
        $historicalResult->method('fetchAllAssociative')->willReturn([
            ['address' => '123 Main St', 'avg_seconds' => 300.0, 'sample_count' => 5, 'min_seconds' => 200.0, 'max_seconds' => 400.0],
        ]);

        $this->connection->method('executeQuery')->willReturnOnConsecutiveCalls(
            $feedbackResult,
            $historicalResult,
        );

        $calibrations = $this->service->getCalibratedServiceTimesWithFeedback(customerId: 1);

        self::assertCount(1, $calibrations);
        // Feedback data wins over historical
        self::assertSame('123 Main St', $calibrations[0]['address']);
        self::assertSame(180, $calibrations[0]['avgSeconds']);
        self::assertSame(4, $calibrations[0]['sampleCount']);
    }

    #[Test]
    public function with_feedback_returns_historical_when_no_feedback_for_address(): void
    {
        $feedbackResult = $this->createMock(Result::class);
        $feedbackResult->method('fetchAllAssociative')->willReturn([]);

        $historicalResult = $this->createMock(Result::class);
        $historicalResult->method('fetchAllAssociative')->willReturn([
            ['address' => '456 Oak Ave', 'avg_seconds' => 450.0, 'sample_count' => 3, 'min_seconds' => 300.0, 'max_seconds' => 600.0],
        ]);

        $this->connection->method('executeQuery')->willReturnOnConsecutiveCalls(
            $feedbackResult,
            $historicalResult,
        );

        $calibrations = $this->service->getCalibratedServiceTimesWithFeedback(customerId: 1);

        self::assertCount(1, $calibrations);
        self::assertSame('456 Oak Ave', $calibrations[0]['address']);
        self::assertSame(450, $calibrations[0]['avgSeconds']);
        self::assertSame(3, $calibrations[0]['sampleCount']);
    }
}
