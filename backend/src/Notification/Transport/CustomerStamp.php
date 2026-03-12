<?php

declare(strict_types=1);

namespace App\Notification\Transport;

use Symfony\Component\Messenger\Stamp\StampInterface;

final class CustomerStamp implements StampInterface
{
    public function __construct(
        public readonly ?int $customerId,
    ) {
    }
}
