<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\ApiKey;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class ApiKeyAuthenticator extends AbstractAuthenticator
{
    private const HEADER = 'X-Api-Key';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->headers->has(self::HEADER);
    }

    public function authenticate(Request $request): Passport
    {
        $rawKey = $request->headers->get(self::HEADER, '');
        if ($rawKey === '') {
            throw new CustomUserMessageAuthenticationException('API key missing.');
        }

        $keyHash = hash('sha256', $rawKey);

        $apiKey = $this->entityManager->getRepository(ApiKey::class)->findOneBy([
            'keyHash' => $keyHash,
            'isActive' => true,
        ]);

        if (!$apiKey instanceof ApiKey) {
            throw new CustomUserMessageAuthenticationException('Invalid API key.');
        }

        if (!$apiKey->getCustomer()->isActive()) {
            throw new CustomUserMessageAuthenticationException('Customer account is inactive.');
        }

        $apiKey->touchLastUsed();
        $this->entityManager->flush();

        // Store API key on request attributes so rate limiter can access it
        $request->attributes->set('_api_key', $apiKey);

        // Find or build a user identifier for this API key's customer
        $customerId = $apiKey->getCustomer()->getId();
        $userIdentifier = 'api-key:customer:' . $customerId;

        return new SelfValidatingPassport(
            new UserBadge($userIdentifier, function () use ($apiKey): ApiKeyUser {
                return new ApiKeyUser($apiKey);
            }),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Let the request continue to the controller
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'error' => [
                'code' => 'authentication_failed',
                'message' => strtr($exception->getMessageKey(), $exception->getMessageData()),
            ],
        ], Response::HTTP_UNAUTHORIZED);
    }
}
