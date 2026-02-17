<?php

declare(strict_types=1);

use App\Kernel;

if (!is_dir(dirname(__DIR__).'/vendor')) {
    throw new RuntimeException('Dependencies are missing. Run "composer install" in backend/.');
}

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context): Kernel {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
