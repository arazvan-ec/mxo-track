<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\PublicIdTrait;
use App\Repository\UserPreferenceRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserPreferenceRepository::class)]
#[ORM\Table(name: 'user_preference')]
#[ORM\UniqueConstraint(name: 'uniq_user_preference_public_id', columns: ['public_id'])]
#[ORM\UniqueConstraint(name: 'uniq_user_preference_user', columns: ['user_id'])]
#[ORM\HasLifecycleCallbacks]
class UserPreference
{
    use PublicIdTrait;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 16, options: ['default' => 'expanded'])]
    private string $widgetDefaultMode = 'expanded';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public function __construct(User $user, string $widgetDefaultMode = 'expanded')
    {
        $this->user = $user;
        $this->widgetDefaultMode = $widgetDefaultMode;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getWidgetDefaultMode(): string
    {
        return $this->widgetDefaultMode;
    }

    public function setWidgetDefaultMode(string $widgetDefaultMode): void
    {
        $this->widgetDefaultMode = $widgetDefaultMode;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
