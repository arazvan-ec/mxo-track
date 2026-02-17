<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ExceptionCode;
use App\Enum\RouteStopStatus;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
class RouteStop
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Route::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Route $route;

    #[ORM\Column] private int $sequence;
    #[ORM\Column(length: 255)] private string $address;
    #[ORM\Column(length: 20, enumType: RouteStopStatus::class)] private RouteStopStatus $status = RouteStopStatus::PENDING;
    #[ORM\Column(nullable: true)] private ?DateTimeImmutable $deliveredAt = null;
    #[ORM\Column(length: 30, enumType: ExceptionCode::class, nullable: true)] private ?ExceptionCode $exceptionCode = null;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $exceptionNotes = null;

    public function __construct(Route $route, int $sequence, string $address)
    {
        $this->id = Uuid::v7();
        $this->route = $route;
        $this->sequence = $sequence;
        $this->address = $address;
    }

    public function getId(): Uuid { return $this->id; }
    public function getRoute(): Route { return $this->route; }
    public function getStatus(): RouteStopStatus { return $this->status; }

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
