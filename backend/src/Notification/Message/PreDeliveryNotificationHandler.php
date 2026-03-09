<?php

declare(strict_types=1);

namespace App\Notification\Message;

use App\Entity\RouteStop;
use App\Entity\Shipment;
use App\Notification\RecipientNotificationService;
use App\Notification\RecipientPreference;
use App\Notification\Template\PreDeliveryTemplate;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Ulid;

#[AsMessageHandler]
final class PreDeliveryNotificationHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RecipientNotificationService $notificationService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(PreDeliveryNotificationMessage $message): void
    {
        $shipment = $this->entityManager->getRepository(Shipment::class)->findOneBy([
            'publicId' => Ulid::fromString($message->getShipmentPublicId()),
        ]);

        if ($shipment === null) {
            $this->logger->warning('PreDeliveryNotification: shipment {id} not found', [
                'id' => $message->getShipmentPublicId(),
            ]);

            return;
        }

        $driverName = $this->resolveDriverName($shipment);
        $trackingUrl = $this->buildTrackingUrl($shipment);

        $template = new PreDeliveryTemplate(
            $shipment->getRecipientName() ?? 'Cliente',
            $driverName,
            $message->getEstimatedArrival(),
            $trackingUrl,
        );

        $sent = $this->notificationService->notify(
            $message->getRecipientPhone(),
            RecipientPreference::CHANNEL_SMS,
            $template,
        );

        if ($sent) {
            $this->logger->info('Pre-delivery notification sent for shipment {ref}', [
                'ref' => $shipment->getReference(),
            ]);
        }
    }

    private function resolveDriverName(Shipment $shipment): string
    {
        $routeStop = $this->entityManager->getRepository(RouteStop::class)->findOneBy([
            'shipment' => $shipment,
        ]);

        if ($routeStop === null) {
            return 'Su conductor';
        }

        $route = $routeStop->getRoute();
        $driver = $route->getDriver();

        if ($driver === null) {
            return 'Su conductor';
        }

        return $driver->getName() ?? $driver->getEmail();
    }

    private function buildTrackingUrl(Shipment $shipment): string
    {
        $token = $shipment->getTrackingToken();

        if ($token === null) {
            return '';
        }

        return sprintf('/tracking/%s', $token);
    }
}
