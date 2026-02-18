<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class DoctrineCustomerFilterSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 50],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $filters = $this->entityManager->getFilters();
        if (!$filters->has('customer_tenant')) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            if ($filters->isEnabled('customer_tenant')) {
                $filters->disable('customer_tenant');
            }

            return;
        }

        $shouldEnable = ($user->hasRole('ROLE_CUSTOMER') || $user->hasRole('ROLE_DRIVER'))
            && $user->getCustomer() !== null;

        if (!$shouldEnable) {
            if ($filters->isEnabled('customer_tenant')) {
                $filters->disable('customer_tenant');
            }

            return;
        }

        if (!$filters->isEnabled('customer_tenant')) {
            $filters->enable('customer_tenant');
        }

        $filters->getFilter('customer_tenant')->setParameter('customer_id', (string) $user->getCustomer()?->getId());
    }
}
