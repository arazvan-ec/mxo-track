<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\PageLayout;
use App\Entity\PageLayoutWidget;
use App\Entity\WidgetDefinition;
use App\Enum\PageKey;
use App\Enum\SheetState;
use App\Enum\WidgetType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PageLayout::class)]
#[CoversClass(PageLayoutWidget::class)]
final class PageLayoutTest extends TestCase
{
    #[Test]
    public function it_creates_global_layout(): void
    {
        $layout = new PageLayout(PageKey::TEST_ROUTING);

        self::assertSame(PageKey::TEST_ROUTING, $layout->getPageKey());
        self::assertNull($layout->getCustomer());
        self::assertTrue($layout->isActive());
        self::assertCount(0, $layout->getWidgets());
    }

    #[Test]
    public function it_adds_and_removes_widgets(): void
    {
        $layout = new PageLayout(PageKey::FLEET_MAP);
        $widgetDef = new WidgetDefinition(WidgetType::METRIC_PAIRS, 'Metric Pairs');

        $plw = new PageLayoutWidget($layout, $widgetDef, SheetState::COLLAPSED, 0);
        $layout->addWidget($plw);

        self::assertCount(1, $layout->getWidgets());
        self::assertSame($layout, $plw->getPageLayout());
        self::assertSame($widgetDef, $plw->getWidgetDefinition());
        self::assertSame(SheetState::COLLAPSED, $plw->getSheetState());
        self::assertSame(0, $plw->getPosition());

        $layout->removeWidget($plw);
        self::assertCount(0, $layout->getWidgets());
    }

    #[Test]
    public function it_filters_widgets_by_state(): void
    {
        $layout = new PageLayout(PageKey::TEST_ROUTING);
        $metricDef = new WidgetDefinition(WidgetType::METRIC_PAIRS, 'Metric Pairs');
        $legendDef = new WidgetDefinition(WidgetType::MAP_LEGEND, 'Map Legend');

        $collapsed = new PageLayoutWidget($layout, $metricDef, SheetState::COLLAPSED, 0);
        $half1 = new PageLayoutWidget($layout, $metricDef, SheetState::HALF, 0);
        $half2 = new PageLayoutWidget($layout, $legendDef, SheetState::HALF, 1);

        $layout->addWidget($collapsed);
        $layout->addWidget($half1);
        $layout->addWidget($half2);

        self::assertCount(1, $layout->getWidgetsForState(SheetState::COLLAPSED));
        self::assertCount(2, $layout->getWidgetsForState(SheetState::HALF));
        self::assertCount(0, $layout->getWidgetsForState(SheetState::FULL));
    }

    #[Test]
    public function clear_widgets_removes_all(): void
    {
        $layout = new PageLayout(PageKey::ROUTE_PLANNER);
        $widgetDef = new WidgetDefinition(WidgetType::STOP_LIST, 'Stop List');

        $layout->addWidget(new PageLayoutWidget($layout, $widgetDef, SheetState::COLLAPSED, 0));
        $layout->addWidget(new PageLayoutWidget($layout, $widgetDef, SheetState::HALF, 0));

        self::assertCount(2, $layout->getWidgets());

        $layout->clearWidgets();
        self::assertCount(0, $layout->getWidgets());
    }
}
