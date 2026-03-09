<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\PublicIdTrait;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'push_subscription')]
#[ORM\UniqueConstraint(name: 'uniq_push_subscription_public_id', columns: ['public_id'])]
#[ORM\UniqueConstraint(name: 'uniq_push_subscription_user_endpoint', columns: ['user_id', 'endpoint'])]
#[ORM\Index(name: 'idx_push_subscription_user', columns: ['user_id'])]
#[ORM\HasLifecycleCallbacks]
class PushSubscription
{
    use PublicIdTrait;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 500)]
    private string $endpoint;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $authKey = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $p256dhKey = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    public function __construct(User $user, string $endpoint, ?string $authKey = null, ?string $p256dhKey = null)
    {
        $this->user = $user;
        $this->endpoint = $endpoint;
        $this->authKey = $authKey;
        $this->p256dhKey = $p256dhKey;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getUser(): User { return $this->user; }
    public function getEndpoint(): string { return $this->endpoint; }
    public function getAuthKey(): ?string { return $this->authKey; }
    public function getP256dhKey(): ?string { return $this->p256dhKey; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
}
