<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
use App\Enum\UserRole;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

abstract class BaseVoter extends Voter
{
    final protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User || !$user->isActive()) {
            return false;
        }

        if ($user->hasRole(UserRole::ADMIN->value)) {
            return true;
        }

        return $this->isGrantedForUser($attribute, $subject, $user);
    }

    abstract protected function isGrantedForUser(string $attribute, mixed $subject, User $user): bool;
}
