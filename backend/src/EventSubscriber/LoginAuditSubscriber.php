<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

final class LoginAuditSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly EntityManagerInterface $em,
        private readonly RequestStack $requestStack,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LoginFailureEvent::class => 'onLoginFailure',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        $request = $this->requestStack->getCurrentRequest();

        $this->auditLogger->log(
            actor: $user,
            action: 'login_success',
            entityType: 'User',
            entityId: method_exists($user, 'getId') ? (string) $user->getId() : '',
            payload: [
                'ip' => $request?->getClientIp(),
                'user_agent' => $request?->headers->get('User-Agent', ''),
            ],
        );
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $email = $request?->request->get('_username', '');

        $this->auditLogger->log(
            actor: null,
            action: 'login_failure',
            entityType: 'User',
            entityId: '',
            payload: [
                'email' => $email,
                'ip' => $request?->getClientIp(),
                'user_agent' => $request?->headers->get('User-Agent', ''),
            ],
        );
    }
}
