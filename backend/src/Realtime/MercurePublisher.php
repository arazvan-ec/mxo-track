<?php
declare(strict_types=1);
namespace App\Realtime;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final readonly class MercurePublisher implements RealtimePublisherInterface
{
    public function __construct(
        private HubInterface $hub,
        private LoggerInterface $logger,
    ) {
    }

    public function publish(SseMessage $message): void
    {
        $data = \is_array($message->data)
            ? json_encode($message->data, JSON_THROW_ON_ERROR)
            : $message->data;

        try {
            $this->hub->publish(new Update(
                topics: $message->topics,
                data: $data,
                private: $message->private,
                id: $message->id,
                type: $message->type,
                retry: $message->retry,
            ));
        } catch (\Throwable $e) {
            $this->logger->error('Mercure publish failed.', [
                'topics' => $message->topics,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /** @param list<SseMessage> $messages */
    public function publishBatch(array $messages): void
    {
        foreach ($messages as $message) {
            $this->publish($message);
        }
    }
}
