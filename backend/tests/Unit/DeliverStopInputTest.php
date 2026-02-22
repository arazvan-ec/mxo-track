<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Dto\Driver\DeliverStopInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeliverStopInput::class)]
final class DeliverStopInputTest extends TestCase
{
    #[Test]
    public function fromArrayMapsAllFields(): void
    {
        $payload = [
            'client_action_id' => '550e8400-e29b-41d4-a716-446655440000',
            'signed_by_name' => 'Maria Garcia',
            'recipient_id_encoded' => 'base64-encoded-data-here',
            'confirmed_by_driver' => true,
            'shipment_public_id' => '01HX1234ABCDEFGHIJKLMNOP',
        ];

        $input = DeliverStopInput::fromArray($payload);

        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $input->clientActionId);
        self::assertSame('Maria Garcia', $input->signedByName);
        self::assertSame('base64-encoded-data-here', $input->recipientIdEncoded);
        self::assertTrue($input->confirmedByDriver);
        self::assertSame('01HX1234ABCDEFGHIJKLMNOP', $input->shipmentPublicId);
    }

    #[Test]
    public function fromArrayHandlesMissingOptionalFields(): void
    {
        $payload = [
            'client_action_id' => '550e8400-e29b-41d4-a716-446655440000',
            'signed_by_name' => 'John Doe',
            'recipient_id_encoded' => 'some-data',
        ];

        $input = DeliverStopInput::fromArray($payload);

        self::assertFalse($input->confirmedByDriver);
        self::assertNull($input->shipmentPublicId);
    }

    #[Test]
    public function fromArrayHandlesEmptyPayload(): void
    {
        $input = DeliverStopInput::fromArray([]);

        self::assertSame('', $input->clientActionId);
        self::assertSame('', $input->signedByName);
        self::assertSame('', $input->recipientIdEncoded);
        self::assertFalse($input->confirmedByDriver);
        self::assertNull($input->shipmentPublicId);
    }
}
