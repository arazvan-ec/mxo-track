<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Model;

use App\Domain\Route\Model\RouteStop;
use App\Entity\User;
use DateTimeImmutable;
use Symfony\Component\Uid\Ulid;

/**
 * Proof of Delivery entity — domain POPO.
 * Persistence handled via external XML mapping (no ORM attributes).
 */
class Pod
{
    private ?string $id = null;
    private Ulid $publicId;
    private RouteStop $routeStop;
    private ?Shipment $shipment = null;
    private string $signedByName;
    private string $recipientIdEncoded;
    private bool $confirmedByDriver = true;
    private User $createdByUser;
    private DateTimeImmutable $createdAt;

    public function __construct(RouteStop $routeStop, User $driver, string $signedByName, string $recipientIdEncoded)
    {
        $this->routeStop = $routeStop;
        $this->createdByUser = $driver;
        $this->signedByName = $signedByName;
        $this->recipientIdEncoded = $recipientIdEncoded;
        $this->createdAt = new DateTimeImmutable();
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

    public function getRouteStop(): RouteStop { return $this->routeStop; }
    public function getShipment(): ?Shipment { return $this->shipment; }
    public function setShipment(?Shipment $shipment): void { $this->shipment = $shipment; }
    public function getSignedByName(): string { return $this->signedByName; }
    public function getRecipientIdEncoded(): string { return $this->recipientIdEncoded; }
    public function isConfirmedByDriver(): bool { return $this->confirmedByDriver; }
    public function getCreatedByUser(): User { return $this->createdByUser; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
}
