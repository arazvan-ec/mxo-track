<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\PublicIdTrait;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'delivery_rating')]
#[ORM\UniqueConstraint(name: 'uniq_delivery_rating_public_id', columns: ['public_id'])]
#[ORM\UniqueConstraint(name: 'uniq_delivery_rating_shipment', columns: ['shipment_id'])]
#[ORM\HasLifecycleCallbacks]
class DeliveryRating
{
    use PublicIdTrait;

    #[ORM\OneToOne(targetEntity: Shipment::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Shipment $shipment;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $score;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $tags = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $recipientPhone = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    public function __construct(Shipment $shipment, int $score)
    {
        if ($score < 1 || $score > 5) {
            throw new \InvalidArgumentException('Score must be between 1 and 5');
        }

        $this->shipment = $shipment;
        $this->score = $score;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getShipment(): Shipment { return $this->shipment; }
    public function getScore(): int { return $this->score; }
    public function getComment(): ?string { return $this->comment; }
    public function getTags(): ?array { return $this->tags; }
    public function getRecipientPhone(): ?string { return $this->recipientPhone; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }

    public function setComment(?string $comment): void { $this->comment = $comment; }

    /**
     * @param string[]|null $tags
     */
    public function setTags(?array $tags): void { $this->tags = $tags; }

    public function setRecipientPhone(?string $recipientPhone): void { $this->recipientPhone = $recipientPhone; }
}
