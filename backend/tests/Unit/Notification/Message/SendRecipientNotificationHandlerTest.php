<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Message;

use App\Entity\Customer;
use App\Entity\RecipientNotification;
use App\Entity\Route;
use App\Entity\RouteStop;
use App\Entity\Shipment;
use App\Notification\Message\SendRecipientNotificationHandler;
use App\Notification\Message\SendRecipientNotificationMessage;
use App\Notification\Transport\TenantAwareSmsTransport;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Notifier\NotifierInterface;

#[CoversClass(SendRecipientNotificationHandler::class)]
final class SendRecipientNotificationHandlerTest extends TestCase
{
    private EntityManagerInterface $em;
    private NotifierInterface $notifier;
    private TenantAwareSmsTransport $transport;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->notifier = $this->createMock(NotifierInterface::class);
        $this->transport = $this->createMock(TenantAwareSmsTransport::class);
    }

    private function createHandler(): SendRecipientNotificationHandler
    {
        return new SendRecipientNotificationHandler(
            $this->notifier,
            $this->transport,
            $this->em,
            new NullLogger(),
            'https://example.com',
        );
    }

    #[Test]
    public function does_nothing_when_stop_not_found(): void
    {
        $this->em->method('find')->willReturn(null);
        $this->notifier->expects(self::never())->method('send');

        $handler = $this->createHandler();
        $handler(new SendRecipientNotificationMessage('999', 'approaching'));
    }

    #[Test]
    public function does_nothing_when_shipment_is_null(): void
    {
        $stop = $this->createMock(RouteStop::class);
        $stop->method('getShipment')->willReturn(null);

        $this->em->method('find')->willReturn($stop);
        $this->notifier->expects(self::never())->method('send');

        $handler = $this->createHandler();
        $handler(new SendRecipientNotificationMessage('1', 'approaching'));
    }

    #[Test]
    public function does_nothing_when_phone_is_empty(): void
    {
        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getRecipientPhone')->willReturn(null);

        $stop = $this->createMock(RouteStop::class);
        $stop->method('getShipment')->willReturn($shipment);
        $stop->method('getRecipientPhone')->willReturn(null);

        $this->em->method('find')->willReturn($stop);
        $this->notifier->expects(self::never())->method('send');

        $handler = $this->createHandler();
        $handler(new SendRecipientNotificationMessage('1', 'approaching'));
    }

    #[Test]
    public function sends_approaching_notification(): void
    {
        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getRecipientPhone')->willReturn('+34600000000');
        $shipment->method('getRecipientName')->willReturn('Juan');
        $shipment->method('getTrackingToken')->willReturn('abc123');

        $route = $this->createMock(Route::class);
        $route->method('getDriver')->willReturn(null);

        $stop = $this->createMock(RouteStop::class);
        $stop->method('getShipment')->willReturn($shipment);
        $stop->method('getRecipientPhone')->willReturn('+34600000000');
        $stop->method('getRoute')->willReturn($route);

        $this->em->method('find')->willReturn($stop);
        $this->notifier->expects(self::once())->method('send');
        $this->em->expects(self::once())->method('persist')->with(self::isInstanceOf(RecipientNotification::class));
        $this->em->expects(self::once())->method('flush');

        $handler = $this->createHandler();
        $handler(new SendRecipientNotificationMessage('1', 'approaching'));
    }

    #[Test]
    public function sends_delivered_notification(): void
    {
        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getRecipientPhone')->willReturn('+34600000000');
        $shipment->method('getRecipientName')->willReturn('Juan');
        $shipment->method('getReference')->willReturn('REF-001');
        $shipment->method('getTrackingToken')->willReturn('abc123');

        $stop = $this->createMock(RouteStop::class);
        $stop->method('getShipment')->willReturn($shipment);
        $stop->method('getRecipientPhone')->willReturn('+34600000000');

        $this->em->method('find')->willReturn($stop);
        $this->notifier->expects(self::once())->method('send');
        $this->em->expects(self::once())->method('persist');

        $handler = $this->createHandler();
        $handler(new SendRecipientNotificationMessage('1', 'delivered'));
    }

    #[Test]
    public function sets_customer_on_transport_when_customer_id_provided(): void
    {
        $customer = $this->createMock(Customer::class);

        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getRecipientPhone')->willReturn('+34600000000');
        $shipment->method('getRecipientName')->willReturn('Juan');
        $shipment->method('getTrackingToken')->willReturn('abc123');

        $route = $this->createMock(Route::class);
        $route->method('getDriver')->willReturn(null);

        $stop = $this->createMock(RouteStop::class);
        $stop->method('getShipment')->willReturn($shipment);
        $stop->method('getRecipientPhone')->willReturn('+34600000000');
        $stop->method('getRoute')->willReturn($route);

        $this->em->method('find')->willReturnCallback(function (string $class, mixed $id) use ($stop, $customer) {
            if ($class === RouteStop::class) {
                return $stop;
            }
            if ($class === Customer::class) {
                return $customer;
            }

            return null;
        });

        $this->transport->expects(self::exactly(2))->method('setCustomer')
            ->willReturnCallback(function (?Customer $c) use ($customer): void {
                static $callCount = 0;
                $callCount++;
                if ($callCount === 1) {
                    self::assertSame($customer, $c);
                } else {
                    self::assertNull($c);
                }
            });

        $this->notifier->expects(self::once())->method('send');

        $handler = $this->createHandler();
        $handler(new SendRecipientNotificationMessage('1', 'approaching', '42'));
    }

    #[Test]
    public function records_failed_notification_and_rethrows_on_error(): void
    {
        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getRecipientPhone')->willReturn('+34600000000');
        $shipment->method('getRecipientName')->willReturn('Juan');
        $shipment->method('getTrackingToken')->willReturn('abc123');

        $route = $this->createMock(Route::class);
        $route->method('getDriver')->willReturn(null);

        $stop = $this->createMock(RouteStop::class);
        $stop->method('getShipment')->willReturn($shipment);
        $stop->method('getRecipientPhone')->willReturn('+34600000000');
        $stop->method('getRoute')->willReturn($route);

        $this->em->method('find')->willReturn($stop);
        $this->notifier->method('send')->willThrowException(new \RuntimeException('Twilio error'));
        $this->em->expects(self::once())->method('persist');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Twilio error');

        $handler = $this->createHandler();
        $handler(new SendRecipientNotificationMessage('1', 'approaching'));
    }

    #[Test]
    public function sends_rescheduled_notification_with_metadata(): void
    {
        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getRecipientPhone')->willReturn('+34600000000');
        $shipment->method('getRecipientName')->willReturn('Juan');
        $shipment->method('getTrackingToken')->willReturn('abc123');

        $route = $this->createMock(Route::class);
        $route->method('getDriver')->willReturn(null);

        $stop = $this->createMock(RouteStop::class);
        $stop->method('getShipment')->willReturn($shipment);
        $stop->method('getRecipientPhone')->willReturn('+34600000000');
        $stop->method('getRoute')->willReturn($route);

        $this->em->method('find')->willReturn($stop);
        $this->notifier->expects(self::once())->method('send')
            ->with(self::callback(function ($notification): bool {
                self::assertInstanceOf(\App\Notification\RescheduleConfirmedNotification::class, $notification);
                $sms = $notification->asSmsMessage(new \Symfony\Component\Notifier\Recipient\Recipient('', '+34600000000'));
                self::assertStringContainsString('2026-03-15', $sms->getSubject());
                self::assertStringContainsString('10:00-12:00', $sms->getSubject());

                return true;
            }), self::anything());

        $handler = $this->createHandler();
        $handler(new SendRecipientNotificationMessage('1', 'rescheduled', null, [
            'slot_date' => '2026-03-15',
            'slot_time_range' => '10:00-12:00',
        ]));
    }

    #[Test]
    public function sends_slot_confirmed_notification_with_metadata(): void
    {
        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getRecipientPhone')->willReturn('+34600000000');
        $shipment->method('getRecipientName')->willReturn('Juan');
        $shipment->method('getTrackingToken')->willReturn('abc123');

        $route = $this->createMock(Route::class);
        $route->method('getDriver')->willReturn(null);

        $stop = $this->createMock(RouteStop::class);
        $stop->method('getShipment')->willReturn($shipment);
        $stop->method('getRecipientPhone')->willReturn('+34600000000');
        $stop->method('getRoute')->willReturn($route);

        $this->em->method('find')->willReturn($stop);
        $this->notifier->expects(self::once())->method('send')
            ->with(self::callback(function ($notification): bool {
                self::assertInstanceOf(\App\Notification\DeliverySlotConfirmedNotification::class, $notification);
                $sms = $notification->asSmsMessage(new \Symfony\Component\Notifier\Recipient\Recipient('', '+34600000000'));
                self::assertStringContainsString('2026-03-16', $sms->getSubject());
                self::assertStringContainsString('14:00-16:00', $sms->getSubject());

                return true;
            }), self::anything());

        $handler = $this->createHandler();
        $handler(new SendRecipientNotificationMessage('1', 'slot_confirmed', null, [
            'slot_date' => '2026-03-16',
            'slot_time_range' => '14:00-16:00',
        ]));
    }

    #[Test]
    public function does_nothing_for_unknown_notification_type(): void
    {
        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getRecipientPhone')->willReturn('+34600000000');
        $shipment->method('getRecipientName')->willReturn('Juan');

        $stop = $this->createMock(RouteStop::class);
        $stop->method('getShipment')->willReturn($shipment);
        $stop->method('getRecipientPhone')->willReturn('+34600000000');

        $this->em->method('find')->willReturn($stop);
        $this->notifier->expects(self::never())->method('send');

        $handler = $this->createHandler();
        $handler(new SendRecipientNotificationMessage('1', 'unknown_type'));
    }
}
