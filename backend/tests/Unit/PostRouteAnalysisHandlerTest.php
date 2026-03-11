<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Route;
use App\Message\PostRouteAnalysisMessage;
use App\MessageHandler\PostRouteAnalysisHandler;
use App\Service\PostRouteAnalyzer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Uid\Ulid;

#[CoversClass(PostRouteAnalysisHandler::class)]
final class PostRouteAnalysisHandlerTest extends TestCase
{
    private PostRouteAnalyzer&MockObject $analyzer;
    private EntityManagerInterface&MockObject $em;
    private PostRouteAnalysisHandler $handler;

    protected function setUp(): void
    {
        $this->analyzer = $this->createMock(PostRouteAnalyzer::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->handler = new PostRouteAnalysisHandler(
            $this->analyzer,
            $this->em,
            new NullLogger(),
        );
    }

    #[Test]
    public function invokeAnalyzesAndPersistsResult(): void
    {
        $ulid = new Ulid();
        $route = $this->createMock(Route::class);
        $route->method('getName')->willReturn('Ruta Test');

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')
            ->with(['publicId' => Ulid::fromString((string) $ulid)])
            ->willReturn($route);

        $this->em->method('getRepository')
            ->with(Route::class)
            ->willReturn($repo);

        $analysis = [
            'summary' => 'Ruta completada correctamente.',
            'planned_vs_actual' => 'Dentro del margen.',
            'insights' => ['Buen rendimiento'],
            'recommendations' => ['Mantener'],
        ];

        $this->analyzer->method('analyze')
            ->with($route)
            ->willReturn($analysis);

        $route->expects(self::once())
            ->method('setAiAnalysis')
            ->with($analysis);

        $this->em->expects(self::once())->method('flush');

        ($this->handler)(new PostRouteAnalysisMessage((string) $ulid));
    }

    #[Test]
    public function invokeSkipsWhenRouteNotFound(): void
    {
        $ulid = new Ulid();

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn(null);

        $this->em->method('getRepository')
            ->with(Route::class)
            ->willReturn($repo);

        $this->analyzer->expects(self::never())->method('analyze');
        $this->em->expects(self::never())->method('flush');

        ($this->handler)(new PostRouteAnalysisMessage((string) $ulid));
    }
}
