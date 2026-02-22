<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Route;
use App\Entity\RouteStop;
use App\Entity\Shipment;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

final class EmailNotificationService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function sendDeliveryNotification(Shipment $shipment, RouteStop $stop): void
    {
        $customer = $shipment->getCustomer();
        $recipientEmail = $stop->getRecipientName(); // Contact info from customer

        $html = $this->twig->render('email/delivery_notification.html.twig', [
            'shipment' => $shipment,
            'stop' => $stop,
        ]);

        $email = (new Email())
            ->from('noreply@mxo-track.com')
            ->to($customer->getName() . ' <noreply@mxo-track.com>') // Would use real customer email
            ->subject(sprintf('Su envio %s ha sido entregado', $shipment->getReference()))
            ->html($html);

        $this->safeSend($email, 'delivery', $shipment->getReference());
    }

    public function sendExceptionNotification(Shipment $shipment, RouteStop $stop, string $reason): void
    {
        $html = $this->twig->render('email/exception_notification.html.twig', [
            'shipment' => $shipment,
            'stop' => $stop,
            'reason' => $reason,
        ]);

        $email = (new Email())
            ->from('noreply@mxo-track.com')
            ->to($shipment->getCustomer()->getName() . ' <noreply@mxo-track.com>')
            ->subject(sprintf('Incidencia en su envio %s', $shipment->getReference()))
            ->html($html);

        $this->safeSend($email, 'exception', $shipment->getReference());
    }

    public function sendRouteAssignedNotification(Route $route, User $driver): void
    {
        $html = $this->twig->render('email/route_assigned.html.twig', [
            'route' => $route,
            'driver' => $driver,
        ]);

        $email = (new Email())
            ->from('noreply@mxo-track.com')
            ->to($driver->getEmail())
            ->subject(sprintf('Nueva ruta asignada: %s', $route->getName()))
            ->html($html);

        $this->safeSend($email, 'route_assigned', $route->getName());
    }

    private function safeSend(Email $email, string $type, string $reference): void
    {
        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send {type} email for {reference}: {error}', [
                'type' => $type,
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
