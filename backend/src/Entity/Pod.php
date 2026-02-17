<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
class Pod
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\OneToOne(targetEntity: RouteStop::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private RouteStop $routeStop;

    #[ORM\ManyToOne(targetEntity: Shipment::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Shipment $shipment = null;

    #[ORM\Column(length: 120)]
    private string $signedByName;

    #[ORM\Column(type: 'text', name: 'recipient_id_encoded')]
    private string $recipientIdEncoded;

    #[ORM\Column(type: 'boolean')]
    private bool $confirmedByDriver = true;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_user_id', nullable: false)]
    private User $createdByUser;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct(RouteStop $routeStop, User $driver, string $signedByName, string $recipientIdEncoded)
    {
        $this->id = Uuid::v7();
        $this->routeStop = $routeStop;
        $this->createdByUser = $driver;
        $this->signedByName = $signedByName;
        $this->recipientIdEncoded = $recipientIdEncoded;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getRecipientIdEncoded(): string
    {
        return $this->recipientIdEncoded;
    }

    public function isConfirmedByDriver(): bool
    {
        return $this->confirmedByDriver;
    }
}
