<?php

declare(strict_types=1);

namespace App\Application\Delivery;

use App\Domain\Event\StopDelivered;
use App\Domain\Event\StopExceptionReported;
use App\Dto\Driver\DeliverStopInput;
use App\Dto\Driver\ExceptionStopInput;
use App\Entity\Pod;
use App\Entity\RouteStop;
use App\Entity\Shipment;
use App\Entity\ShipmentEvent;
use App\Entity\User;
use App\Enum\ExceptionCode;
use App\Enum\ShipmentEventType;
use App\Repository\RouteStopRepository;
use App\Repository\ShipmentRepository;
use App\Message\NlpClassificationMessage;
use App\Service\AuditLogger;
use App\Service\DeliveryEvidenceFactory;
use App\Service\DriverActionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class DeliveryService
{
    public function __construct(
        private EntityManagerInterface $em,
        private DriverActionService $driverActionService,
        private DeliveryEvidenceFactory $evidenceFactory,
        private AuditLogger $auditLogger,
        private EventDispatcherInterface $eventDispatcher,
        private MessageBusInterface $messageBus,
        private RouteStopRepository $stopRepo,
        private ShipmentRepository $shipmentRepo,
    ) {}

    /**
     * @throws StopNotFoundException
     * @throws DriverNotOwnerException
     * @throws DriverConfirmationRequiredException
     */
    public function deliverStop(
        string $stopPublicId,
        DeliverStopInput $input,
        User $driver,
        DeliveryContext $context = new DeliveryContext(),
    ): DeliveryResult {
        $stop = $this->resolveStopForDriver($stopPublicId, $driver);

        $created = $this->driverActionService->register($driver, $input->clientActionId, 'DELIVER', $stop);
        if (!$created) {
            return new DeliveryResult(idempotent: true);
        }

        if (!$input->confirmedByDriver) {
            throw new DriverConfirmationRequiredException();
        }

        $stop->markDelivered();

        $pod = new Pod($stop, $driver, $input->signedByName, $input->recipientIdEncoded);
        $this->em->persist($pod);

        if ($input->shipmentPublicId !== null) {
            $shipment = $this->shipmentRepo->findOneByPublicId($input->shipmentPublicId);
            if ($shipment instanceof Shipment) {
                $this->em->persist(new ShipmentEvent($shipment, ShipmentEventType::DELIVERED, [
                    'stop_public_id' => $stopPublicId,
                    'confirmation_mode' => 'recipient_id_encoded',
                ]));
            }
        }

        $this->auditLogger->log($driver, 'DRIVER_DELIVER', 'route_stop', (string) $stop->getId(), [
            'client_action_id' => $input->clientActionId,
            'shipment_public_id' => $input->shipmentPublicId ?? '',
            'delivery_evidence' => $this->evidenceFactory->build(
                $input->recipientIdEncoded,
                $input->confirmedByDriver,
                $stopPublicId,
                $input->clientActionId,
                $driver->getPublicIdString(),
                $context->clientIp,
                $context->userAgent,
            ),
        ]);

        $this->em->flush();

        $this->eventDispatcher->dispatch(new StopDelivered(
            stopPublicId: $stopPublicId,
            shipmentPublicId: $input->shipmentPublicId ?? '',
            routePublicId: $stop->getRoute()->getPublicIdString(),
            driverUserId: (int) $driver->getId(),
            podPublicId: $pod->getPublicIdString(),
        ));

        return new DeliveryResult(idempotent: false, podPublicId: $pod->getPublicIdString());
    }

    /**
     * @throws StopNotFoundException
     * @throws DriverNotOwnerException
     */
    public function reportException(
        string $stopPublicId,
        ExceptionStopInput $input,
        User $driver,
    ): ExceptionResult {
        $stop = $this->resolveStopForDriver($stopPublicId, $driver);

        $created = $this->driverActionService->register($driver, $input->clientActionId, 'EXCEPTION', $stop);
        if (!$created) {
            return new ExceptionResult(idempotent: true);
        }

        $reason = ExceptionCode::tryFrom($input->reason) ?? ExceptionCode::OTHER;
        $stop->markException($reason, $input->comment);

        $shipmentEvent = null;
        if ($input->shipmentPublicId !== null) {
            $shipment = $this->shipmentRepo->findOneByPublicId($input->shipmentPublicId);
            if ($shipment instanceof Shipment) {
                $shipmentEvent = new ShipmentEvent($shipment, ShipmentEventType::EXCEPTION, [
                    'stop_public_id' => $stopPublicId,
                    'reason' => $reason->value,
                    'comment' => $input->comment,
                ]);
                $this->em->persist($shipmentEvent);
            }
        }

        $this->auditLogger->log($driver, 'DRIVER_EXCEPTION', 'route_stop', (string) $stop->getId(), [
            'client_action_id' => $input->clientActionId,
            'shipment_public_id' => $input->shipmentPublicId ?? '',
            'reason' => $reason->value,
            'comment' => $input->comment,
        ]);

        $this->em->flush();

        if ($shipmentEvent !== null && $input->comment !== '') {
            $this->messageBus->dispatch(new NlpClassificationMessage(
                shipmentEventId: $shipmentEvent->getId(),
                exceptionNotes: $input->comment,
                exceptionCode: $reason->value,
            ));
        }

        $this->eventDispatcher->dispatch(new StopExceptionReported(
            stopPublicId: $stopPublicId,
            shipmentPublicId: $input->shipmentPublicId ?? '',
            routePublicId: $stop->getRoute()->getPublicIdString(),
            driverUserId: (int) $driver->getId(),
            reason: $reason,
            notes: $input->comment !== '' ? $input->comment : null,
        ));

        return new ExceptionResult(idempotent: false);
    }

    /**
     * @throws StopNotFoundException
     * @throws DriverNotOwnerException
     */
    private function resolveStopForDriver(string $stopPublicId, User $driver): RouteStop
    {
        $stop = $this->stopRepo->findOneByPublicId($stopPublicId);
        if (!$stop instanceof RouteStop) {
            throw new StopNotFoundException($stopPublicId);
        }

        if ($stop->getRoute()->getDriver()?->getId() !== $driver->getId()) {
            throw new DriverNotOwnerException();
        }

        return $stop;
    }
}
