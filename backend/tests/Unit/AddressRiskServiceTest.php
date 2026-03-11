<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\AddressRiskService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(AddressRiskService::class)]
final class AddressRiskServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private Connection&MockObject $conn;
    private AddressRiskService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->conn = $this->createMock(Connection::class);
        $this->em->method('getConnection')->willReturn($this->conn);

        $this->service = new AddressRiskService($this->em, new NullLogger());
    }

    #[Test]
    public function checkAddressReturnsFalseWhenFewSamples(): void
    {
        $this->mockQueryResult(['total' => 2, 'exceptions' => 2]);

        $result = $this->service->checkAddress('Calle Nueva 1');

        self::assertFalse($result['is_risky']);
        self::assertSame(0.0, $result['exception_rate']);
        self::assertSame(2, $result['sample_count']);
    }

    #[Test]
    public function checkAddressReturnsFalseWhenLowRate(): void
    {
        $this->mockQueryResult(['total' => 10, 'exceptions' => 2]);

        $result = $this->service->checkAddress('Calle Segura 5');

        self::assertFalse($result['is_risky']);
        self::assertSame(0.2, $result['exception_rate']);
        self::assertSame(10, $result['sample_count']);
    }

    #[Test]
    public function checkAddressReturnsTrueWhenHighRate(): void
    {
        $this->mockQueryResult(['total' => 10, 'exceptions' => 4]);

        $result = $this->service->checkAddress('Calle Problematica 3');

        self::assertTrue($result['is_risky']);
        self::assertSame(0.4, $result['exception_rate']);
        self::assertSame(10, $result['sample_count']);
    }

    #[Test]
    public function checkAddressReturnsTrueAtExactThreshold(): void
    {
        $this->mockQueryResult(['total' => 10, 'exceptions' => 3]);

        $result = $this->service->checkAddress('Calle Limite 7');

        // 3/10 = 0.3 >= 0.3 threshold → risky
        self::assertTrue($result['is_risky']);
        self::assertSame(0.3, $result['exception_rate']);
    }

    #[Test]
    public function checkAddressReturnsFalseWhenNoHistory(): void
    {
        $this->mockQueryResult(['total' => 0, 'exceptions' => 0]);

        $result = $this->service->checkAddress('Calle Desconocida 99');

        self::assertFalse($result['is_risky']);
        self::assertSame(0, $result['sample_count']);
    }

    #[Test]
    public function checkAddressReturnsFalseOnQueryError(): void
    {
        $this->conn->method('executeQuery')
            ->willThrowException(new \RuntimeException('DB connection lost'));

        $result = $this->service->checkAddress('Calle Error');

        self::assertFalse($result['is_risky']);
        self::assertSame(0.0, $result['exception_rate']);
        self::assertSame(0, $result['sample_count']);
    }

    private function mockQueryResult(array $row): void
    {
        $dbResult = $this->createMock(Result::class);
        $dbResult->method('fetchAssociative')->willReturn($row);

        $this->conn->method('executeQuery')->willReturn($dbResult);
    }
}
