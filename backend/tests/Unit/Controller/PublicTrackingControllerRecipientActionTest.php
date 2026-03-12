<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Application\Tracking\PublicTrackingService;
use App\Application\Tracking\TrackingInfo;
use App\Controller\PublicTrackingController;
use App\Entity\RecipientAction;
use App\Entity\Shipment;
use App\Enum\RecipientActionType;
use App\Notification\DeliveryRatingService;
use App\Notification\DeliverySlotService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(PublicTrackingController::class)]
final class PublicTrackingControllerRecipientActionTest extends TestCase
{
    private PublicTrackingService $trackingService;
    private EntityManagerInterface $em;
    private PublicTrackingController $controller;

    protected function setUp(): void
    {
        $this->trackingService = $this->createMock(PublicTrackingService::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $bus = $this->createMock(MessageBusInterface::class);
        $deliverySlotService = $this->createMock(DeliverySlotService::class);
        $deliveryRatingService = $this->createMock(DeliveryRatingService::class);

        $this->controller = new PublicTrackingController(
            $this->trackingService,
            $deliverySlotService,
            $deliveryRatingService,
            $bus,
            $this->em,
            new NullLogger(),
        );
    }

    #[Test]
    public function confirmPresenceCreatesPresenceConfirmedAction(): void
    {
        $shipment = $this->createMock(Shipment::class);
        $info = new TrackingInfo($shipment, [], null, null, false);
        $this->trackingService->method('trackByToken')->with('TRK-ABCD-1234')->willReturn($info);

        $persisted = null;
        $this->em->method('persist')->willReturnCallback(function (object $entity) use (&$persisted) {
            $persisted = $entity;
        });
        $this->em->expects(self::once())->method('flush');

        $request = new Request(request: ['confirmed' => '1']);
        $response = $this->controller->confirmPresence('TRK-ABCD-1234', $request);

        self::assertInstanceOf(RecipientAction::class, $persisted);
        self::assertSame(RecipientActionType::PresenceConfirmed, $persisted->getActionType());
        self::assertSame(['confirmed' => true], $persisted->getPayload());
    }

    #[Test]
    public function confirmPresenceWithDeniedCreatesPresenceDeniedAction(): void
    {
        $shipment = $this->createMock(Shipment::class);
        $info = new TrackingInfo($shipment, [], null, null, false);
        $this->trackingService->method('trackByToken')->with('TRK-ABCD-1234')->willReturn($info);

        $persisted = null;
        $this->em->method('persist')->willReturnCallback(function (object $entity) use (&$persisted) {
            $persisted = $entity;
        });

        $request = new Request(request: ['confirmed' => '0']);
        $response = $this->controller->confirmPresence('TRK-ABCD-1234', $request);

        self::assertInstanceOf(RecipientAction::class, $persisted);
        self::assertSame(RecipientActionType::PresenceDenied, $persisted->getActionType());
        self::assertSame(['confirmed' => false], $persisted->getPayload());
    }

    #[Test]
    public function confirmPresenceThrows404ForInvalidToken(): void
    {
        $this->trackingService->method('trackByToken')->willReturn(null);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        $request = new Request(request: ['confirmed' => '1']);
        $this->controller->confirmPresence('INVALID', $request);
    }

    #[Test]
    public function alternativeCreatesAlternativeRequestedAction(): void
    {
        $shipment = $this->createMock(Shipment::class);
        $info = new TrackingInfo($shipment, [], null, null, false);
        $this->trackingService->method('trackByToken')->with('TRK-ABCD-1234')->willReturn($info);

        $persisted = null;
        $this->em->method('persist')->willReturnCallback(function (object $entity) use (&$persisted) {
            $persisted = $entity;
        });
        $this->em->expects(self::once())->method('flush');

        $request = new Request(request: ['option' => 'porteria', 'instructions' => 'Piso 3']);
        $response = $this->controller->alternative('TRK-ABCD-1234', $request);

        self::assertInstanceOf(RecipientAction::class, $persisted);
        self::assertSame(RecipientActionType::AlternativeRequested, $persisted->getActionType());
        self::assertSame(['option' => 'porteria', 'instructions' => 'Piso 3'], $persisted->getPayload());
    }

    #[Test]
    public function alternativeThrows404ForInvalidToken(): void
    {
        $this->trackingService->method('trackByToken')->willReturn(null);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        $request = new Request(request: ['option' => 'porteria']);
        $this->controller->alternative('INVALID', $request);
    }
}
