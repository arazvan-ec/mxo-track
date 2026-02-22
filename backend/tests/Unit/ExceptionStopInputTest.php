<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Dto\Driver\ExceptionStopInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExceptionStopInput::class)]
final class ExceptionStopInputTest extends TestCase
{
    #[Test]
    public function fromArrayMapsAllFields(): void
    {
        $payload = [
            'client_action_id' => '550e8400-e29b-41d4-a716-446655440000',
            'reason' => 'ABSENT',
            'comment' => 'Nobody answered the door',
            'shipment_public_id' => '01HX1234ABCDEFGHIJKLMNOP',
        ];

        $input = ExceptionStopInput::fromArray($payload);

        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $input->clientActionId);
        self::assertSame('ABSENT', $input->reason);
        self::assertSame('Nobody answered the door', $input->comment);
        self::assertSame('01HX1234ABCDEFGHIJKLMNOP', $input->shipmentPublicId);
    }

    #[Test]
    public function fromArrayDefaultsToOtherReason(): void
    {
        $payload = [
            'client_action_id' => '550e8400-e29b-41d4-a716-446655440000',
        ];

        $input = ExceptionStopInput::fromArray($payload);

        self::assertSame('OTHER', $input->reason);
        self::assertSame('', $input->comment);
        self::assertNull($input->shipmentPublicId);
    }

    #[Test]
    public function fromArrayHandlesEmptyPayload(): void
    {
        $input = ExceptionStopInput::fromArray([]);

        self::assertSame('', $input->clientActionId);
        self::assertSame('OTHER', $input->reason);
        self::assertSame('', $input->comment);
        self::assertNull($input->shipmentPublicId);
    }
}
