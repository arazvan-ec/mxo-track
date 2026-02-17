<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use PHPUnit\Framework\TestCase;

final class DriverIdempotencyTest extends TestCase
{
    public function testDuplicateClientActionIdShouldNotDuplicateEvent(): void
    {
        $processed = [];
        $actionId = '2d6a69c1-d7e4-40e6-a271-9e5307f0100f';

        $first = $this->register($processed, $actionId);
        $second = $this->register($processed, $actionId);

        self::assertTrue($first);
        self::assertFalse($second);
    }

    private function register(array &$processed, string $id): bool
    {
        if (isset($processed[$id])) {
            return false;
        }
        $processed[$id] = true;
        return true;
    }
}
