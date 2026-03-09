<?php

declare(strict_types=1);

namespace App\Application\Delivery;

final class DriverConfirmationRequiredException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Driver must explicitly confirm the delivery.');
    }
}
