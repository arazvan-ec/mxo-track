<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Ai\LlmClientInterface;
use App\Ai\LlmRequest;
use App\Ai\LlmResponse;
use App\Service\ExceptionClassifierService;
use App\Service\RateLimitedApiClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(ExceptionClassifierService::class)]
final class ExceptionClassifierServiceTest extends TestCase
{
    private LlmClientInterface&MockObject $llmClient;
    private RateLimitedApiClient $rateLimiter;
    private ExceptionClassifierService $service;

    protected function setUp(): void
    {
        $this->llmClient = $this->createMock(LlmClientInterface::class);
        $this->rateLimiter = new RateLimitedApiClient(new NullLogger());
        $this->service = new ExceptionClassifierService(
            $this->llmClient,
            $this->rateLimiter,
            new NullLogger(),
        );
    }

    #[Test]
    public function classifyReturnsValidClassification(): void
    {
        $json = json_encode([
            'subcategory' => 'ACCESO_EDIFICIO',
            'actionable_insight' => 'El portero no abrio la puerta',
            'suggested_action' => 'Contactar al destinatario antes de la entrega',
            'confidence' => 0.92,
        ]);

        $this->llmClient->method('complete')
            ->willReturn(new LlmResponse(content: $json));

        $result = $this->service->classify('No pude entrar al edificio, el portero no abria', 'DELIVERY_FAILED');

        self::assertSame('ACCESO_EDIFICIO', $result['subcategory']);
        self::assertSame('El portero no abrio la puerta', $result['actionable_insight']);
        self::assertSame('Contactar al destinatario antes de la entrega', $result['suggested_action']);
        self::assertSame(0.92, $result['confidence']);
    }

    #[Test]
    public function classifyReturnsFallbackWhenNotesEmpty(): void
    {
        $this->llmClient->expects(self::never())->method('complete');

        $result = $this->service->classify('', 'DELIVERY_FAILED');

        self::assertSame('OTRO', $result['subcategory']);
        self::assertNull($result['actionable_insight']);
        self::assertNull($result['suggested_action']);
        self::assertSame(0.0, $result['confidence']);
    }

    #[Test]
    public function classifyReturnsFallbackWhenWhitespaceOnly(): void
    {
        $this->llmClient->expects(self::never())->method('complete');

        $result = $this->service->classify('   ', 'DELIVERY_FAILED');

        self::assertSame('OTRO', $result['subcategory']);
        self::assertSame(0.0, $result['confidence']);
    }

    #[Test]
    public function classifyReturnsFallbackWhenApiThrows(): void
    {
        $this->llmClient->method('complete')
            ->willThrowException(new \RuntimeException('API unavailable'));

        $result = $this->service->classify('Paquete danado', 'DELIVERY_FAILED');

        self::assertSame('OTRO', $result['subcategory']);
        self::assertNull($result['actionable_insight']);
        self::assertSame(0.0, $result['confidence']);
    }

    #[Test]
    public function classifyReturnsFallbackWhenResponseNotJson(): void
    {
        $this->llmClient->method('complete')
            ->willReturn(new LlmResponse(content: 'This is not JSON'));

        $result = $this->service->classify('Paquete danado', 'DELIVERY_FAILED');

        self::assertSame('OTRO', $result['subcategory']);
        self::assertSame(0.0, $result['confidence']);
    }

    #[Test]
    public function classifyNormalizesInvalidSubcategoryToOtro(): void
    {
        $json = json_encode([
            'subcategory' => 'INVENTADA',
            'actionable_insight' => 'test',
            'suggested_action' => 'test',
            'confidence' => 0.8,
        ]);

        $this->llmClient->method('complete')
            ->willReturn(new LlmResponse(content: $json));

        $result = $this->service->classify('Algo paso', 'DELIVERY_FAILED');

        self::assertSame('OTRO', $result['subcategory']);
    }

    #[Test]
    public function classifyClampsConfidenceToValidRange(): void
    {
        $json = json_encode([
            'subcategory' => 'PAQUETE_DANADO',
            'actionable_insight' => null,
            'suggested_action' => null,
            'confidence' => 1.5,
        ]);

        $this->llmClient->method('complete')
            ->willReturn(new LlmResponse(content: $json));

        $result = $this->service->classify('Paquete roto', 'DELIVERY_FAILED');

        self::assertSame(1.0, $result['confidence']);
    }

    #[Test]
    public function classifyHandlesAllValidSubcategories(): void
    {
        $validSubcategories = [
            'ACCESO_EDIFICIO', 'DIRECCION_INCOMPLETA', 'AUSENCIA_RECURRENTE',
            'RECHAZO_ESTADO', 'HORARIO_INCOMPATIBLE', 'PAQUETE_DANADO',
            'DIRECCION_NO_ENCONTRADA', 'DESTINATARIO_DESCONOCIDO', 'OTRO',
        ];

        foreach ($validSubcategories as $subcategory) {
            $json = json_encode([
                'subcategory' => $subcategory,
                'actionable_insight' => 'test',
                'suggested_action' => 'test',
                'confidence' => 0.9,
            ]);

            $llmClient = $this->createMock(LlmClientInterface::class);
            $llmClient->method('complete')
                ->willReturn(new LlmResponse(content: $json));

            $service = new ExceptionClassifierService(
                $llmClient,
                $this->rateLimiter,
                new NullLogger(),
            );

            $result = $service->classify('test notes', 'TEST_CODE');

            self::assertSame($subcategory, $result['subcategory'], "Failed for subcategory: {$subcategory}");
        }
    }
}
