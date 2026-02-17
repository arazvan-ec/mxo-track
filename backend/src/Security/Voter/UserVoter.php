<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
use App\Enum\UserRole;

class UserVoter extends BaseVoter
{
    public const VIEW = 'user.view';
    public const MANAGE = 'user.manage';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::MANAGE], true) && $subject instanceof User;
    }

    protected function isGrantedForUser(string $attribute, mixed $subject, User $user): bool
    {
        if (!$subject instanceof User) {
            return false;
        }

        if ($attribute === self::VIEW) {
            return $user->getPublicIdString() === $subject->getPublicIdString() || $user->hasRole(UserRole::OPERATOR->value);
        }

        return $user->hasRole(UserRole::OPERATOR->value);
    }
}
