<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\PublicIdTrait;
use App\Enum\ExceptionCode;
use App\Enum\RouteStopStatus;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\RouteStopRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_route_stop_public_id', columns: ['public_id'])]
#[ORM\HasLifecycleCallbacks]
class RouteStop
{
    use PublicIdTrait;

    #[ORM\ManyToOne(targetEntity: Route::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Route $route;

    #[ORM\Column] private int $sequence;
    #[ORM\Column(length: 255)] private string $address;
    #[ORM\Column(type: 'float', nullable: true)] private ?float $latitude = null;
    #[ORM\Column(type: 'float', nullable: true)] private ?float $longitude = null;
    #[ORM\Column(length: 150, nullable: true)] private ?string $recipientName = null;
    #[ORM\Column(length: 30, nullable: true)] private ?string $recipientPhone = null;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $notes = null;
    #[ORM\Column(length: 20, enumType: RouteStopStatus::class)] private RouteStopStatus $status = RouteStopStatus::PENDING;
    #[ORM\Column(nullable: true)] private ?DateTimeImmutable $deliveredAt = null;
    #[ORM\Column(length: 30, enumType: ExceptionCode::class, nullable: true)] private ?ExceptionCode $exceptionCode = null;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $exceptionNotes = null;
    #[ORM\Column] private bool $isOrigin = false;

    #[ORM\ManyToOne(targetEntity: Shipment::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Shipment $shipment = null;

    public function __construct(Route $route, int $sequence, string $address)
    {
        $this->route = $route;
        $this->sequence = $sequence;
        $this->address = $address;
    }

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
}
