<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Ai\LlmClientInterface;
use App\Ai\LlmResponse;
use App\Entity\Route;
use App\Entity\RouteStop;
use App\Entity\ShipmentEvent;
use App\Repository\DriverFeedbackRepository;
use App\Service\DeliveryNoteAiEnricher;
use App\Service\RateLimitedApiClient;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(DeliveryNoteAiEnricher::class)]
final class DeliveryNoteAiEnricherTest extends TestCase
{
    private LlmClientInterface&MockObject $llmClient;
    private EntityManagerInterface&MockObject $em;
    private DriverFeedbackRepository&MockObject $feedbackRepo;
    private DeliveryNoteAiEnricher $enricher;

    protected function setUp(): void
    {
        $this->llmClient = $this->createMock(LlmClientInterface::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->feedbackRepo = $this->createMock(DriverFeedbackRepository::class);

        $this->enricher = new DeliveryNoteAiEnricher(
            $this->llmClient,
            new RateLimitedApiClient(new NullLogger()),
            $this->em,
            $this->feedbackRepo,
            new NullLogger(),
        );
    }

    #[Test]
    public function enrichStopReturnsNoteFromAi(): void
    {
        $stop = $this->createMock(RouteStop::class);
        $stop->method('getAddress')->willReturn('Calle Serrano 50, Madrid');

        $this->setupExceptionEventsQuery([
            $this->createExceptionEvent('No habia nadie', '2026-03-01'),
        ]);
        $this->feedbackRepo->method('findByAddress')->willReturn([]);

        $this->llmClient->method('complete')
            ->willReturn(new LlmResponse(content: 'Llamar antes. Ausencia previa el 01/03.'));

        $result = $this->enricher->enrichStop($stop);

        self::assertSame('Llamar antes. Ausencia previa el 01/03.', $result);
    }

    #[Test]
    public function enrichStopReturnsNullWhenNoHistory(): void
    {
        $stop = $this->createMock(RouteStop::class);
        $stop->method('getAddress')->willReturn('Calle Nueva 1');

        $this->setupExceptionEventsQuery([]);
        $this->feedbackRepo->method('findByAddress')->willReturn([]);

        $this->llmClient->expects(self::never())->method('complete');

        $result = $this->enricher->enrichStop($stop);

        self::assertNull($result);
    }

    #[Test]
    public function enrichStopTruncatesLongNotes(): void
    {
        $stop = $this->createMock(RouteStop::class);
        $stop->method('getAddress')->willReturn('Calle Test 1');

        $this->setupExceptionEventsQuery([
            $this->createExceptionEvent('test', '2026-03-01'),
        ]);
        $this->feedbackRepo->method('findByAddress')->willReturn([]);

        $longNote = str_repeat('A', 250);
        $this->llmClient->method('complete')
            ->willReturn(new LlmResponse(content: $longNote));

        $result = $this->enricher->enrichStop($stop);

        self::assertNotNull($result);
        self::assertLessThanOrEqual(200, mb_strlen($result));
        self::assertStringEndsWith('...', $result);
    }

    #[Test]
    public function enrichStopReturnsNullWhenAiReturnsEmpty(): void
    {
        $stop = $this->createMock(RouteStop::class);
        $stop->method('getAddress')->willReturn('Calle Test 2');

        $this->setupExceptionEventsQuery([
            $this->createExceptionEvent('test', '2026-03-01'),
        ]);
        $this->feedbackRepo->method('findByAddress')->willReturn([]);

        $this->llmClient->method('complete')
            ->willReturn(new LlmResponse(content: ''));

        $result = $this->enricher->enrichStop($stop);

        self::assertNull($result);
    }

    #[Test]
    public function enrichRouteEnrichesNonOriginStops(): void
    {
        $route = $this->createMock(Route::class);
        $route->method('getName')->willReturn('Ruta Test');

        $originStop = $this->createMock(RouteStop::class);
        $originStop->method('isOrigin')->willReturn(true);

        $stop1 = $this->createMock(RouteStop::class);
        $stop1->method('isOrigin')->willReturn(false);
        $stop1->method('getAddress')->willReturn('Calle A 1');

        $stop2 = $this->createMock(RouteStop::class);
        $stop2->method('isOrigin')->willReturn(false);
        $stop2->method('getAddress')->willReturn('Calle B 2');

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findBy')
            ->with(['route' => $route], ['sequence' => 'ASC'])
            ->willReturn([$originStop, $stop1, $stop2]);

        $this->em->method('getRepository')
            ->with(RouteStop::class)
            ->willReturn($repo);

        // Both stops have no history
        $this->setupExceptionEventsQuery([]);
        $this->feedbackRepo->method('findByAddress')->willReturn([]);

        // No enrichment expected since no history
        $this->em->expects(self::never())->method('flush');

        $count = $this->enricher->enrichRoute($route);

        self::assertSame(0, $count);
    }

    private function createExceptionEvent(string $notes, string $date): ShipmentEvent&MockObject
    {
        $event = $this->createMock(ShipmentEvent::class);
        $event->method('getPayload')->willReturn(['notes' => $notes]);
        $event->method('getCreatedAt')->willReturn(new \DateTimeImmutable($date));

        return $event;
    }

    private function setupExceptionEventsQuery(array $events): void
    {
        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('getResult')->willReturn($events);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('join')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->em->method('createQueryBuilder')->willReturn($qb);
    }
}
