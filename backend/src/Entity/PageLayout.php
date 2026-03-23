<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concerns\PublicIdTrait;
use App\Enum\PageKey;
use App\Enum\SheetState;
use App\Repository\PageLayoutRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PageLayoutRepository::class)]
#[ORM\Table(name: 'page_layout')]
#[ORM\UniqueConstraint(name: 'uniq_page_layout_public_id', columns: ['public_id'])]
#[ORM\UniqueConstraint(name: 'uniq_page_layout_page_customer', columns: ['page_key', 'customer_id'])]
#[ORM\HasLifecycleCallbacks]
class PageLayout
{
    use PublicIdTrait;

    #[ORM\Column(length: 50, enumType: PageKey::class)]
    private PageKey $pageKey;

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(name: 'customer_id', referencedColumnName: 'id', nullable: true)]
    private ?Customer $customer;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, PageLayoutWidget> */
    #[ORM\OneToMany(targetEntity: PageLayoutWidget::class, mappedBy: 'pageLayout', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sheetState' => 'ASC', 'position' => 'ASC'])]
    private Collection $widgets;

    public function __construct(PageKey $pageKey, ?Customer $customer = null)
    {
        $now = new \DateTimeImmutable();
        $this->pageKey = $pageKey;
        $this->customer = $customer;
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->widgets = new ArrayCollection();
    }

    public function getPageKey(): PageKey { return $this->pageKey; }

    public function getCustomer(): ?Customer { return $this->customer; }

    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): void { $this->active = $active; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    /** @return Collection<int, PageLayoutWidget> */
    public function getWidgets(): Collection { return $this->widgets; }

    /** @return Collection<int, PageLayoutWidget> */
    public function getWidgetsForState(SheetState $state): Collection
    {
        return $this->widgets->filter(
            static fn (PageLayoutWidget $w): bool => $w->getSheetState() === $state,
        );
    }

    public function addWidget(PageLayoutWidget $widget): void
    {
        if (!$this->widgets->contains($widget)) {
            $this->widgets->add($widget);
        }
    }

    public function removeWidget(PageLayoutWidget $widget): void
    {
        $this->widgets->removeElement($widget);
    }

    public function clearWidgets(): void
    {
        $this->widgets->clear();
    }

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
