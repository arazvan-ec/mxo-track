<?php
declare(strict_types=1);
namespace App\Provider;

final class FallbackChain
{
    /**
     * @param list<object> $providers Ordered by priority (first = primary)
     */
    public function __construct(private readonly array $providers) {}

    /**
     * Try each provider in order. Return first successful result.
     * Only catches ProviderUnavailableException (transient failures).
     *
     * @template T
     * @param callable(object): T $operation
     * @return T
     */
    public function execute(callable $operation): mixed
    {
        $lastException = null;

        foreach ($this->providers as $provider) {
            try {
                return $operation($provider);
            } catch (ProviderUnavailableException $e) {
                $lastException = $e;
            }
        }

        throw $lastException ?? new \RuntimeException('No providers available');
    }
}
