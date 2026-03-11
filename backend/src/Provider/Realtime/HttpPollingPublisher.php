<?php

declare(strict_types=1);

namespace App\Provider\Realtime;

use App\Entity\RealtimeEvent;
use App\Provider\TenantContext;
use App\Realtime\RealtimePublisherInterface;
use App\Realtime\SseMessage;
use Doctrine\ORM\EntityManagerInterface;

final class HttpPollingPublisher implements RealtimePublisherInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function publish(SseMessage $message): void
    {
        $customer = $this->tenantContext->getCustomer();
        if ($customer === null) {
            return;
        }

        $data = is_array($message->data) ? $message->data : ['raw' => $message->data];

        foreach ($message->topics as $topic) {
            $event = new RealtimeEvent($customer, $topic, $data, $message->type);
            $this->em->persist($event);
        }

        $this->em->flush();
    }

    public function publishBatch(array $messages): void
    {
        foreach ($messages as $message) {
            $this->publish($message);
        }
    }
}
