<?php

declare(strict_types=1);

namespace App\Infrastructure\Route\Doctrine\Entity;

use App\Domain\Route\Model\RouteStop;
use App\Domain\Route\ValueObject\Coordinate;
use App\Domain\Route\ValueObject\RouteId;
use App\Domain\Route\ValueObject\StopId;
use App\Domain\Route\ValueObject\TimeWindow;
use App\Entity\Shipment;
use App\Enum\ExceptionCode;
use App\Enum\RouteStopStatus;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
#[ORM\Table(name: 'route_stop')]
#[ORM\UniqueConstraint(name: 'uniq_route_stop_public_id', columns: ['public_id'])]
#[ORM\HasLifecycleCallbacks]
class RouteStopEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?string $id = null;

    #[ORM\Column(type: 'ulid', unique: true)]
    private Ulid $publicId;

    #[ORM\ManyToOne(targetEntity: RouteEntity::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private RouteEntity $route;

    #[ORM\Column]
    private int $sequence;

    #[ORM\Column(length: 255)]
    private string $address;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $latitude = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $longitude = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $recipientName = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $recipientPhone = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(length: 20, enumType: RouteStopStatus::class)]
    private RouteStopStatus $status = RouteStopStatus::PENDING;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $deliveredAt = null;

    #[ORM\Column(length: 30, enumType: ExceptionCode::class, nullable: true)]
    private ?ExceptionCode $exceptionCode = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $exceptionNotes = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $aiNotes = null;

    #[ORM\Column]
    private bool $isOrigin = false;

    #[ORM\Column(type: 'time_immutable', nullable: true)]
    private ?DateTimeImmutable $deliveryWindowStart = null;

    #[ORM\Column(type: 'time_immutable', nullable: true)]
    private ?DateTimeImmutable $deliveryWindowEnd = null;

    #[ORM\ManyToOne(targetEntity: Shipment::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Shipment $shipment = null;

    private function __construct()
    {
    }

    #[ORM\PrePersist]
    public function initializePublicId(): void
    {
        $this->publicId ??= new Ulid();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getPublicId(): Ulid
    {
        return $this->publicId;
    }

    public function getRoute(): RouteEntity
    {
        return $this->route;
    }

    // ── Domain ↔ Doctrine Mapping ──

    public function toDomain(): RouteStop
    {
        $coordinate = ($this->latitude !== null && $this->longitude !== null)
            ? new Coordinate($this->latitude, $this->longitude)
            : null;

        $deliveryWindow = ($this->deliveryWindowStart !== null && $this->deliveryWindowEnd !== null)
            ? new TimeWindow($this->deliveryWindowStart, $this->deliveryWindowEnd)
            : null;

        return RouteStop::reconstitute(
            id: new StopId((string) $this->publicId),
            routeId: new RouteId((string) $this->route->getPublicId()),
            sequence: $this->sequence,
            address: $this->address,
            status: $this->status,
            coordinate: $coordinate,
            recipientName: $this->recipientName,
            recipientPhone: $this->recipientPhone,
            notes: $this->notes,
            aiNotes: $this->aiNotes,
            isOrigin: $this->isOrigin,
            deliveredAt: $this->deliveredAt,
            exceptionCode: $this->exceptionCode,
            exceptionNotes: $this->exceptionNotes,
            deliveryWindow: $deliveryWindow,
            shipmentPublicId: $this->shipment?->getPublicIdString(),
        );
    }

    public static function fromDomain(RouteStop $stop, RouteEntity $routeEntity): self
    {
        $entity = new self();
        $entity->publicId = Ulid::fromString((string) $stop->id());
        $entity->route = $routeEntity;
        $entity->updateFromDomain($stop);

        return $entity;
    }

    public function updateFromDomain(RouteStop $stop): void
    {
        $this->sequence = $stop->sequence();
        $this->address = $stop->address();
        $this->status = $stop->status();
        $this->recipientName = $stop->recipientName();
        $this->recipientPhone = $stop->recipientPhone();
        $this->notes = $stop->notes();
        $this->aiNotes = $stop->aiNotes();
        $this->isOrigin = $stop->isOrigin();
        $this->deliveredAt = $stop->deliveredAt();
        $this->exceptionCode = $stop->exceptionCode();
        $this->exceptionNotes = $stop->exceptionNotes();

        $coord = $stop->coordinate();
        $this->latitude = $coord?->latitude;
        $this->longitude = $coord?->longitude;

        $window = $stop->deliveryWindow();
        $this->deliveryWindowStart = $window?->start;
        $this->deliveryWindowEnd = $window?->end;

        // Note: shipment relation resolved by repository using shipmentPublicId
    }

    public function setShipment(?Shipment $shipment): void
    {
        $this->shipment = $shipment;
    }
}
