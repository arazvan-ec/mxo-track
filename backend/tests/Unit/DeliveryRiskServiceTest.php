<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Route;
use App\Entity\RouteStop;
use App\Service\AddressRiskService;
use App\Service\DeliveryRiskService;
use App\Service\MlApiClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(DeliveryRiskService::class)]
final class DeliveryRiskServiceTest extends TestCase
{
    private MlApiClient&MockObject $mlClient;
    private AddressRiskService&MockObject $addressRisk;
    private DeliveryRiskService $service;

    protected function setUp(): void
    {
        $this->mlClient = $this->createMock(MlApiClient::class);
        $this->addressRisk = $this->createMock(AddressRiskService::class);
        $this->service = new DeliveryRiskService(
            $this->mlClient,
            $this->addressRisk,
            new NullLogger(),
        );
    }

    #[Test]
    public function predictRiskReturnsLowRisk(): void
    {
        $stop = $this->createStopMock('Calle A 1', '600123456', 1);

        $this->mlClient->method('predict')->willReturn([
            'risk_score' => 0.1,
            'model_version' => 'v1',
        ]);

        $this->addressRisk->method('checkAddress')->willReturn([
            'is_risky' => false, 'exception_rate' => 0.0, 'sample_count' => 0,
        ]);

        $result = $this->service->predictRisk($stop);

        self::assertSame(0.1, $result['risk_score']);
        self::assertSame('LOW', $result['risk_level']);
        self::assertFalse($result['address_risk']);
    }

    #[Test]
    public function predictRiskReturnsMediumRisk(): void
    {
        $stop = $this->createStopMock('Calle B 2', null, 3);

        $this->mlClient->method('predict')->willReturn([
            'risk_score' => 0.35,
        ]);

        $this->addressRisk->method('checkAddress')->willReturn([
            'is_risky' => false, 'exception_rate' => 0.1, 'sample_count' => 5,
        ]);

        $result = $this->service->predictRisk($stop);

        self::assertSame(0.35, $result['risk_score']);
        self::assertSame('MEDIUM', $result['risk_level']);
    }

    #[Test]
    public function predictRiskReturnsHighRisk(): void
    {
        $stop = $this->createStopMock('Calle C 3', '600000000', 5);

        $this->mlClient->method('predict')->willReturn([
            'risk_score' => 0.6,
        ]);

        $this->addressRisk->method('checkAddress')->willReturn([
            'is_risky' => false, 'exception_rate' => 0.2, 'sample_count' => 10,
        ]);

        $result = $this->service->predictRisk($stop);

        self::assertSame(0.6, $result['risk_score']);
        self::assertSame('HIGH', $result['risk_level']);
    }

    #[Test]
    public function predictRiskBoostsScoreForRiskyAddress(): void
    {
        $stop = $this->createStopMock('Calle Problematica 5', null, 2);

        $this->mlClient->method('predict')->willReturn([
            'risk_score' => 0.1,
        ]);

        $this->addressRisk->method('checkAddress')->willReturn([
            'is_risky' => true, 'exception_rate' => 0.4, 'sample_count' => 10,
        ]);

        $result = $this->service->predictRisk($stop);

        // 0.1 + 0.15 boost = 0.25
        self::assertSame(0.25, $result['risk_score']);
        self::assertSame('MEDIUM', $result['risk_level']);
        self::assertTrue($result['address_risk']);
    }

    #[Test]
    public function predictRiskCapsScoreAtOneWithBoost(): void
    {
        $stop = $this->createStopMock('Calle D 4', null, 1);

        $this->mlClient->method('predict')->willReturn([
            'risk_score' => 0.95,
        ]);

        $this->addressRisk->method('checkAddress')->willReturn([
            'is_risky' => true, 'exception_rate' => 0.5, 'sample_count' => 20,
        ]);

        $result = $this->service->predictRisk($stop);

        // min(1.0, 0.95 + 0.15) = 1.0
        self::assertSame(1.0, $result['risk_score']);
        self::assertSame('HIGH', $result['risk_level']);
    }

    #[Test]
    public function predictRiskHandlesMlServiceDown(): void
    {
        $stop = $this->createStopMock('Calle E 5', '600111222', 1);

        $this->mlClient->method('predict')->willReturn([]);

        $this->addressRisk->method('checkAddress')->willReturn([
            'is_risky' => false, 'exception_rate' => 0.0, 'sample_count' => 0,
        ]);

        $result = $this->service->predictRisk($stop);

        self::assertSame(0.0, $result['risk_score']);
        self::assertSame('LOW', $result['risk_level']);
    }

    private function createStopMock(string $address, ?string $phone, int $sequence): RouteStop&MockObject
    {
        $route = $this->createMock(Route::class);
        $route->method('getStartAt')->willReturn(new \DateTimeImmutable('2026-03-11 10:00:00'));

        $stop = $this->createMock(RouteStop::class);
        $stop->method('getRoute')->willReturn($route);
        $stop->method('getAddress')->willReturn($address);
        $stop->method('getRecipientPhone')->willReturn($phone);
        $stop->method('getSequence')->willReturn($sequence);

        return $stop;
    }
}
