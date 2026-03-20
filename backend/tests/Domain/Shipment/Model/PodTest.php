<?php

declare(strict_types=1);

namespace App\Tests\Domain\Shipment\Model;

use App\Domain\Route\Model\Route;
use App\Domain\Route\Model\RouteStop;
use App\Domain\Shipment\Model\Pod;
use App\Entity\User;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PodTest extends TestCase
{
    #[Test]
    public function constructorSetsFields(): void
    {
        $route = new Route('Test Route');
        $stop = new RouteStop($route, 1, 'Calle Mayor 1');
        $driver = $this->createMock(User::class);

        $pod = new Pod($stop, $driver, 'Juan Perez', 'encoded-id-123');

        self::assertSame('Juan Perez', $pod->getSignedByName());
        self::assertSame('encoded-id-123', $pod->getRecipientIdEncoded());
        self::assertTrue($pod->isConfirmedByDriver());
    }

    #[Test]
    public function constructorSetsCreatedAt(): void
    {
        $route = new Route('Test Route');
        $stop = new RouteStop($route, 1, 'Calle Mayor 1');
        $driver = $this->createMock(User::class);
        $before = new \DateTimeImmutable();

        $pod = new Pod($stop, $driver, 'Juan', 'encoded');

        self::assertGreaterThanOrEqual($before, $pod->getCreatedAt());
    }

    #[Test]
    public function hasPublicIdMethods(): void
    {
        $route = new Route('Test Route');
        $stop = new RouteStop($route, 1, 'Calle Mayor 1');
        $driver = $this->createMock(User::class);

        $pod = new Pod($stop, $driver, 'Juan', 'encoded');
        $pod->initializePublicId();

        self::assertNotNull($pod->getPublicId());
        self::assertNotEmpty($pod->getPublicIdString());
    }
}
