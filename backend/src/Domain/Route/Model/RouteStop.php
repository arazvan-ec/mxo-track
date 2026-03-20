<?php

declare(strict_types=1);

namespace App\Domain\Route\Model;

use App\Domain\Shipment\Model\Shipment;
use App\Enum\ExceptionCode;
use App\Enum\RouteStopStatus;
use DateTimeImmutable;
use Symfony\Component\Uid\Ulid;

/**
 * RouteStop entity — domain POPO.
 * Persistence handled via external XML mapping (no ORM attributes).
 */
class RouteStop
{
    private ?string $id = null;
    private Ulid $publicId;
    private Route $route;
    private int $sequence;
    private string $address;
    private ?float $latitude = null;
    private ?float $longitude = null;
    private ?string $recipientName = null;
    private ?string $recipientPhone = null;
    private ?string $notes = null;
    private RouteStopStatus $status = RouteStopStatus::PENDING;
    private ?DateTimeImmutable $deliveredAt = null;
    private ?ExceptionCode $exceptionCode = null;
    private ?string $exceptionNotes = null;
    private ?string $aiNotes = null;
    private bool $isOrigin = false;
    private ?DateTimeImmutable $deliveryWindowStart = null;
    private ?DateTimeImmutable $deliveryWindowEnd = null;
    private ?Shipment $shipment = null;

    public function __construct(Route $route, int $sequence, string $address)
    {
        $this->route = $route;
        $this->sequence = $sequence;
        $this->address = $address;
        $this->publicId = new Ulid();
    }

    // ── Identity ──

    public function getId(): ?string { return $this->id; }
    public function getPublicId(): Ulid { return $this->publicId; }
    public function getPublicIdString(): string { return (string) $this->publicId; }

    public function initializePublicId(): void
    {
        $this->publicId ??= new Ulid();
    }

    // ── Accessors ──

    public function getRoute(): Route { return $this->route; }
    public function getSequence(): int { return $this->sequence; }
    public function setSequence(int $sequence): void { $this->sequence = $sequence; }
    public function getAddress(): string { return $this->address; }
    public function setAddress(string $address): void { $this->address = $address; }
    public function getLatitude(): ?float { return $this->latitude; }
    public function setLatitude(?float $latitude): void { $this->latitude = $latitude; }
    public function getLongitude(): ?float { return $this->longitude; }
    public function setLongitude(?float $longitude): void { $this->longitude = $longitude; }
    public function getRecipientName(): ?string { return $this->recipientName; }
    public function setRecipientName(?string $recipientName): void { $this->recipientName = $recipientName; }
    public function getRecipientPhone(): ?string { return $this->recipientPhone; }
    public function setRecipientPhone(?string $recipientPhone): void { $this->recipientPhone = $recipientPhone; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): void { $this->notes = $notes; }
    public function getStatus(): RouteStopStatus { return $this->status; }
    public function getDeliveredAt(): ?DateTimeImmutable { return $this->deliveredAt; }
    public function getExceptionCode(): ?ExceptionCode { return $this->exceptionCode; }
    public function getExceptionNotes(): ?string { return $this->exceptionNotes; }
    public function getShipment(): ?Shipment { return $this->shipment; }
    public function setShipment(?Shipment $shipment): void { $this->shipment = $shipment; }
    public function isOrigin(): bool { return $this->isOrigin; }
    public function setOrigin(bool $isOrigin): void { $this->isOrigin = $isOrigin; }
    public function getDeliveryWindowStart(): ?DateTimeImmutable { return $this->deliveryWindowStart; }
    public function setDeliveryWindowStart(?DateTimeImmutable $deliveryWindowStart): void { $this->deliveryWindowStart = $deliveryWindowStart; }
    public function getDeliveryWindowEnd(): ?DateTimeImmutable { return $this->deliveryWindowEnd; }
    public function setDeliveryWindowEnd(?DateTimeImmutable $deliveryWindowEnd): void { $this->deliveryWindowEnd = $deliveryWindowEnd; }
    public function getAiNotes(): ?string { return $this->aiNotes; }
    public function setAiNotes(?string $aiNotes): void { $this->aiNotes = $aiNotes; }

    // ── Domain Logic ──

    public function markDelivered(): void
    {
        if ($this->status !== RouteStopStatus::DELIVERED) {
            $this->status = RouteStopStatus::DELIVERED;
            $this->deliveredAt = new DateTimeImmutable();
            $this->exceptionCode = null;
            $this->exceptionNotes = null;
        }
    }

    public function markException(ExceptionCode $code, string $notes): void
    {
        $this->status = RouteStopStatus::EXCEPTION;
        $this->exceptionCode = $code;
        $this->exceptionNotes = $notes;
    }

    public function markSkipped(?string $reason = null): void
    {
        $this->status = RouteStopStatus::SKIPPED;
        $this->exceptionNotes = $reason;
    }
}
