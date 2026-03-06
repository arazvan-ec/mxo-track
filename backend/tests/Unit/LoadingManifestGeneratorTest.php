<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Dto\LoadingManifestItem;
use App\Entity\Customer;
use App\Entity\Route;
use App\Entity\RouteStop;
use App\Entity\Shipment;
use App\Service\LoadingManifestGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(LoadingManifestGenerator::class)]
final class LoadingManifestGeneratorTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private LoadingManifestGenerator $generator;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->generator = new LoadingManifestGenerator($this->em);
    }

    private function mockDqlQuery(array $stops): void
    {
        $query = $this->createMock(Query::class);
        $query->method('setParameter')->willReturnSelf();
        $query->method('getResult')->willReturn($stops);

        $this->em->method('createQuery')->willReturn($query);
    }

    private function createStopWithShipment(Route $route, int $seq): RouteStop
    {
        $customer = new Customer('Test');
        $shipment = new Shipment('REF-' . $seq, $customer);
        $shipment->initializePublicId();
        $shipment->setTotalWeightKg((float) $seq);
        $shipment->setTotalVolumeM3($seq * 0.1);
        $shipment->setTotalParcels($seq);

        $stop = new RouteStop($route, $seq, 'Address ' . $seq);
        $stop->setShipment($shipment);
        $stop->setRecipientName('Recipient ' . $seq);
        $stop->setRecipientPhone('600' . str_pad((string) $seq, 6, '0', STR_PAD_LEFT));

        return $stop;
    }

    #[Test]
    public function lifoOrderLastDeliveryLoadedFirst(): void
    {
        $route = new Route('Test');
        $stop1 = $this->createStopWithShipment($route, 1);
        $stop2 = $this->createStopWithShipment($route, 2);
        $stop3 = $this->createStopWithShipment($route, 3);

        $this->mockDqlQuery([$stop1, $stop2, $stop3]);

        $manifest = $this->generator->generateManifest($route);

        self::assertCount(3, $manifest);
        self::assertSame(1, $manifest[0]->loadingOrder);
        self::assertSame(3, $manifest[0]->deliverySequence);
        self::assertSame(2, $manifest[1]->loadingOrder);
        self::assertSame(2, $manifest[1]->deliverySequence);
        self::assertSame(3, $manifest[2]->loadingOrder);
        self::assertSame(1, $manifest[2]->deliverySequence);
    }

    #[Test]
    public function manifestItemHasCorrectFields(): void
    {
        $route = new Route('Test');
        $stop = $this->createStopWithShipment($route, 1);

        $this->mockDqlQuery([$stop]);

        $manifest = $this->generator->generateManifest($route);

        self::assertCount(1, $manifest);
        $item = $manifest[0];
        self::assertInstanceOf(LoadingManifestItem::class, $item);
        self::assertSame(1, $item->loadingOrder);
        self::assertSame(1, $item->deliverySequence);
        self::assertSame('REF-1', $item->shipmentReference);
        self::assertSame('Address 1', $item->address);
        self::assertSame('Recipient 1', $item->recipientName);
        self::assertSame(1.0, $item->weightKg);
        self::assertSame(0.1, $item->volumeM3);
        self::assertSame(1, $item->parcels);
    }

    #[Test]
    public function emptyRouteReturnsEmptyManifest(): void
    {
        $route = new Route('Test');
        $this->mockDqlQuery([]);

        $manifest = $this->generator->generateManifest($route);

        self::assertSame([], $manifest);
    }

    #[Test]
    public function singleStopHasLoadingOrderOne(): void
    {
        $route = new Route('Test');
        $stop = $this->createStopWithShipment($route, 5);

        $this->mockDqlQuery([$stop]);

        $manifest = $this->generator->generateManifest($route);

        self::assertCount(1, $manifest);
        self::assertSame(1, $manifest[0]->loadingOrder);
        self::assertSame(5, $manifest[0]->deliverySequence);
    }
}
