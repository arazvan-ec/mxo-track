<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\PublicIdTrait;
use App\Enum\SheetState;
use App\Repository\PageLayoutWidgetRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PageLayoutWidgetRepository::class)]
#[ORM\Table(name: 'page_layout_widget')]
#[ORM\UniqueConstraint(name: 'uniq_page_layout_widget_public_id', columns: ['public_id'])]
#[ORM\Index(name: 'idx_plw_layout_state_position', columns: ['page_layout_id', 'sheet_state', 'position'])]
#[ORM\HasLifecycleCallbacks]
class PageLayoutWidget
{
    use PublicIdTrait;

    #[ORM\ManyToOne(targetEntity: PageLayout::class, inversedBy: 'widgets')]
    #[ORM\JoinColumn(name: 'page_layout_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private PageLayout $pageLayout;

    #[ORM\ManyToOne(targetEntity: WidgetDefinition::class)]
    #[ORM\JoinColumn(name: 'widget_definition_id', referencedColumnName: 'id', nullable: false)]
    private WidgetDefinition $widgetDefinition;

    #[ORM\Column(length: 20, enumType: SheetState::class)]
    private SheetState $sheetState;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $position;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        PageLayout $pageLayout,
        WidgetDefinition $widgetDefinition,
        SheetState $sheetState,
        int $position,
    ) {
        $this->pageLayout = $pageLayout;
        $this->widgetDefinition = $widgetDefinition;
        $this->sheetState = $sheetState;
        $this->position = $position;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getPageLayout(): PageLayout { return $this->pageLayout; }
    public function getWidgetDefinition(): WidgetDefinition { return $this->widgetDefinition; }
    public function getSheetState(): SheetState { return $this->sheetState; }
    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): void { $this->position = $position; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    #[ORM\PrePersist]
    public function touchCreatedAt(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }
}
