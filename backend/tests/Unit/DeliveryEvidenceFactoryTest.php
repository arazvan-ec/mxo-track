<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\DeliveryEvidenceFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeliveryEvidenceFactory::class)]
final class DeliveryEvidenceFactoryTest extends TestCase
{
    private DeliveryEvidenceFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new DeliveryEvidenceFactory();
    }

    #[Test]
    public function buildReturnsArrayWithAllRequiredKeys(): void
    {
        $evidence = $this->factory->build(
            'base64-recipient-id',
            true,
            'stop-123',
            'action-456',
            'driver-789',
            '192.168.1.1',
            'Mozilla/5.0',
        );

        self::assertArrayHasKey('confirmation_mode', $evidence);
        self::assertArrayHasKey('recipient_id_sha256', $evidence);
        self::assertArrayHasKey('confirmed_by_driver', $evidence);
        self::assertArrayHasKey('confirmed_at', $evidence);
        self::assertArrayHasKey('driver_ip', $evidence);
        self::assertArrayHasKey('driver_user_agent', $evidence);
        self::assertArrayHasKey('action_fingerprint', $evidence);
        self::assertArrayHasKey('fingerprint_bucket', $evidence);
    }

    #[Test]
    public function buildSetsRecipientIdEncodedConfirmationMode(): void
    {
        $evidence = $this->factory->build('id', true, 'stop', 'action', 'driver', '1.2.3.4', 'UA');

        self::assertSame('recipient_id_encoded', $evidence['confirmation_mode']);
    }

    #[Test]
    public function buildHashesRecipientIdWithSha256(): void
    {
        $recipientId = 'test-recipient-id-data';
        $expectedHash = hash('sha256', $recipientId);

        $evidence = $this->factory->build($recipientId, true, 'stop', 'action', 'driver', '', '');

        self::assertSame($expectedHash, $evidence['recipient_id_sha256']);
    }

    #[Test]
    public function buildPreservesDriverMetadata(): void
    {
        $evidence = $this->factory->build(
            'id',
            true,
            'stop',
            'action',
            'driver',
            '10.0.0.1',
            'CustomUserAgent/1.0',
        );

        self::assertSame('10.0.0.1', $evidence['driver_ip']);
        self::assertSame('CustomUserAgent/1.0', $evidence['driver_user_agent']);
        self::assertTrue($evidence['confirmed_by_driver']);
    }

    #[Test]
    public function buildGeneratesConsistentFingerprintForSameInput(): void
    {
        // Two calls within the same minute should produce the same fingerprint
        $evidence1 = $this->factory->build('id', true, 'stop-1', 'action-1', 'driver-1', '', '');
        $evidence2 = $this->factory->build('id', true, 'stop-1', 'action-1', 'driver-1', '', '');

        self::assertSame($evidence1['action_fingerprint'], $evidence2['action_fingerprint']);
        self::assertSame($evidence1['fingerprint_bucket'], $evidence2['fingerprint_bucket']);
    }

    #[Test]
    public function buildGeneratesDifferentFingerprintForDifferentStops(): void
    {
        $evidence1 = $this->factory->build('id', true, 'stop-A', 'action-1', 'driver-1', '', '');
        $evidence2 = $this->factory->build('id', true, 'stop-B', 'action-1', 'driver-1', '', '');

        self::assertNotSame($evidence1['action_fingerprint'], $evidence2['action_fingerprint']);
    }

    #[Test]
    public function buildConfirmedAtIsValidAtomDate(): void
    {
        $evidence = $this->factory->build('id', true, 'stop', 'action', 'driver', '', '');

        $parsed = \DateTimeImmutable::createFromFormat(DATE_ATOM, $evidence['confirmed_at']);
        self::assertInstanceOf(\DateTimeImmutable::class, $parsed);
    }
}
