<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Customer;
use App\Entity\User;
use App\Enum\UserRole;
use App\Security\TopicResolver;
use App\Service\MercureJwtFactory;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MercureJwtFactory::class)]
final class MercureJwtFactoryTest extends TestCase
{
    private const SUBSCRIBER_KEY = 'test-subscriber-jwt-key-32bytesxx';
    private const TTL_SECONDS = 3600;

    #[Test]
    public function createSubscriberTokenReturnsValidJwt(): void
    {
        $topicResolver = new TopicResolver();
        $factory = new MercureJwtFactory($topicResolver, self::SUBSCRIBER_KEY, self::TTL_SECONDS);

        $user = new User('admin@test.com');
        $user->setRoles([UserRole::ADMIN->value]);

        $token = $factory->createSubscriberToken($user);

        self::assertIsString($token);
        self::assertNotEmpty($token);

        // The token should be decodable with the correct key
        $decoded = JWT::decode($token, new Key(self::SUBSCRIBER_KEY, 'HS256'));

        self::assertIsObject($decoded);
        self::assertObjectHasProperty('mercure', $decoded);
        self::assertObjectHasProperty('subscribe', $decoded->mercure);
    }

    #[Test]
    public function adminUserGetsWildcardSubscription(): void
    {
        $topicResolver = new TopicResolver();
        $factory = new MercureJwtFactory($topicResolver, self::SUBSCRIBER_KEY, self::TTL_SECONDS);

        $user = new User('admin@test.com');
        $user->setRoles([UserRole::ADMIN->value]);

        $token = $factory->createSubscriberToken($user);
        $decoded = JWT::decode($token, new Key(self::SUBSCRIBER_KEY, 'HS256'));

        self::assertContains('*', $decoded->mercure->subscribe);
    }

    #[Test]
    public function driverUserGetsVehicleTopicsOnly(): void
    {
        $topicResolver = new TopicResolver();
        $factory = new MercureJwtFactory($topicResolver, self::SUBSCRIBER_KEY, self::TTL_SECONDS);

        $user = new User('driver@test.com');
        $user->setRoles([UserRole::DRIVER->value]);

        $vehiclePublicIds = ['01HX1234ABCDEF5678900000', '01HX5678ABCDEF1234560000'];
        $token = $factory->createSubscriberToken($user, $vehiclePublicIds);
        $decoded = JWT::decode($token, new Key(self::SUBSCRIBER_KEY, 'HS256'));

        $topics = $decoded->mercure->subscribe;
        self::assertCount(2, $topics);
        self::assertContains('/vehicles/01HX1234ABCDEF5678900000/position', $topics);
        self::assertContains('/vehicles/01HX5678ABCDEF1234560000/position', $topics);
    }

    #[Test]
    public function tokenContainsCorrectExpiration(): void
    {
        $topicResolver = new TopicResolver();
        $factory = new MercureJwtFactory($topicResolver, self::SUBSCRIBER_KEY, self::TTL_SECONDS);

        $user = new User('admin@test.com');
        $user->setRoles([UserRole::ADMIN->value]);

        $beforeTime = time();
        $token = $factory->createSubscriberToken($user);
        $afterTime = time();

        $decoded = JWT::decode($token, new Key(self::SUBSCRIBER_KEY, 'HS256'));

        self::assertObjectHasProperty('iat', $decoded);
        self::assertObjectHasProperty('exp', $decoded);

        // iat should be approximately "now"
        self::assertGreaterThanOrEqual($beforeTime, $decoded->iat);
        self::assertLessThanOrEqual($afterTime, $decoded->iat);

        // exp should be iat + TTL
        self::assertSame($decoded->iat + self::TTL_SECONDS, $decoded->exp);
    }

    #[Test]
    public function tokenContainsUserSubjectAndRole(): void
    {
        $topicResolver = new TopicResolver();
        $factory = new MercureJwtFactory($topicResolver, self::SUBSCRIBER_KEY, self::TTL_SECONDS);

        $user = new User('driver@test.com');
        $user->setRoles([UserRole::DRIVER->value]);

        $token = $factory->createSubscriberToken($user);
        $decoded = JWT::decode($token, new Key(self::SUBSCRIBER_KEY, 'HS256'));

        // Sub contains user:<id>
        self::assertObjectHasProperty('sub', $decoded);
        self::assertStringStartsWith('user:', $decoded->sub);

        // Role contains the user's roles
        self::assertObjectHasProperty('role', $decoded);
        self::assertStringContainsString('ROLE_DRIVER', $decoded->role);
    }

    #[Test]
    public function tokenWithWrongKeyFailsToDecode(): void
    {
        $topicResolver = new TopicResolver();
        $factory = new MercureJwtFactory($topicResolver, self::SUBSCRIBER_KEY, self::TTL_SECONDS);

        $user = new User('admin@test.com');
        $user->setRoles([UserRole::ADMIN->value]);

        $token = $factory->createSubscriberToken($user);

        $this->expectException(\Exception::class);
        JWT::decode($token, new Key('wrong-key-wrong-key-wrong-key-32', 'HS256'));
    }

    #[Test]
    public function driverWithNoVehiclesGetsEmptyTopics(): void
    {
        $topicResolver = new TopicResolver();
        $factory = new MercureJwtFactory($topicResolver, self::SUBSCRIBER_KEY, self::TTL_SECONDS);

        $user = new User('driver@test.com');
        $user->setRoles([UserRole::DRIVER->value]);

        $token = $factory->createSubscriberToken($user);
        $decoded = JWT::decode($token, new Key(self::SUBSCRIBER_KEY, 'HS256'));

        self::assertEmpty($decoded->mercure->subscribe);
    }

    #[Test]
    public function userWithNoSpecialRolesGetsEmptyTopics(): void
    {
        $topicResolver = new TopicResolver();
        $factory = new MercureJwtFactory($topicResolver, self::SUBSCRIBER_KEY, self::TTL_SECONDS);

        // User with only ROLE_USER (no customer, no driver, no admin)
        $user = new User('basic@test.com');
        $user->setRoles([]);

        $token = $factory->createSubscriberToken($user);
        $decoded = JWT::decode($token, new Key(self::SUBSCRIBER_KEY, 'HS256'));

        self::assertEmpty($decoded->mercure->subscribe);
    }
}
