<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\ShipmentEvent;
use App\Message\NlpClassificationMessage;
use App\MessageHandler\NlpClassificationHandler;
use App\Service\ExceptionClassifierService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(NlpClassificationHandler::class)]
final class NlpClassificationHandlerTest extends TestCase
{
    private ExceptionClassifierService&MockObject $classifier;
    private EntityManagerInterface&MockObject $em;
    private NlpClassificationHandler $handler;

    protected function setUp(): void
    {
        $this->classifier = $this->createMock(ExceptionClassifierService::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->handler = new NlpClassificationHandler(
            $this->classifier,
            $this->em,
            new NullLogger(),
        );
    }

    #[Test]
    public function invokeClassifiesAndPersistsResult(): void
    {
        $event = $this->createShipmentEvent();
        $classification = [
            'subcategory' => 'ACCESO_EDIFICIO',
            'actionable_insight' => 'Portero no disponible',
            'suggested_action' => 'Llamar antes',
            'confidence' => 0.9,
        ];

        $this->em->method('find')
            ->with(ShipmentEvent::class, 42)
            ->willReturn($event);

        $this->classifier->method('classify')
            ->with('No pude entrar', 'DELIVERY_FAILED')
            ->willReturn($classification);

        $this->em->expects(self::once())->method('flush');

        ($this->handler)(new NlpClassificationMessage(42, 'No pude entrar', 'DELIVERY_FAILED'));

        $payload = $event->getPayload();
        self::assertArrayHasKey('ai_classification', $payload);
        self::assertSame('ACCESO_EDIFICIO', $payload['ai_classification']['subcategory']);
        self::assertSame(0.9, $payload['ai_classification']['confidence']);
    }

    #[Test]
    public function invokeSkipsWhenEventNotFound(): void
    {
        $this->em->method('find')
            ->with(ShipmentEvent::class, 999)
            ->willReturn(null);

        $this->classifier->expects(self::never())->method('classify');
        $this->em->expects(self::never())->method('flush');

        ($this->handler)(new NlpClassificationMessage(999, 'test', 'TEST'));
    }

    #[Test]
    public function invokeMergesWithExistingPayload(): void
    {
        $event = $this->createShipmentEvent(['existing_key' => 'existing_value']);

        $this->em->method('find')->willReturn($event);
        $this->classifier->method('classify')->willReturn([
            'subcategory' => 'OTRO',
            'actionable_insight' => null,
            'suggested_action' => null,
            'confidence' => 0.5,
        ]);

        $this->em->expects(self::once())->method('flush');

        ($this->handler)(new NlpClassificationMessage(1, 'notes', 'CODE'));

        $payload = $event->getPayload();
        self::assertSame('existing_value', $payload['existing_key']);
        self::assertArrayHasKey('ai_classification', $payload);
    }

    private function createShipmentEvent(array $payload = []): ShipmentEvent
    {
        $shipment = $this->createMock(\App\Entity\Shipment::class);
        $event = new ShipmentEvent($shipment, \App\Enum\ShipmentEventType::EXCEPTION, $payload);

        return $event;
    }
}
