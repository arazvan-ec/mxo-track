<?php
declare(strict_types=1);
namespace App\Tests\Unit\Provider;

use App\Provider\FallbackChain;
use App\Provider\ProviderUnavailableException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FallbackChain::class)]
final class FallbackChainTest extends TestCase
{
    #[Test]
    public function it_returns_result_from_first_provider(): void
    {
        $chain = new FallbackChain(['provider1', 'provider2']);

        $result = $chain->execute(fn(string $p) => "result_from_$p");
        self::assertSame('result_from_provider1', $result);
    }

    #[Test]
    public function it_falls_back_on_provider_unavailable_exception(): void
    {
        $chain = new FallbackChain(['provider1', 'provider2']);

        $callCount = 0;
        $result = $chain->execute(function (string $p) use (&$callCount) {
            $callCount++;
            if ($p === 'provider1') {
                throw new ProviderUnavailableException('provider1', 'Down');
            }
            return "result_from_$p";
        });

        self::assertSame('result_from_provider2', $result);
        self::assertSame(2, $callCount);
    }

    #[Test]
    public function it_does_not_catch_other_exceptions(): void
    {
        $chain = new FallbackChain(['provider1', 'provider2']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Bug!');

        $chain->execute(fn() => throw new \LogicException('Bug!'));
    }

    #[Test]
    public function it_throws_last_exception_when_all_fail(): void
    {
        $chain = new FallbackChain(['p1', 'p2']);

        $this->expectException(ProviderUnavailableException::class);
        $this->expectExceptionMessage('p2 failed');

        $chain->execute(function (string $p) {
            throw new ProviderUnavailableException($p, "$p failed");
        });
    }

    #[Test]
    public function it_throws_runtime_when_no_providers(): void
    {
        $chain = new FallbackChain([]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No providers available');

        $chain->execute(fn() => 'never');
    }
}
