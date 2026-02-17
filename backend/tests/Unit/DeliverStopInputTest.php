<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Dto\Driver\DeliverStopInput;
use PHPUnit\Framework\TestCase;

final class DeliverStopInputTest extends TestCase
{
    public function testFromArrayMapsExpectedFields(): void
    {
        $dto = DeliverStopInput::fromArray([
            'client_action_id' => '11111111-1111-1111-1111-111111111111',
            'signed_by_name' => 'Recepción',
            'recipient_id_encoded' => 'ZWplbXBsby1kbmk=',
            'confirmed_by_driver' => true,
            'shipment_public_id' => '01JQYJ5N6P4D7R8T2E2NNKNY7A',
        ]);

        self::assertSame('11111111-1111-1111-1111-111111111111', $dto->clientActionId);
        self::assertSame('Recepción', $dto->signedByName);
        self::assertSame('ZWplbXBsby1kbmk=', $dto->recipientIdEncoded);
        self::assertTrue($dto->confirmedByDriver);
        self::assertSame('01JQYJ5N6P4D7R8T2E2NNKNY7A', $dto->shipmentPublicId);
    }
}
