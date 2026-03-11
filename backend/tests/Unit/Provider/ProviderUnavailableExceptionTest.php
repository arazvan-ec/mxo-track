<?php

declare(strict_types=1);

namespace App\Tests\Unit\Provider;

use App\Provider\ProviderUnavailableException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProviderUnavailableException::class)]
final class ProviderUnavailableExceptionTest extends TestCase
{
    #[Test]
    public function it_stores_provider_type(): void
    {
        $e = new ProviderUnavailableException('google_directions');
        self::assertSame('google_directions', $e->providerType);
    }

    #[Test]
    public function it_has_default_message(): void
    {
        $e = new ProviderUnavailableException('osrm');
        self::assertSame("Provider 'osrm' is unavailable", $e->getMessage());
    }

    #[Test]
    public function it_accepts_custom_message(): void
    {
        $e = new ProviderUnavailableException('vroom', 'Connection timeout');
        self::assertSame('Connection timeout', $e->getMessage());
    }

    #[Test]
    public function it_accepts_previous_exception(): void
    {
        $prev = new \RuntimeException('HTTP 503');
        $e = new ProviderUnavailableException('traccar', '', $prev);
        self::assertSame($prev, $e->getPrevious());
    }
}
