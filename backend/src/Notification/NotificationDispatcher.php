<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\Shipment;
use App\Enum\NotificationTriggerType;
use App\Notification\Message\SendNotificationMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final class NotificationDispatcher
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly NotificationResolver $resolver,
    ) {}

    public function dispatchForShipment(
        Shipment $shipment,
        NotificationTriggerType $trigger,
    ): void {
        $commands = $this->resolver->resolve($shipment, $trigger);
        $phone = $shipment->getRecipientPhone();

        if ($phone === null || $phone === '') {
            return;
        }

        foreach ($commands as $command) {
            $message = new SendNotificationMessage(
                shipmentId: (int) $shipment->getId(),
                channel: $command->channel->value,
                triggerType: $trigger->value,
                recipientPhone: $phone,
                message: $command->message,
                timing: $command->timing,
            );

            $stamps = [];
            $delay = $this->calculateDelay($command->timing);
            if ($delay !== null) {
                $stamps[] = new DelayStamp($delay);
            }

            $this->bus->dispatch($message, $stamps);
        }
    }

    private function calculateDelay(array $timing): ?int
    {
        if (isset($timing['delay_minutes'])) {
            return $timing['delay_minutes'] * 60 * 1000;
        }

        return null;
    }
}
