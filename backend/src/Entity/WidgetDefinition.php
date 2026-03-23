<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\PublicIdTrait;
use App\Enum\WidgetType;
use App\Repository\WidgetDefinitionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WidgetDefinitionRepository::class)]
#[ORM\Table(name: 'widget_definition')]
#[ORM\UniqueConstraint(name: 'uniq_widget_definition_public_id', columns: ['public_id'])]
#[ORM\UniqueConstraint(name: 'uniq_widget_definition_type', columns: ['type'])]
#[ORM\HasLifecycleCallbacks]
class WidgetDefinition
{
    use PublicIdTrait;

    #[ORM\Column(length: 50, enumType: WidgetType::class)]
    private WidgetType $type;

    #[ORM\Column(length: 120)]
    private string $label;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $previewImage = null;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(WidgetType $type, string $label)
    {
        $now = new \DateTimeImmutable();
        $this->type = $type;
        $this->label = $label;
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getType(): WidgetType { return $this->type; }

    public function getLabel(): string { return $this->label; }
    public function setLabel(string $label): void { $this->label = $label; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): void { $this->description = $description; }

    public function getPreviewImage(): ?string { return $this->previewImage; }
    public function setPreviewImage(?string $previewImage): void { $this->previewImage = $previewImage; }

    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): void { $this->active = $active; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    #[ORM\PrePersist]
    public function touchCreatedAt(): void
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function touchUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
