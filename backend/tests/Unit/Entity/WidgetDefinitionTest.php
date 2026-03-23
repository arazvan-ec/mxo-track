<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\WidgetDefinition;
use App\Enum\WidgetType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(WidgetDefinition::class)]
final class WidgetDefinitionTest extends TestCase
{
    #[Test]
    public function it_stores_type_and_label(): void
    {
        $widget = new WidgetDefinition(WidgetType::METRIC_PAIRS, 'Metric Pairs');

        self::assertSame(WidgetType::METRIC_PAIRS, $widget->getType());
        self::assertSame('Metric Pairs', $widget->getLabel());
        self::assertTrue($widget->isActive());
        self::assertNull($widget->getDescription());
        self::assertNull($widget->getPreviewImage());
    }

    #[Test]
    public function it_allows_setting_optional_fields(): void
    {
        $widget = new WidgetDefinition(WidgetType::STOP_LIST, 'Stop List');
        $widget->setDescription('Shows ordered stops');
        $widget->setPreviewImage('/images/stop-list.png');
        $widget->setActive(false);

        self::assertSame('Shows ordered stops', $widget->getDescription());
        self::assertSame('/images/stop-list.png', $widget->getPreviewImage());
        self::assertFalse($widget->isActive());
    }

    #[Test]
    public function it_initializes_timestamps(): void
    {
        $before = new \DateTimeImmutable();
        $widget = new WidgetDefinition(WidgetType::KPI_PILLS, 'KPI Pills');
        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $widget->getCreatedAt());
        self::assertLessThanOrEqual($after, $widget->getCreatedAt());
        self::assertGreaterThanOrEqual($before, $widget->getUpdatedAt());
    }
}
