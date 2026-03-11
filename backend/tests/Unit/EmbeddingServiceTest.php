<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Ai\EmbeddingClientInterface;
use App\Service\EmbeddingService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(EmbeddingService::class)]
final class EmbeddingServiceTest extends TestCase
{
    private EmbeddingClientInterface&MockObject $embeddingClient;
    private EntityManagerInterface&MockObject $em;
    private Connection&MockObject $conn;
    private EmbeddingService $service;

    protected function setUp(): void
    {
        $this->embeddingClient = $this->createMock(EmbeddingClientInterface::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->conn = $this->createMock(Connection::class);
        $this->em->method('getConnection')->willReturn($this->conn);

        $this->service = new EmbeddingService(
            $this->embeddingClient,
            $this->em,
            new NullLogger(),
        );
    }

    #[Test]
    public function embedAndStoreCallsClientAndPersists(): void
    {
        $this->embeddingClient->method('embed')
            ->with('Test shipment text')
            ->willReturn([0.1, 0.2, 0.3]);

        $this->conn->expects(self::once())
            ->method('executeStatement')
            ->with(
                self::stringContains('INSERT INTO ml_embedding'),
                self::callback(fn (array $params) => $params['entity_type'] === 'shipment'
                    && $params['entity_id'] === 42
                    && $params['embedding'] === '[0.1,0.2,0.3]'
                    && $params['text_content'] === 'Test shipment text'),
            );

        $this->service->embedAndStore('shipment', 42, 'Test shipment text');
    }

    #[Test]
    public function embedAndStoreSkipsWhenEmbeddingNull(): void
    {
        $this->embeddingClient->method('embed')->willReturn(null);

        $this->conn->expects(self::never())->method('executeStatement');

        $this->service->embedAndStore('shipment', 1, 'test');
    }

    #[Test]
    public function embedAndStoreSkipsWhenEmbeddingEmpty(): void
    {
        $this->embeddingClient->method('embed')->willReturn([]);

        $this->conn->expects(self::never())->method('executeStatement');

        $this->service->embedAndStore('shipment', 1, 'test');
    }

    #[Test]
    public function searchReturnsFormattedResults(): void
    {
        $this->embeddingClient->method('embed')
            ->with('paquete fragil')
            ->willReturn([0.5, 0.6, 0.7]);

        $dbResult = $this->createMock(Result::class);
        $dbResult->method('fetchAllAssociative')->willReturn([
            ['entity_type' => 'shipment', 'entity_id' => 10, 'text_content' => 'Paquete fragil Serrano', 'similarity' => 0.92],
            ['entity_type' => 'shipment', 'entity_id' => 20, 'text_content' => 'Paquete grande Madrid', 'similarity' => 0.78],
        ]);

        $this->conn->method('executeQuery')->willReturn($dbResult);

        $results = $this->service->search('paquete fragil', 'shipment', 5);

        self::assertCount(2, $results);
        self::assertSame(10, $results[0]['entity_id']);
        self::assertSame(0.92, $results[0]['similarity']);
        self::assertSame('shipment', $results[0]['entity_type']);
    }

    #[Test]
    public function searchReturnsEmptyWhenEmbeddingFails(): void
    {
        $this->embeddingClient->method('embed')->willReturn(null);

        $this->conn->expects(self::never())->method('executeQuery');

        $results = $this->service->search('query', 'shipment');

        self::assertSame([], $results);
    }
}
