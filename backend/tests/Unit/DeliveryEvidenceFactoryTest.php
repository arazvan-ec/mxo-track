<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\DeliveryEvidenceFactory;
use PHPUnit\Framework\TestCase;

final class DeliveryEvidenceFactoryTest extends TestCase
{
    public function testBuildIncludesExpectedSecurityFields(): void
    {
        $factory = new DeliveryEvidenceFactory();

        $result = $factory->build(
            'ZWplbXBsby1kbmk=',
            true,
            'stop-1',
            '11111111-1111-1111-1111-111111111111',
            'driver-1',
            '10.0.0.10',
            'Mozilla/5.0 TestAgent',
        );

        self::assertSame('recipient_id_encoded', $result['confirmation_mode']);
        self::assertSame(hash('sha256', 'ZWplbXBsby1kbmk='), $result['recipient_id_sha256']);
        self::assertTrue($result['confirmed_by_driver']);
        self::assertSame('10.0.0.10', $result['driver_ip']);
        self::assertSame('Mozilla/5.0 TestAgent', $result['driver_user_agent']);
        self::assertArrayHasKey('action_fingerprint', $result);
        self::assertArrayHasKey('fingerprint_bucket', $result);
    }
}
