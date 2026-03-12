<?php

declare(strict_types=1);

namespace App\Tests\Unit\Provider\Routing;

use App\Provider\Routing\GoogleDirectionsEngine;
use App\Provider\Routing\GoogleDirectionsFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GoogleDirectionsFactoryTest extends TestCase
{
    #[Test]
    public function create_throws_when_no_api_key_configured(): void
    {
        $factory = new GoogleDirectionsFactory(
            $this->createMock(HttpClientInterface::class),
            '',
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/GOOGLE_DIRECTIONS_API_KEY/');

        $factory->create([]);
    }

    #[Test]
    public function create_throws_when_config_api_key_is_empty_string(): void
    {
        $factory = new GoogleDirectionsFactory(
            $this->createMock(HttpClientInterface::class),
            '',
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/GOOGLE_DIRECTIONS_API_KEY/');

        $factory->create(['api_key' => '']);
    }

    #[Test]
    public function create_succeeds_with_default_api_key(): void
    {
        $factory = new GoogleDirectionsFactory(
            $this->createMock(HttpClientInterface::class),
            'test-key-123',
        );

        $engine = $factory->create([]);
        $this->assertInstanceOf(GoogleDirectionsEngine::class, $engine);
    }

    #[Test]
    public function create_succeeds_with_config_api_key(): void
    {
        $factory = new GoogleDirectionsFactory(
            $this->createMock(HttpClientInterface::class),
            '',
        );

        $engine = $factory->create(['api_key' => 'explicit-key']);
        $this->assertInstanceOf(GoogleDirectionsEngine::class, $engine);
    }
}
