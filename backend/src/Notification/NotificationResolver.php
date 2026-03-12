<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\Shipment;
use App\Enum\NotificationChannel;
use App\Enum\NotificationTriggerType;
use App\Repository\NotificationPreferenceRepository;

final class NotificationResolver
{
    public function __construct(
        private readonly NotificationPreferenceRepository $prefRepo,
        private readonly string $appBaseUrl,
    ) {}

    /** @return NotificationCommand[] */
    public function resolve(Shipment $shipment, NotificationTriggerType $trigger): array
    {
        $customerId = $shipment->getCustomer()->getId();
        $preferences = $customerId !== null
            ? $this->prefRepo->findEnabledByCustomerAndTrigger((int) $customerId, $trigger)
            : [];

        if (empty($preferences)) {
            $template = DefaultNotificationTemplates::resolve($trigger, NotificationChannel::Sms, null);
            $timing = DefaultNotificationTiming::resolve($trigger, []);
            $message = $this->renderTemplate($template, $shipment);

            return [new NotificationCommand($shipment, NotificationChannel::Sms, $message, $timing)];
        }

        $commands = [];
        foreach ($preferences as $pref) {
            $template = DefaultNotificationTemplates::resolve(
                $trigger,
                $pref->getChannel(),
                $pref->getMessageTemplate(),
            );
            $timing = DefaultNotificationTiming::resolve($trigger, $pref->getTimingConfig());
            $message = $this->renderTemplate($template, $shipment);

            $commands[] = new NotificationCommand($shipment, $pref->getChannel(), $message, $timing);
        }

        return $commands;
    }

    private function renderTemplate(string $template, Shipment $shipment): string
    {
        $trackingUrl = sprintf('%s/track/%s', rtrim($this->appBaseUrl, '/'), $shipment->getTrackingToken() ?? '');

        $timeWindow = '';
        if ($shipment->getPreferredWindowStart() !== null && $shipment->getPreferredWindowEnd() !== null) {
            $timeWindow = sprintf(
                '%s-%s',
                $shipment->getPreferredWindowStart()->format('H:i'),
                $shipment->getPreferredWindowEnd()->format('H:i'),
            );
        }

        return strtr($template, [
            '{recipient_name}' => $shipment->getRecipientName() ?? 'Cliente',
            '{tracking_url}' => $trackingUrl,
            '{time_window}' => $timeWindow,
            '{eta}' => '',
        ]);
    }
}
