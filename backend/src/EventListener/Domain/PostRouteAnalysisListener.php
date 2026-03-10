<?php

declare(strict_types=1);

namespace App\EventListener\Domain;

use App\Domain\Event\RouteCompleted;
use App\Message\PostRouteAnalysisMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsEventListener]
final readonly class PostRouteAnalysisListener
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(RouteCompleted $event): void
    {
        $this->messageBus->dispatch(new PostRouteAnalysisMessage($event->routePublicId));

        $this->logger->info('PostRouteAnalysisListener: dispatched analysis for route {id}', [
            'id' => $event->routePublicId,
        ]);
    }
}
