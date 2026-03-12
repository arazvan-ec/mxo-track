<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification;

use App\Entity\Customer;
use App\Entity\Route;
use App\Entity\RouteStop;
use App\Entity\Shipment;
use App\Notification\Message\SendRecipientNotificationMessage;
use App\Notification\RecipientNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(RecipientNotificationService::class)]
final class RecipientNotificationServiceTest extends TestCase
{
    private MessageBusInterface $bus;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
    }

    private function createService(): RecipientNotificationService
    {
        return new RecipientNotificationService(
            $this->bus,
            $this->em,
            new NullLogger(),
        );
    }

    #[Test]
    public function notify_approaching_dispatches_message(): void
    {
        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getRecipientPhone')->willReturn('+34600000000');

        $route = $this->createMock(Route::class);
        $customer = $this->createMock(Customer::class);
        $customer->method('getId')->willReturn('42');
        $route->method('getCustomer')->willReturn($customer);

        $stop = $this->createMock(RouteStop::class);
        $stop->method('getShipment')->willReturn($shipment);
        $stop->method('getRecipientPhone')->willReturn('+34600000000');
        $stop->method('getId')->willReturn('1');
        $stop->method('getRoute')->willReturn($route);

        $this->bus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(function ($message): bool {
                self::assertInstanceOf(SendRecipientNotificationMessage::class, $message);
                self::assertSame('1', $message->routeStopId);
                self::assertSame('approaching', $message->notificationType);
                self::assertSame('42', $message->customerId);

                return true;
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $service = $this->createService();
        $service->notifyApproaching($stop);
    }

    #[Test]
    public function notify_approaching_does_nothing_when_shipment_null(): void
    {
        $stop = $this->createMock(RouteStop::class);
        $stop->method('getShipment')->willReturn(null);

        $this->bus->expects(self::never())->method('dispatch');

        $service = $this->createService();
        $service->notifyApproaching($stop);
    }

    #[Test]
    public function notify_approaching_does_nothing_when_no_phone(): void
    {
        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getRecipientPhone')->willReturn(null);

        $stop = $this->createMock(RouteStop::class);
        $stop->method('getShipment')->willReturn($shipment);
        $stop->method('getRecipientPhone')->willReturn(null);

        $this->bus->expects(self::never())->method('dispatch');

        $service = $this->createService();
        $service->notifyApproaching($stop);
    }

    #[Test]
    public function notify_delivered_dispatches_message(): void
    {
        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getRecipientPhone')->willReturn('+34600000000');

        $route = $this->createMock(Route::class);
        $route->method('getCustomer')->willReturn(null);

        $stop = $this->createMock(RouteStop::class);
        $stop->method('getShipment')->willReturn($shipment);
        $stop->method('getRecipientPhone')->willReturn('+34600000000');
        $stop->method('getId')->willReturn('5');
        $stop->method('getRoute')->willReturn($route);

        $this->bus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(function ($message): bool {
                self::assertInstanceOf(SendRecipientNotificationMessage::class, $message);
                self::assertSame('5', $message->routeStopId);
                self::assertSame('delivered', $message->notificationType);
                self::assertNull($message->customerId);

                return true;
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $service = $this->createService();
        $service->notifyDelivered($stop);
    }

    #[Test]
    public function notify_route_started_dispatches_per_stop(): void
    {
        $customer = $this->createMock(Customer::class);
        $customer->method('getId')->willReturn('10');

        $route = $this->createMock(Route::class);
        $route->method('getCustomer')->willReturn($customer);

        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getRecipientPhone')->willReturn('+34600000000');

        $originStop = $this->createMock(RouteStop::class);
        $originStop->method('isOrigin')->willReturn(true);

        $deliveryStop = $this->createMock(RouteStop::class);
        $deliveryStop->method('isOrigin')->willReturn(false);
        $deliveryStop->method('getShipment')->willReturn($shipment);
        $deliveryStop->method('getRecipientPhone')->willReturn('+34600000000');
        $deliveryStop->method('getId')->willReturn('7');

        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn([$originStop, $deliveryStop]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->em->method('createQueryBuilder')->willReturn($qb);

        $this->bus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(function ($message): bool {
                self::assertInstanceOf(SendRecipientNotificationMessage::class, $message);
                self::assertSame('7', $message->routeStopId);
                self::assertSame('route_started', $message->notificationType);
                self::assertSame('10', $message->customerId);

                return true;
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $service = $this->createService();
        $service->notifyRouteStarted($route);
    }
}
