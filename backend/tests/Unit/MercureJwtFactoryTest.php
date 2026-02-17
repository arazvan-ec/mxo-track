<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Customer;
use App\Entity\User;
use App\Security\TopicResolver;
use App\Service\MercureJwtFactory;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPUnit\Framework\TestCase;

final class MercureJwtFactoryTest extends TestCase
{
    public function testCustomerGetsExactTopicsWithoutWildcard(): void
    {
        $customer = new Customer('Cliente A');
        $user = new User('customer@example.com');
        $user->setRoles(['ROLE_CUSTOMER']);
        $user->setCustomer($customer);

        $factory = new MercureJwtFactory(new TopicResolver(), 'secret', 3600);
        $token = $factory->createSubscriberToken($user, ['veh-1', 'veh-2']);
        $decoded = (array) JWT::decode($token, new Key('secret', 'HS256'));

        $topics = (array) ((array) $decoded['mercure'])['subscribe'];
        self::assertNotContains('/*', $topics);
        self::assertContains('/vehicles/veh-1/position', $topics);
        self::assertContains('/vehicles/veh-2/position', $topics);
    }
}
