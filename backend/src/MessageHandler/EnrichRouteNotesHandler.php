<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Route;
use App\Message\EnrichRouteNotesMessage;
use App\Service\DeliveryNoteAiEnricher;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class EnrichRouteNotesHandler
{
    public function __construct(
        private readonly DeliveryNoteAiEnricher $enricher,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(EnrichRouteNotesMessage $message): void
    {
        $route = $this->em->find(Route::class, $message->getRouteId());

        if ($route === null) {
            $this->logger->warning('EnrichRouteNotesHandler: Route {id} not found, skipping.', [
                'id' => $message->getRouteId(),
            ]);

            return;
        }

        $count = $this->enricher->enrichRoute($route);

        $this->logger->info('EnrichRouteNotesHandler: enriched {count} stops for route "{name}".', [
            'count' => $count,
            'name' => $route->getName(),
        ]);
    }
}
