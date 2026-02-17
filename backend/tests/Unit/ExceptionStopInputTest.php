<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Dto\Driver\ExceptionStopInput;
use PHPUnit\Framework\TestCase;

final class ExceptionStopInputTest extends TestCase
{
    public function testFromArrayMapsExpectedFields(): void
    {
        $dto = ExceptionStopInput::fromArray([
            'client_action_id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'reason' => 'ABSENT',
            'comment' => 'Cliente ausente',
        ]);

        self::assertSame('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', $dto->clientActionId);
        self::assertSame('ABSENT', $dto->reason);
        self::assertSame('Cliente ausente', $dto->comment);
        self::assertNull($dto->shipmentId);
    }
}
