<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Ai\LlmClientInterface;
use App\Ai\LlmResponse;
use App\Entity\User;
use App\Service\AiAssistantService;
use App\Service\AlertService;
use App\Service\ExceptionPatternService;
use App\Service\ReportingService;
use App\Service\SearchService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(AiAssistantService::class)]
final class AiAssistantServiceTest extends TestCase
{
    private LlmClientInterface&MockObject $llmClient;
    private SearchService&MockObject $searchService;
    private ReportingService&MockObject $reportingService;
    private AlertService&MockObject $alertService;
    private ExceptionPatternService&MockObject $exceptionPatternService;
    private EntityManagerInterface&MockObject $em;
    private AiAssistantService $service;

    protected function setUp(): void
    {
        $this->llmClient = $this->createMock(LlmClientInterface::class);
        $this->searchService = $this->createMock(SearchService::class);
        $this->reportingService = $this->createMock(ReportingService::class);
        $this->alertService = $this->createMock(AlertService::class);
        $this->exceptionPatternService = $this->createMock(ExceptionPatternService::class);
        $this->em = $this->createMock(EntityManagerInterface::class);

        $this->service = new AiAssistantService(
            $this->llmClient,
            $this->searchService,
            $this->reportingService,
            $this->alertService,
            $this->exceptionPatternService,
            $this->em,
            new NullLogger(),
        );

        // Reset static rate limit buckets between tests
        $ref = new \ReflectionClass(AiAssistantService::class);
        $prop = $ref->getProperty('rateLimitBuckets');
        $prop->setValue(null, []);
    }

    #[Test]
    public function chatReturnsAiResponse(): void
    {
        $this->llmClient->method('completeWithToolLoop')
            ->willReturn(new LlmResponse(
                content: 'Hay 5 entregas pendientes para hoy.',
                rawResponse: ['tools_used' => ['search_shipments']],
            ));

        $result = $this->service->chat('Cuantas entregas pendientes hay?');

        self::assertSame('Hay 5 entregas pendientes para hoy.', $result['response']);
        self::assertSame(['search_shipments'], $result['tools_used']);
    }

    #[Test]
    public function chatReturnsErrorMessageOnException(): void
    {
        $this->llmClient->method('completeWithToolLoop')
            ->willThrowException(new \RuntimeException('API down'));

        $result = $this->service->chat('test');

        self::assertStringContainsString('error', $result['response']);
        self::assertSame([], $result['tools_used']);
    }

    #[Test]
    public function chatEnforcesRateLimit(): void
    {
        $this->llmClient->method('completeWithToolLoop')
            ->willReturn(new LlmResponse(content: 'OK'));

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('1');

        // Send 20 messages (the limit)
        for ($i = 0; $i < 20; $i++) {
            $result = $this->service->chat('msg ' . $i, null, $user);
            self::assertNotEmpty($result['response']);
        }

        // 21st should be rate-limited
        $result = $this->service->chat('one more', null, $user);
        self::assertStringContainsString('limite', $result['response']);
        self::assertSame([], $result['tools_used']);
    }

    #[Test]
    public function chatHandlesEmptyResponse(): void
    {
        $this->llmClient->method('completeWithToolLoop')
            ->willReturn(new LlmResponse(content: ''));

        $result = $this->service->chat('test');

        // Empty response should still work (controller handles empty display)
        self::assertSame('', $result['response']);
    }
}
