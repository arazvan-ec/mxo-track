<?php

declare(strict_types=1);

namespace App\Provider;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.provider_factory')]
interface ProviderFactoryInterface
{
    /**
     * Create a configured provider instance.
     *
     * @param array<string, mixed> $config Provider-specific configuration
     */
    public function create(array $config): object;

    public function getProviderType(): string;

    public function getServiceType(): ServiceType;
}
