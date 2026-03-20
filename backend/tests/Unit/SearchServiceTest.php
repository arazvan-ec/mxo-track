<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Shipment\Model\Shipment;
use App\Entity\User;
use App\Service\EmbeddingService;
use App\Service\SearchService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[CoversClass(SearchService::class)]
final class SearchServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private UrlGeneratorInterface&MockObject $urlGenerator;
    private EmbeddingService&MockObject $embeddingService;
    private SearchService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->embeddingService = $this->createMock(EmbeddingService::class);

        $this->service = new SearchService(
            $this->em,
            $this->urlGenerator,
            $this->embeddingService,
            new NullLogger(),
        );
    }

    #[Test]
    public function searchReturnsEmptyForBlankQuery(): void
    {
        $user = $this->createMock(User::class);
        $result = $this->service->search('', $user);

        self::assertSame([], $result);
    }

    #[Test]
    public function searchReturnsEmptyForSingleCharQuery(): void
    {
        $user = $this->createMock(User::class);
        $result = $this->service->search('a', $user);

        self::assertSame([], $result);
    }

    #[Test]
    public function searchReturnsEmptyForWhitespaceQuery(): void
    {
        $user = $this->createMock(User::class);
        $result = $this->service->search('   ', $user);

        self::assertSame([], $result);
    }

    #[Test]
    public function searchCallsSemanticWhenKeywordResultsSparse(): void
    {
        $user = $this->createMock(User::class);
        $user->method('hasRole')->willReturn(false);
        $user->method('getCustomer')->willReturn(null);

        // Mock QB that returns empty results
        $this->setupEmptyQueryBuilder();

        // Semantic search should be attempted since keyword results < 3
        $this->embeddingService->expects(self::once())
            ->method('search')
            ->with('paquete fragil', 'shipment', 10)
            ->willReturn([]);

        $this->service->search('paquete fragil', $user);
    }

    #[Test]
    public function searchHandlesSemanticSearchException(): void
    {
        $user = $this->createMock(User::class);
        $user->method('hasRole')->willReturn(false);
        $user->method('getCustomer')->willReturn(null);

        $this->setupEmptyQueryBuilder();

        $this->embeddingService->method('search')
            ->willThrowException(new \RuntimeException('pgvector not available'));

        // Should not throw — graceful degradation
        $result = $this->service->search('test query', $user);
        self::assertIsArray($result);
    }

    private function setupEmptyQueryBuilder(): void
    {
        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('getResult')->willReturn([]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('orWhere')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->em->method('createQueryBuilder')->willReturn($qb);
    }
}
