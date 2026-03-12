<?php

declare(strict_types=1);

namespace App\Notification\Message;

use App\Entity\Customer;
use App\Entity\RecipientNotification;
use App\Entity\RouteStop;
use App\Entity\Shipment;
use App\Notification\DeliveryApproachingNotification;
use App\Notification\DeliveryCompletedNotification;
use App\Notification\DeliverySlotConfirmedNotification;
use App\Notification\RatingRequestNotification;
use App\Notification\RescheduleConfirmedNotification;
use App\Notification\Transport\TenantAwareSmsTransport;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;

#[AsMessageHandler]
final class SendRecipientNotificationHandler
{
    public function __construct(
        private readonly NotifierInterface $notifier,
        private readonly TenantAwareSmsTransport $transport,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly string $appBaseUrl = '',
    ) {
    }

    public function __invoke(SendRecipientNotificationMessage $message): void
    {
        $stop = $this->em->find(RouteStop::class, $message->routeStopId);
        if ($stop === null) {
            $this->logger->warning('SendRecipientNotificationHandler: stop {id} not found', [
                'id' => $message->routeStopId,
            ]);

            return;
        }

        $shipment = $stop->getShipment();
        if ($shipment === null) {
            return;
        }

        $phone = $shipment->getRecipientPhone() ?? $stop->getRecipientPhone();
        if ($phone === null || $phone === '') {
            return;
        }

        if ($message->customerId !== null) {
            $customer = $this->em->find(Customer::class, $message->customerId);
            $this->transport->setCustomer($customer);
        }

        try {
            $recipientName = $shipment->getRecipientName() ?? $stop->getRecipientName() ?? 'Cliente';
            $notification = $this->buildNotification($message->notificationType, $stop, $shipment, $recipientName, $message->metadata);

            if ($notification === null) {
                $this->logger->warning('Unknown notification type: {type}', [
                    'type' => $message->notificationType,
                ]);

                return;
            }

            $recipient = new Recipient('', $phone);
            $this->notifier->send($notification, $recipient);

            $this->recordNotification($shipment, $phone, $notification->getTemplateName(), true);
        } catch (\Throwable $e) {
            $this->recordNotification($shipment, $phone, $message->notificationType, false, $e->getMessage());

            throw $e;
        } finally {
            $this->transport->setCustomer(null);
        }
    }

    /**
     * @return (Notification&\App\Notification\DeliveryApproachingNotification)|(Notification&\App\Notification\DeliveryCompletedNotification)|(Notification&\App\Notification\RatingRequestNotification)|(Notification&\App\Notification\DeliverySlotConfirmedNotification)|(Notification&\App\Notification\RescheduleConfirmedNotification)|null
     */
    /**
     * @param array<string, string> $metadata
     */
    private function buildNotification(
        string $type,
        RouteStop $stop,
        Shipment $shipment,
        string $recipientName,
        array $metadata = [],
    ): ?Notification {
        $trackingUrl = $this->buildTrackingUrl($shipment->getTrackingToken());
        $ratingUrl = $this->buildRatingUrl($shipment->getTrackingToken());
        $driverName = $stop->getRoute()->getDriver()?->getName() ?? 'Su conductor';

        return match ($type) {
            'approaching', 'route_started' => new DeliveryApproachingNotification(
                $recipientName,
                $driverName,
                new \DateTimeImmutable('+30 minutes'),
                $trackingUrl,
            ),
            'delivered' => new DeliveryCompletedNotification(
                $recipientName,
                $shipment->getReference(),
                $ratingUrl,
            ),
            'rating_request' => new RatingRequestNotification(
                $recipientName,
                $ratingUrl,
            ),
            'slot_confirmed' => new DeliverySlotConfirmedNotification(
                $recipientName,
                $metadata['slot_date'] ?? '',
                $metadata['slot_time_range'] ?? '',
            ),
            'rescheduled' => new RescheduleConfirmedNotification(
                $recipientName,
                $metadata['slot_date'] ?? '',
                $metadata['slot_time_range'] ?? '',
                $trackingUrl,
            ),
            default => null,
        };
    }

    private function recordNotification(
        Shipment $shipment,
        string $phone,
        string $templateName,
        bool $success,
        ?string $errorMessage = null,
    ): void {
        $notification = new RecipientNotification(
            $shipment,
            'sms',
            $templateName,
            $phone,
        );

        if ($success) {
            $notification->markSent();
        } else {
            $notification->markFailed($errorMessage ?? 'Unknown error');
        }

        $this->em->persist($notification);
        $this->em->flush();
    }

    private function buildTrackingUrl(?string $trackingToken): string
    {
        if ($trackingToken === null || $trackingToken === '') {
            return '';
        }

        return rtrim($this->appBaseUrl, '/') . '/track/' . $trackingToken;
    }

    private function buildRatingUrl(?string $trackingToken): string
    {
        if ($trackingToken === null || $trackingToken === '') {
            return '';
        }

        return rtrim($this->appBaseUrl, '/') . '/track/' . $trackingToken . '/rate';
    }
}
