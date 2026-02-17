<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AuthFlowTest extends WebTestCase
{
    public function testDashboardRedirectsToLoginWhenAnonymous(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseRedirects('/login');
    }

    public function testLoginCreatesSession(): void
    {
        $client = static::createClient();
        $container = static::getContainer();

        $this->createUser($container->get(EntityManagerInterface::class), $container->get(UserPasswordHasherInterface::class), true);

        $crawler = $client->request('GET', '/login');
        $client->submit($crawler->selectButton('Entrar')->form([
            '_username' => 'admin@transporte.local',
            '_password' => 'ChangeMe_123!',
        ]));

        self::assertResponseRedirects('/');
    }

    public function testInactiveUserCannotLogin(): void
    {
        $client = static::createClient();
        $container = static::getContainer();

        $this->createUser($container->get(EntityManagerInterface::class), $container->get(UserPasswordHasherInterface::class), false);

        $crawler = $client->request('GET', '/login');
        $client->submit($crawler->selectButton('Entrar')->form([
            '_username' => 'admin@transporte.local',
            '_password' => 'ChangeMe_123!',
        ]));

        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextContains('body', 'Credenciales inválidas.');
    }

    private function createUser(EntityManagerInterface $entityManager, UserPasswordHasherInterface $hasher, bool $active): void
    {
        $existing = $entityManager->getRepository(User::class)->findOneBy(['email' => 'admin@transporte.local']);
        if ($existing instanceof User) {
            $existing->setActive($active);
            $entityManager->flush();
            return;
        }

        $user = new User('admin@transporte.local');
        $user->assignRole(UserRole::ADMIN);
        $user->setActive($active);
        $user->setPassword($hasher->hashPassword($user, 'ChangeMe_123!'));
        $entityManager->persist($user);
        $entityManager->flush();
    }
}
