<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Application\Delivery\DeliveryContext;
use App\Application\Delivery\DeliveryService;
use App\Application\Delivery\DriverConfirmationRequiredException;
use App\Application\Delivery\DriverNotOwnerException;
use App\Application\Delivery\StopNotFoundException;
use App\Dto\Driver\DeliverStopInput;
use App\Dto\Driver\ExceptionStopInput;
use App\Entity\Pod;
use App\Domain\Route\Model\Route;
use App\Domain\Route\Model\RouteStop;
use App\Entity\Shipment;
use App\Entity\ShipmentEvent;
use App\Entity\User;
use App\Domain\Route\Repository\RouteStopRepositoryInterface;
use App\Domain\Shipment\Repository\ShipmentRepositoryInterface;
use App\Service\AuditLogger;
use App\Service\DeliveryEvidenceFactory;
use App\Service\DriverActionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[CoversClass(DeliveryService::class)]
final class DeliveryServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private DriverActionService&MockObject $driverActionService;
    private DeliveryEvidenceFactory&MockObject $evidenceFactory;
    private AuditLogger&MockObject $auditLogger;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private MessageBusInterface&MockObject $messageBus;
    private RouteStopRepositoryInterface&MockObject $stopRepo;
    private ShipmentRepositoryInterface&MockObject $shipmentRepo;
    private DeliveryService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->driverActionService = $this->createMock(DriverActionService::class);
        $this->evidenceFactory = $this->createMock(DeliveryEvidenceFactory::class);
        $this->auditLogger = $this->createMock(AuditLogger::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->stopRepo = $this->createMock(RouteStopRepositoryInterface::class);
        $this->shipmentRepo = $this->createMock(ShipmentRepositoryInterface::class);

        $this->service = new DeliveryService(
            $this->em,
            $this->driverActionService,
            $this->evidenceFactory,
            $this->auditLogger,
            $this->eventDispatcher,
            $this->messageBus,
            $this->stopRepo,
            $this->shipmentRepo,
        );
    }

    #[Test]
    public function deliverStopSuccessfullyCreatesPodAndEvent(): void
    {
        $driver = $this->createDriver('1');
        $route = new Route('Test Route');
        $route->setDriver($driver);
        $this->initPublicId($route);
        $stop = new RouteStop($route, 1, 'Calle Mayor 1');
        $this->setEntityId($stop, '10');
        $this->initPublicId($stop);

        $stopPublicId = $stop->getPublicIdString();

        $this->stopRepo->method('findOneByPublicId')->with($stopPublicId)->willReturn($stop);

        $this->driverActionService->method('register')->willReturn(true);

        $shipment = $this->createMock(Shipment::class);
        $this->shipmentRepo->method('findOneByPublicId')->willReturn($shipment);

        $this->evidenceFactory->method('build')->willReturn(['confirmation_mode' => 'recipient_id_encoded']);

        // Simulate @PrePersist lifecycle callback for entities with PublicIdTrait
        $this->em->expects(self::atLeastOnce())->method('persist')
            ->willReturnCallback(function (object $entity): void {
                if (method_exists($entity, 'initializePublicId')) {
                    $entity->initializePublicId();
                }
            });
        $this->em->expects(self::once())->method('flush');
        $this->eventDispatcher->expects(self::once())->method('dispatch');

        $input = DeliverStopInput::fromArray([
            'client_action_id' => Uuid::v4()->toRfc4122(),
            'signed_by_name' => 'Juan Perez',
            'recipient_id_encoded' => 'encoded-id-12345',
            'confirmed_by_driver' => true,
            'shipment_public_id' => (new Ulid())->toBase32(),
        ]);

        $result = $this->service->deliverStop($stopPublicId, $input, $driver);

        self::assertFalse($result->idempotent);
        self::assertNotNull($result->podPublicId);
    }

    #[Test]
    public function deliverStopIdempotentWhenDuplicateAction(): void
    {
        $driver = $this->createDriver('1');
        $route = new Route('Test Route');
        $route->setDriver($driver);
        $stop = new RouteStop($route, 1, 'Calle Mayor 1');
        $this->initPublicId($stop);

        $stopPublicId = $stop->getPublicIdString();
        $this->stopRepo->method('findOneByPublicId')->willReturn($stop);

        $this->driverActionService->method('register')->willReturn(false);

        $this->em->expects(self::never())->method('flush');

        $input = DeliverStopInput::fromArray([
            'client_action_id' => Uuid::v4()->toRfc4122(),
            'signed_by_name' => 'Juan Perez',
            'recipient_id_encoded' => 'encoded-id-12345',
            'confirmed_by_driver' => true,
        ]);

        $result = $this->service->deliverStop($stopPublicId, $input, $driver);

        self::assertTrue($result->idempotent);
    }

    #[Test]
    public function deliverStopThrowsWhenDriverNotOwner(): void
    {
        $driver = $this->createDriver('1');
        $otherDriver = $this->createDriver('2');

        $route = new Route('Test Route');
        $route->setDriver($otherDriver);
        $stop = new RouteStop($route, 1, 'Calle Mayor 1');
        $this->initPublicId($stop);

        $this->stopRepo->method('findOneByPublicId')->willReturn($stop);

        self::expectException(DriverNotOwnerException::class);

        $input = DeliverStopInput::fromArray([
            'client_action_id' => Uuid::v4()->toRfc4122(),
            'signed_by_name' => 'Test',
            'recipient_id_encoded' => 'encoded-id',
            'confirmed_by_driver' => true,
        ]);

        $this->service->deliverStop($stop->getPublicIdString(), $input, $driver);
    }

    #[Test]
    public function deliverStopThrowsWhenStopNotFound(): void
    {
        $driver = $this->createDriver('1');
        $this->stopRepo->method('findOneByPublicId')->willReturn(null);

        self::expectException(StopNotFoundException::class);

        $input = DeliverStopInput::fromArray([
            'client_action_id' => Uuid::v4()->toRfc4122(),
            'signed_by_name' => 'Test',
            'recipient_id_encoded' => 'encoded-id',
            'confirmed_by_driver' => true,
        ]);

        $this->service->deliverStop('01NONEXISTENT00000000000000', $input, $driver);
    }

    #[Test]
    public function deliverStopThrowsWhenDriverNotConfirmed(): void
    {
        $driver = $this->createDriver('1');
        $route = new Route('Test Route');
        $route->setDriver($driver);
        $stop = new RouteStop($route, 1, 'Calle Mayor 1');
        $this->setEntityId($stop, '10');
        $this->initPublicId($stop);

        $this->stopRepo->method('findOneByPublicId')->willReturn($stop);
        $this->driverActionService->method('register')->willReturn(true);

        self::expectException(DriverConfirmationRequiredException::class);

        $input = DeliverStopInput::fromArray([
            'client_action_id' => Uuid::v4()->toRfc4122(),
            'signed_by_name' => 'Test',
            'recipient_id_encoded' => 'encoded-id',
            'confirmed_by_driver' => false,
        ]);

        $this->service->deliverStop($stop->getPublicIdString(), $input, $driver);
    }

    #[Test]
    public function reportExceptionCreatesShipmentEventAndDispatches(): void
    {
        $driver = $this->createDriver('1');
        $route = new Route('Test Route');
        $route->setDriver($driver);
        $this->initPublicId($route);
        $stop = new RouteStop($route, 1, 'Calle Mayor 1');
        $this->setEntityId($stop, '10');
        $this->initPublicId($stop);

        $stopPublicId = $stop->getPublicIdString();
        $this->stopRepo->method('findOneByPublicId')->willReturn($stop);
        $this->driverActionService->method('register')->willReturn(true);

        $shipment = $this->createMock(Shipment::class);
        $this->shipmentRepo->method('findOneByPublicId')->willReturn($shipment);

        $this->em->expects(self::atLeastOnce())->method('persist');
        $this->em->expects(self::once())->method('flush');
        // Use empty comment to skip NlpClassificationMessage dispatch (avoids getId() null issue)
        $this->eventDispatcher->expects(self::once())->method('dispatch');

        $input = ExceptionStopInput::fromArray([
            'client_action_id' => Uuid::v4()->toRfc4122(),
            'reason' => 'ABSENT',
            'comment' => '',
            'shipment_public_id' => (new Ulid())->toBase32(),
        ]);

        $result = $this->service->reportException($stopPublicId, $input, $driver);

        self::assertFalse($result->idempotent);
    }

    #[Test]
    public function reportExceptionIdempotentWhenDuplicate(): void
    {
        $driver = $this->createDriver('1');
        $route = new Route('Test Route');
        $route->setDriver($driver);
        $stop = new RouteStop($route, 1, 'Calle Mayor 1');
        $this->initPublicId($stop);

        $this->stopRepo->method('findOneByPublicId')->willReturn($stop);
        $this->driverActionService->method('register')->willReturn(false);

        $this->em->expects(self::never())->method('flush');

        $input = ExceptionStopInput::fromArray([
            'client_action_id' => Uuid::v4()->toRfc4122(),
            'reason' => 'ABSENT',
            'comment' => '',
        ]);

        $result = $this->service->reportException($stop->getPublicIdString(), $input, $driver);

        self::assertTrue($result->idempotent);
    }

    private function createDriver(string $id): User&MockObject
    {
        $driver = $this->createMock(User::class);
        $driver->method('getId')->willReturn($id);
        $driver->method('getPublicIdString')->willReturn((new Ulid())->toBase32());

        return $driver;
    }

    private function setEntityId(object $entity, string $id): void
    {
        $ref = new \ReflectionClass($entity);
        $prop = $ref->getProperty('id');
        $prop->setValue($entity, $id);
    }

    private function initPublicId(object $entity): void
    {
        $ref = new \ReflectionClass($entity);
        $prop = $ref->getProperty('publicId');
        $prop->setValue($entity, new Ulid());
    }
}
