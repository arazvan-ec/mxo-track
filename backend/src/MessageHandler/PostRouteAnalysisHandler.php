<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Route;
use App\Message\PostRouteAnalysisMessage;
use App\Service\PostRouteAnalyzer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Ulid;

#[AsMessageHandler]
final class PostRouteAnalysisHandler
{
    public function __construct(
        private readonly PostRouteAnalyzer $postRouteAnalyzer,
        private readonly EntityManagerInterface $entityManager,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(PostRouteAnalysisMessage $message): void
    {
        $route = $this->entityManager->getRepository(Route::class)->findOneBy([
            'publicId' => Ulid::fromString($message->getRoutePublicId()),
        ]);

        if ($route === null) {
            $this->logger?->warning('PostRouteAnalysisHandler: route {id} not found.', [
                'id' => $message->getRoutePublicId(),
            ]);

            return;
        }

        $this->logger?->info('Starting post-route analysis for route "{name}" ({id}).', [
            'name' => $route->getName(),
            'id' => $message->getRoutePublicId(),
        ]);

        $analysis = $this->postRouteAnalyzer->analyze($route);

        $route->setAiAnalysis($analysis);
        $this->entityManager->flush();

        $this->logger?->info('Post-route analysis completed for route "{name}".', [
            'name' => $route->getName(),
        ]);
    }
}
