<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\DriverFeedback;
use App\Entity\User;
use App\Repository\DriverFeedbackRepository;
use App\Service\CoordinateCorrectionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CoordinateCorrectionService::class)]
final class CoordinateCorrectionServiceTest extends TestCase
{
    private DriverFeedbackRepository $repository;
    private CoordinateCorrectionService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(DriverFeedbackRepository::class);
        $this->service = new CoordinateCorrectionService($this->repository);
    }

    #[Test]
    public function returns_average_when_three_consistent_feedbacks(): void
    {
        // Three points within ~11m of each other (México City area)
        $driver = new User('driver@test.com');
        $feedbacks = [
            new DriverFeedback($driver, null, 19.432600, -99.133200),
            new DriverFeedback($driver, null, 19.432650, -99.133250),
            new DriverFeedback($driver, null, 19.432700, -99.133300),
        ];

        $this->repository->method('findByAddress')->willReturn($feedbacks);

        $result = $this->service->getCorrectedCoordinates('Av Reforma 123');

        self::assertNotNull($result);
        self::assertCount(2, $result);
        self::assertEqualsWithDelta(19.432650, $result[0], 0.0001);
        self::assertEqualsWithDelta(-99.133250, $result[1], 0.0001);
    }

    #[Test]
    public function returns_null_when_fewer_than_three_feedbacks(): void
    {
        $driver = new User('driver@test.com');
        $feedbacks = [
            new DriverFeedback($driver, null, 19.432600, -99.133200),
            new DriverFeedback($driver, null, 19.432650, -99.133250),
        ];

        $this->repository->method('findByAddress')->willReturn($feedbacks);

        $result = $this->service->getCorrectedCoordinates('Av Reforma 123');

        self::assertNull($result);
    }

    #[Test]
    public function returns_null_when_feedbacks_spread_beyond_50m(): void
    {
        // Third point is ~600m away from the other two
        $driver = new User('driver@test.com');
        $feedbacks = [
            new DriverFeedback($driver, null, 19.432600, -99.133200),
            new DriverFeedback($driver, null, 19.432650, -99.133250),
            new DriverFeedback($driver, null, 19.437600, -99.138200),
        ];

        $this->repository->method('findByAddress')->willReturn($feedbacks);

        $result = $this->service->getCorrectedCoordinates('Av Reforma 123');

        self::assertNull($result);
    }

    #[Test]
    public function returns_null_when_corrected_coords_are_null(): void
    {
        $driver = new User('driver@test.com');
        $feedbacks = [
            new DriverFeedback($driver, null, null, null),
            new DriverFeedback($driver, null, null, null),
            new DriverFeedback($driver, null, null, null),
        ];

        $this->repository->method('findByAddress')->willReturn($feedbacks);

        $result = $this->service->getCorrectedCoordinates('Av Reforma 123');

        self::assertNull($result);
    }

    #[Test]
    public function filters_out_feedbacks_with_null_coords_before_counting(): void
    {
        // 4 feedbacks total but only 2 have coordinates — should return null
        $driver = new User('driver@test.com');
        $feedbacks = [
            new DriverFeedback($driver, null, 19.432600, -99.133200),
            new DriverFeedback($driver, null, null, null),
            new DriverFeedback($driver, null, 19.432650, -99.133250),
            new DriverFeedback($driver, null, null, null),
        ];

        $this->repository->method('findByAddress')->willReturn($feedbacks);

        $result = $this->service->getCorrectedCoordinates('Av Reforma 123');

        self::assertNull($result);
    }
}
