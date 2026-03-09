<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\ApiKey;
use App\Entity\Customer;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Lightweight user implementation for API key-authenticated requests.
 * Carries ROLE_CUSTOMER so the Doctrine tenant filter activates automatically.
 */
final class ApiKeyUser implements UserInterface
{
    public function __construct(
        private readonly ApiKey $apiKey,
    ) {
    }

    public function getRoles(): array
    {
        return ['ROLE_CUSTOMER', 'ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return 'api-key:customer:' . $this->apiKey->getCustomer()->getId();
    }

    public function getCustomer(): Customer
    {
        return $this->apiKey->getCustomer();
    }

    public function getApiKey(): ApiKey
    {
        return $this->apiKey;
    }
}
