<?php

declare(strict_types=1);

namespace App\Application\Delivery;

final class DriverNotOwnerException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Driver does not own this route.');
    }
}
