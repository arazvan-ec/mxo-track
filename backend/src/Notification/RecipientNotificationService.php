<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\RecipientNotification;
use App\Entity\Route;
use App\Entity\RouteStop;
use App\Notification\Channel\NotificationChannelInterface;
use App\Notification\Template\DeliveryCompletedTemplate;
use App\Notification\Template\NotificationTemplate;
use App\Notification\Template\PreDeliveryTemplate;
use App\Notification\Template\RatingRequestTemplate;
use App\Service\EtaService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class RecipientNotificationService
{
    /**
     * @param iterable<NotificationChannelInterface> $channels
     */
    public function __construct(
        private readonly iterable $channels,
        private readonly EntityManagerInterface $em,
        private readonly EtaService $etaService,
        private readonly LoggerInterface $logger,
        private readonly string $appBaseUrl = '',
    ) {
    }

    /**
     * Send ETA notifications to all recipients when a route starts.
     */
    public function notifyRouteStarted(Route $route): void
    {
        $etas = $this->etaService->calculateEtas($route);

        $stops = $this->em->createQueryBuilder()
            ->select('s')
            ->from(RouteStop::class, 's')
            ->where('s.route = :route')
            ->setParameter('route', $route)
            ->orderBy('s.sequence', 'ASC')
            ->getQuery()
            ->getResult();

        $driverName = $route->getDriver()?->getName() ?? 'Su conductor';

        /** @var RouteStop $stop */
        foreach ($stops as $stop) {
            if ($stop->isOrigin()) {
                continue;
            }

            $shipment = $stop->getShipment();
            if ($shipment === null) {
                continue;
            }

            $phone = $shipment->getRecipientPhone() ?? $stop->getRecipientPhone();
            if ($phone === null || $phone === '') {
                continue;
            }

            $recipientName = $shipment->getRecipientName() ?? $stop->getRecipientName() ?? 'Cliente';
            $stopPublicId = $stop->getPublicIdString();
            $eta = $etas[$stopPublicId] ?? null;

            if ($eta === null) {
                continue;
            }

            $trackingUrl = $this->buildTrackingUrl($shipment->getTrackingToken());

            $template = new PreDeliveryTemplate(
                $recipientName,
                $driverName,
                $eta['eta'],
                $trackingUrl,
            );

            $this->sendAndRecord($shipment, $phone, RecipientPreference::CHANNEL_SMS, $template);
        }
    }

    /**
     * Send approaching notification when driver is N stops away.
     */
    public function notifyApproaching(RouteStop $stop): void
    {
        $shipment = $stop->getShipment();
        if ($shipment === null) {
            return;
        }

        $phone = $shipment->getRecipientPhone() ?? $stop->getRecipientPhone();
        if ($phone === null || $phone === '') {
            return;
        }

        $recipientName = $shipment->getRecipientName() ?? $stop->getRecipientName() ?? 'Cliente';
        $driverName = $stop->getRoute()->getDriver()?->getName() ?? 'Su conductor';
        $trackingUrl = $this->buildTrackingUrl($shipment->getTrackingToken());

        $template = new PreDeliveryTemplate(
            $recipientName,
            $driverName,
            new \DateTimeImmutable('+30 minutes'),
            $trackingUrl,
        );

        $this->sendAndRecord($shipment, $phone, RecipientPreference::CHANNEL_SMS, $template);
    }

    /**
     * Send delivery confirmation with rating link.
     */
    public function notifyDelivered(RouteStop $stop): void
    {
        $shipment = $stop->getShipment();
        if ($shipment === null) {
            return;
        }

        $phone = $shipment->getRecipientPhone() ?? $stop->getRecipientPhone();
        if ($phone === null || $phone === '') {
            return;
        }

        $recipientName = $shipment->getRecipientName() ?? $stop->getRecipientName() ?? 'Cliente';
        $ratingUrl = $this->buildRatingUrl($shipment->getTrackingToken());

        $template = new DeliveryCompletedTemplate(
            $recipientName,
            $shipment->getReference(),
            $ratingUrl,
        );

        $this->sendAndRecord($shipment, $phone, RecipientPreference::CHANNEL_SMS, $template);
    }

    /**
     * Generic notify method used by channels.
     */
    public function notify(string $recipient, string $channelType, NotificationTemplate $template): bool
    {
        foreach ($this->channels as $channel) {
            if ($channel->supports($channelType)) {
                return $channel->send($recipient, $template);
            }
        }

        $this->logger->warning('No notification channel found for type {type}', [
            'type' => $channelType,
            'template' => $template->getTemplateName(),
        ]);

        return false;
    }

    private function sendAndRecord(
        \App\Entity\Shipment $shipment,
        string $phone,
        string $channelType,
        NotificationTemplate $template,
    ): void {
        $notification = new RecipientNotification(
            $shipment,
            $channelType,
            $template->getTemplateName(),
            $phone,
        );

        $success = $this->notify($phone, $channelType, $template);

        if ($success) {
            $notification->markSent();
        } else {
            $notification->markFailed('Channel delivery failed');
        }

        $this->em->persist($notification);
        $this->em->flush();

        $this->logger->info('Recipient notification {status} for shipment {ref}', [
            'status' => $notification->getStatus(),
            'ref' => $shipment->getReference(),
            'template' => $template->getTemplateName(),
            'phone' => $phone,
        ]);
    }

    private function buildTrackingUrl(?string $trackingToken): string
    {
        if ($trackingToken === null || $trackingToken === '') {
            return '';
        }

        $base = rtrim($this->appBaseUrl, '/');

        return $base . '/track/' . $trackingToken;
    }

    private function buildRatingUrl(?string $trackingToken): string
    {
        if ($trackingToken === null || $trackingToken === '') {
            return '';
        }

        $base = rtrim($this->appBaseUrl, '/');

        return $base . '/track/' . $trackingToken . '/rate';
    }
}
