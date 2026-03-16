<?php

declare(strict_types=1);

namespace App\EventListener\Domain;

use App\Domain\Route\Event\RouteStarted;
use App\Entity\Route;
use App\Message\EnrichRouteNotesMessage;
use App\Repository\RouteRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsEventListener]
final readonly class AiEnrichmentListener
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private RouteRepository $routeRepo,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(RouteStarted $event): void
    {
        $route = $this->routeRepo->findOneByPublicId($event->routePublicId);

        if (!$route instanceof Route) {
            $this->logger->warning('AiEnrichmentListener: route not found for publicId {id}', [
                'id' => $event->routePublicId,
            ]);

            return;
        }

        $this->messageBus->dispatch(new EnrichRouteNotesMessage($route->getId()));

        $this->logger->info('AiEnrichmentListener: dispatched EnrichRouteNotesMessage for route "{name}"', [
            'name' => $route->getName(),
        ]);
    }
}
