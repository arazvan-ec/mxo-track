<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\PublicIdTrait;
use App\Enum\RecipientActionType;
use App\Repository\RecipientActionRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RecipientActionRepository::class)]
#[ORM\Table(name: 'recipient_action')]
#[ORM\UniqueConstraint(name: 'uniq_recipient_action_public_id', columns: ['public_id'])]
#[ORM\Index(name: 'idx_recipient_action_shipment', columns: ['shipment_id'])]
#[ORM\HasLifecycleCallbacks]
class RecipientAction
{
    use PublicIdTrait;

    #[ORM\ManyToOne(targetEntity: Shipment::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Shipment $shipment;

    #[ORM\Column(length: 30, enumType: RecipientActionType::class)]
    private RecipientActionType $actionType;

    #[ORM\Column(type: Types::JSON)]
    private array $payload;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    public function __construct(
        Shipment $shipment,
        RecipientActionType $actionType,
        array $payload = [],
    ) {
        $this->shipment = $shipment;
        $this->actionType = $actionType;
        $this->payload = $payload;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getShipment(): Shipment { return $this->shipment; }
    public function getActionType(): RecipientActionType { return $this->actionType; }
    public function getPayload(): array { return $this->payload; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
}
