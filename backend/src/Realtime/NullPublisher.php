<?php
declare(strict_types=1);
namespace App\Realtime;

use Psr\Log\LoggerInterface;

final readonly class NullPublisher implements RealtimePublisherInterface
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function publish(SseMessage $message): void
    {
        $this->logger->debug('NullPublisher::publish called.', [
            'topics' => $message->topics,
        ]);
    }

    /** @param list<SseMessage> $messages */
    public function publishBatch(array $messages): void
    {
        $this->logger->debug('NullPublisher::publishBatch called.', [
            'count' => \count($messages),
        ]);
    }
}
