<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\VehicleSkill;
use PHPUnit\Framework\TestCase;

final class VehicleSkillMarkerColorTest extends TestCase
{
    public function testEachSkillHasUniqueMarkerColor(): void
    {
        $colors = [];
        foreach (VehicleSkill::cases() as $skill) {
            $color = $skill->markerColor();
            self::assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $color, "{$skill->name} should have valid hex color");
            $colors[$skill->name] = $color;
        }

        // All colors should be unique
        self::assertCount(\count(VehicleSkill::cases()), array_unique($colors));
    }

    public function testSpecificSkillColors(): void
    {
        self::assertSame('#0ea5e9', VehicleSkill::REFRIGERATED->markerColor());
        self::assertSame('#f97316', VehicleSkill::HEAVY_LOAD->markerColor());
        self::assertSame('#ef4444', VehicleSkill::HAZMAT->markerColor());
    }

    public function testDefaultMarkerColor(): void
    {
        self::assertSame('#6366f1', VehicleSkill::defaultMarkerColor());
    }
}
