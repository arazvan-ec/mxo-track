<?php

declare(strict_types=1);

namespace App\Application\Route;

final class InspectionNotCompletedException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Debe completar la inspección del vehículo.');
    }
}
