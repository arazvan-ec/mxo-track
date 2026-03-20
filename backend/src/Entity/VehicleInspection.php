<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Route\Model\Route;
use App\Entity\Concerns\PublicIdTrait;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_vehicle_inspection_public_id', columns: ['public_id'])]
#[ORM\HasLifecycleCallbacks]
class VehicleInspection
{
    use PublicIdTrait;

    #[ORM\ManyToOne(targetEntity: Route::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Route $route;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $driver;

    /** @var array<int, array{name: string, checked: bool, note?: string}> */
    #[ORM\Column(type: 'json')]
    private array $items;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $completedAt = null;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    /**
     * @param array<int, array{name: string, checked: bool, note?: string}> $items
     */
    public function __construct(Route $route, User $driver, array $items)
    {
        $this->route = $route;
        $this->driver = $driver;
        $this->items = $items;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getRoute(): Route
    {
        return $this->route;
    }

    public function getDriver(): User
    {
        return $this->driver;
    }

    /** @return array<int, array{name: string, checked: bool, note?: string}> */
    public function getItems(): array
    {
        return $this->items;
    }

    /** @param array<int, array{name: string, checked: bool, note?: string}> $items */
    public function setItems(array $items): void
    {
        $this->items = $items;
    }

    public function getCompletedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?DateTimeImmutable $completedAt): void
    {
        $this->completedAt = $completedAt;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function allItemsChecked(): bool
    {
        foreach ($this->items as $item) {
            if (!($item['checked'] ?? false)) {
                return false;
            }
        }

        return true;
    }
}
