<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\PublicIdTrait;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'driver_action', uniqueConstraints: [new ORM\UniqueConstraint(name: 'uniq_driver_action', columns: ['driver_user_id', 'client_action_id']), new ORM\UniqueConstraint(name: 'uniq_driver_action_public_id', columns: ['public_id'])])]
#[ORM\HasLifecycleCallbacks]
class DriverAction
{
    use PublicIdTrait;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'driver_user_id', nullable: false)]
    private User $driver;

    #[ORM\Column(type: 'uuid', name: 'client_action_id')]
    private Uuid $clientActionId;

    #[ORM\Column(length: 30)]
    private string $type;

    #[ORM\ManyToOne(targetEntity: RouteStop::class)]
    #[ORM\JoinColumn(name: 'stop_id', nullable: false)]
    private RouteStop $stop;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct(User $driver, Uuid $clientActionId, string $type, RouteStop $stop)
    {
        $this->driver = $driver;
        $this->clientActionId = $clientActionId;
        $this->type = $type;
        $this->stop = $stop;
        $this->createdAt = new DateTimeImmutable();
    }
}
