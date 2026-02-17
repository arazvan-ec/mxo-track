<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminUserFixture extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $admin = new User('admin@transporte.local');
        $admin->assignRole(UserRole::ADMIN);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'ChangeMe_123!'));
        $admin->setActive(true);

        $manager->persist($admin);
        $manager->flush();
    }
}
