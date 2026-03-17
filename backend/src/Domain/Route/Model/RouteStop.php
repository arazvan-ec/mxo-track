<?php

declare(strict_types=1);

namespace App\Domain\Route\Model;

use App\Domain\Route\ValueObject\Coordinate;
use App\Domain\Route\ValueObject\RouteId;
use App\Domain\Route\ValueObject\StopId;
use App\Domain\Route\ValueObject\TimeWindow;
use App\Enum\ExceptionCode;
use App\Enum\RouteStopStatus;

final class RouteStop
{
    private RouteStopStatus $status = RouteStopStatus::PENDING;
    private ?Coordinate $coordinate = null;
    private ?string $recipientName = null;
    private ?string $recipientPhone = null;
    private ?string $notes = null;
    private ?string $aiNotes = null;
    private bool $isOrigin = false;
    private ?\DateTimeImmutable $deliveredAt = null;
    private ?ExceptionCode $exceptionCode = null;
    private ?string $exceptionNotes = null;
    private ?TimeWindow $deliveryWindow = null;
    private ?string $shipmentPublicId = null;

    public function __construct(
        private readonly StopId $id,
        private readonly RouteId $routeId,
        private int $sequence,
        private string $address,
    ) {
    }

    public static function reconstitute(
        StopId $id,
        RouteId $routeId,
        int $sequence,
        string $address,
        RouteStopStatus $status,
        ?Coordinate $coordinate,
        ?string $recipientName,
        ?string $recipientPhone,
        ?string $notes,
        ?string $aiNotes,
        bool $isOrigin,
        ?\DateTimeImmutable $deliveredAt,
        ?ExceptionCode $exceptionCode,
        ?string $exceptionNotes,
        ?TimeWindow $deliveryWindow,
        ?string $shipmentPublicId,
    ): self {
        $stop = new self($id, $routeId, $sequence, $address);
        $stop->status = $status;
        $stop->coordinate = $coordinate;
        $stop->recipientName = $recipientName;
        $stop->recipientPhone = $recipientPhone;
        $stop->notes = $notes;
        $stop->aiNotes = $aiNotes;
        $stop->isOrigin = $isOrigin;
        $stop->deliveredAt = $deliveredAt;
        $stop->exceptionCode = $exceptionCode;
        $stop->exceptionNotes = $exceptionNotes;
        $stop->deliveryWindow = $deliveryWindow;
        $stop->shipmentPublicId = $shipmentPublicId;

        return $stop;
    }

    public function id(): StopId
    {
        return $this->id;
    }

    public function routeId(): RouteId
    {
        return $this->routeId;
    }

    public function sequence(): int
    {
        return $this->sequence;
    }

    public function setSequence(int $sequence): void
    {
        $this->sequence = $sequence;
    }

    public function address(): string
    {
        return $this->address;
    }

    public function setAddress(string $address): void
    {
        $this->address = $address;
    }

    public function status(): RouteStopStatus
    {
        return $this->status;
    }

    public function coordinate(): ?Coordinate
    {
        return $this->coordinate;
    }

    public function setCoordinate(?Coordinate $coordinate): void
    {
        $this->coordinate = $coordinate;
    }

    public function recipientName(): ?string
    {
        return $this->recipientName;
    }

    public function setRecipientName(?string $recipientName): void
    {
        $this->recipientName = $recipientName;
    }

    public function recipientPhone(): ?string
    {
        return $this->recipientPhone;
    }

    public function setRecipientPhone(?string $recipientPhone): void
    {
        $this->recipientPhone = $recipientPhone;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
    }

    public function aiNotes(): ?string
    {
        return $this->aiNotes;
    }

    public function setAiNotes(?string $aiNotes): void
    {
        $this->aiNotes = $aiNotes;
    }

    public function isOrigin(): bool
    {
        return $this->isOrigin;
    }

    public function setOrigin(bool $isOrigin): void
    {
        $this->isOrigin = $isOrigin;
    }

    public function deliveredAt(): ?\DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    public function exceptionCode(): ?ExceptionCode
    {
        return $this->exceptionCode;
    }

    public function exceptionNotes(): ?string
    {
        return $this->exceptionNotes;
    }

    public function deliveryWindow(): ?TimeWindow
    {
        return $this->deliveryWindow;
    }

    public function setDeliveryWindow(?TimeWindow $deliveryWindow): void
    {
        $this->deliveryWindow = $deliveryWindow;
    }

    public function shipmentPublicId(): ?string
    {
        return $this->shipmentPublicId;
    }

    public function setShipmentPublicId(?string $shipmentPublicId): void
    {
        $this->shipmentPublicId = $shipmentPublicId;
    }

    public function markDelivered(): void
    {
        if ($this->status !== RouteStopStatus::DELIVERED) {
            $this->status = RouteStopStatus::DELIVERED;
            $this->deliveredAt = new \DateTimeImmutable();
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
}
