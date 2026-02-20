<?php

declare(strict_types=1);

namespace App\Service;

class DatabasePodStorage implements PodStorageInterface
{
    public function storeSignature(string $base64): string
    {
        return $base64;
    }

    public function loadSignature(string $reference): string
    {
        return $reference;
    }
}
