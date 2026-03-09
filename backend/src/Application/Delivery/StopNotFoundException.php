<?php

declare(strict_types=1);

namespace App\Application\Delivery;

final class StopNotFoundException extends \RuntimeException
{
    public function __construct(string $stopPublicId)
    {
        parent::__construct(sprintf('Stop "%s" not found.', $stopPublicId));
    }
}
