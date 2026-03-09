<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\PublicIdTrait;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'vehicle_inspection')]
#[ORM\UniqueConstraint(name: 'uniq_vehicle_inspection_public_id', columns: ['public_id'])]
#[ORM\UniqueConstraint(name: 'uniq_vehicle_inspection_route', columns: ['route_id'])]
#[ORM\HasLifecycleCallbacks]
class VehicleInspection
{
    use PublicIdTrait;

    #[ORM\ManyToOne(targetEntity: Route::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Route $route;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $driver;

    /** @var array<array{name: string, checked: bool, note?: string}> */
    #[ORM\Column(type: Types::JSON)]
    private array $items = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    public function __construct(Route $route, User $driver)
    {
        $this->route = $route;
        $this->driver = $driver;
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

    /** @return array<array{name: string, checked: bool, note?: string}> */
    public function getItems(): array
    {
        return $this->items;
    }

    /** @param array<array{name: string, checked: bool, note?: string}> $items */
    public function setItems(array $items): void
    {
        $this->items = $items;

        $allChecked = \count($items) > 0 && array_reduce(
            $items,
            static fn(bool $carry, array $item) => $carry && ($item['checked'] ?? false),
            true,
        );

        $this->completedAt = $allChecked ? new DateTimeImmutable() : null;
    }

    public function getCompletedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function isCompleted(): bool
    {
        return $this->completedAt !== null;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return array<string>
     */
    public static function defaultChecklistItems(): array
    {
        return [
            'Neumaticos en buen estado',
            'Luces funcionan correctamente',
            'Carga asegurada',
            'Documentacion del vehiculo',
            'Nivel de combustible/carga',
        ];
    }
}
