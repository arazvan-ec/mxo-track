<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Ai\LlmClientInterface;
use App\Ai\LlmRequest;
use App\Ai\LlmResponse;
use App\Domain\Route\Model\Route;
use App\Domain\Route\Model\RouteStop;
use App\Enum\ExceptionCode;
use App\Enum\RouteStatus;
use App\Enum\RouteStopStatus;
use App\Service\PostRouteAnalyzer;
use App\Service\RateLimitedApiClient;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(PostRouteAnalyzer::class)]
final class PostRouteAnalyzerTest extends TestCase
{
    private LlmClientInterface&MockObject $llmClient;
    private EntityManagerInterface&MockObject $em;
    private PostRouteAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->llmClient = $this->createMock(LlmClientInterface::class);
        $this->em = $this->createMock(EntityManagerInterface::class);

        $this->analyzer = new PostRouteAnalyzer(
            $this->llmClient,
            new RateLimitedApiClient(new NullLogger()),
            $this->em,
            new NullLogger(),
        );
    }

    #[Test]
    public function analyzeReturnsAiAnalysisOnSuccess(): void
    {
        $route = $this->createRouteMock('Ruta Norte', 90, 100, 15.5);
        $stops = $this->createStopMocks([
            ['DELIVERED', 1, 'Calle A'],
            ['DELIVERED', 2, 'Calle B'],
            ['EXCEPTION', 3, 'Calle C'],
        ]);

        $this->setupStopRepository($route, $stops);

        $aiResponse = json_encode([
            'summary' => 'Ruta completada con buen rendimiento.',
            'planned_vs_actual' => 'Duración real cercana a la estimada.',
            'insights' => ['Alta tasa de entrega', 'Una excepción por acceso'],
            'recommendations' => ['Verificar acceso en Calle C antes de próxima visita'],
        ]);

        $this->llmClient->method('complete')
            ->willReturn(new LlmResponse(content: $aiResponse));

        $result = $this->analyzer->analyze($route);

        self::assertSame('Ruta completada con buen rendimiento.', $result['summary']);
        self::assertSame('Duración real cercana a la estimada.', $result['planned_vs_actual']);
        self::assertCount(2, $result['insights']);
        self::assertCount(1, $result['recommendations']);
    }

    #[Test]
    public function analyzeReturnsFallbackWhenAiFails(): void
    {
        $route = $this->createRouteMock('Ruta Sur', 60, 75, 20.0);
        $stops = $this->createStopMocks([
            ['DELIVERED', 1, 'Calle X'],
            ['EXCEPTION', 2, 'Calle Y'],
            ['EXCEPTION', 3, 'Calle Z'],
        ]);

        $this->setupStopRepository($route, $stops);

        $this->llmClient->method('complete')
            ->willThrowException(new \RuntimeException('API down'));

        $result = $this->analyzer->analyze($route);

        self::assertStringContainsString('Ruta Sur', $result['summary']);
        self::assertStringContainsString('1 de 3', $result['summary']);
        self::assertStringContainsString('2 excepciones', $result['summary']);
        self::assertStringContainsString('60 min', $result['planned_vs_actual']);
        self::assertStringContainsString('75 min', $result['planned_vs_actual']);
    }

    #[Test]
    public function analyzeReturnsFallbackWhenResponseNotJson(): void
    {
        $route = $this->createRouteMock('Ruta Este', null, null, null);
        $stops = $this->createStopMocks([
            ['DELIVERED', 1, 'Calle A'],
        ]);

        $this->setupStopRepository($route, $stops);

        $this->llmClient->method('complete')
            ->willReturn(new LlmResponse(content: 'Not valid JSON response'));

        $result = $this->analyzer->analyze($route);

        self::assertStringContainsString('Ruta Este', $result['summary']);
        self::assertSame('Sin datos suficientes para comparar planificado vs real.', $result['planned_vs_actual']);
    }

    #[Test]
    public function analyzeHandlesJsonInMarkdownCodeBlock(): void
    {
        $route = $this->createRouteMock('Ruta Oeste', 45, 50, 10.0);
        $stops = $this->createStopMocks([
            ['DELIVERED', 1, 'Calle A'],
            ['DELIVERED', 2, 'Calle B'],
        ]);

        $this->setupStopRepository($route, $stops);

        $wrappedResponse = "```json\n" . json_encode([
            'summary' => 'Excelente rendimiento.',
            'planned_vs_actual' => 'Dentro del margen.',
            'insights' => ['100% entregas exitosas'],
            'recommendations' => [],
        ]) . "\n```";

        $this->llmClient->method('complete')
            ->willReturn(new LlmResponse(content: $wrappedResponse));

        $result = $this->analyzer->analyze($route);

        self::assertSame('Excelente rendimiento.', $result['summary']);
        self::assertCount(1, $result['insights']);
    }

    #[Test]
    public function analyzeFallbackShowsLowDeliveryRateWarning(): void
    {
        $route = $this->createRouteMock('Ruta Problema', null, null, null);
        $stops = $this->createStopMocks([
            ['DELIVERED', 1, 'Calle A'],
            ['EXCEPTION', 2, 'Calle B'],
            ['EXCEPTION', 3, 'Calle C'],
            ['EXCEPTION', 4, 'Calle D'],
            ['EXCEPTION', 5, 'Calle E'],
        ]);

        $this->setupStopRepository($route, $stops);

        $this->llmClient->method('complete')
            ->willThrowException(new \RuntimeException('fail'));

        $result = $this->analyzer->analyze($route);

        $hasLowRateInsight = false;
        foreach ($result['insights'] as $insight) {
            if (str_contains($insight, '80%') || str_contains($insight, 'debajo')) {
                $hasLowRateInsight = true;
                break;
            }
        }
        self::assertTrue($hasLowRateInsight, 'Should warn about low delivery rate');
    }

    #[Test]
    public function analyzeSkipsOriginStops(): void
    {
        $route = $this->createRouteMock('Ruta Con Origen', null, null, null);

        $originStop = $this->createMock(RouteStop::class);
        $originStop->method('isOrigin')->willReturn(true);

        $deliveryStop = $this->createMock(RouteStop::class);
        $deliveryStop->method('isOrigin')->willReturn(false);
        $deliveryStop->method('getStatus')->willReturn(RouteStopStatus::DELIVERED);
        $deliveryStop->method('getSequence')->willReturn(1);
        $deliveryStop->method('getAddress')->willReturn('Calle A');
        $deliveryStop->method('getDeliveredAt')->willReturn(new \DateTimeImmutable());
        $deliveryStop->method('getExceptionCode')->willReturn(null);

        $this->setupStopRepository($route, [$originStop, $deliveryStop]);

        $this->llmClient->method('complete')
            ->willThrowException(new \RuntimeException('fail'));

        $result = $this->analyzer->analyze($route);

        // Only 1 stop counted (origin excluded)
        self::assertStringContainsString('1 de 1', $result['summary']);
    }

    private function createRouteMock(
        string $name,
        ?int $estimatedMinutes,
        ?int $actualMinutes,
        ?float $distanceKm,
    ): Route&MockObject {
        $route = $this->createMock(Route::class);
        $route->method('getName')->willReturn($name);
        $route->method('getEstimatedDurationMinutes')->willReturn($estimatedMinutes);
        $route->method('getTotalDistanceKm')->willReturn($distanceKm);
        $route->method('getPublicIdString')->willReturn('01JTEST000000000000000000');
        $route->method('getStatus')->willReturn(RouteStatus::DONE);

        if ($estimatedMinutes !== null && $actualMinutes !== null) {
            $start = new \DateTimeImmutable('2026-03-11 08:00:00');
            $end = $start->modify("+{$actualMinutes} minutes");
            $route->method('getStartAt')->willReturn($start);
            $route->method('getEndAt')->willReturn($end);
        } else {
            $route->method('getStartAt')->willReturn(null);
            $route->method('getEndAt')->willReturn(null);
        }

        return $route;
    }

    /**
     * @param list<array{0: string, 1: int, 2: string}> $stopData [status, sequence, address]
     * @return list<RouteStop&MockObject>
     */
    private function createStopMocks(array $stopData): array
    {
        $stops = [];
        foreach ($stopData as [$statusStr, $sequence, $address]) {
            $status = RouteStopStatus::from($statusStr);
            $stop = $this->createMock(RouteStop::class);
            $stop->method('isOrigin')->willReturn(false);
            $stop->method('getStatus')->willReturn($status);
            $stop->method('getSequence')->willReturn($sequence);
            $stop->method('getAddress')->willReturn($address);
            $stop->method('getDeliveredAt')->willReturn(
                $status === RouteStopStatus::DELIVERED ? new \DateTimeImmutable() : null,
            );
            $stop->method('getExceptionCode')->willReturn(
                $status === RouteStopStatus::EXCEPTION ? ExceptionCode::OTHER : null,
            );
            $stops[] = $stop;
        }

        return $stops;
    }

    private function setupStopRepository(Route&MockObject $route, array $stops): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findBy')
            ->with(['route' => $route], ['sequence' => 'ASC'])
            ->willReturn($stops);

        $this->em->method('getRepository')
            ->with(RouteStop::class)
            ->willReturn($repo);
    }
}
