<?php

declare(strict_types=1);

namespace App\Service;

interface PodStorageInterface
{
    public function storeSignature(string $base64): string;
    public function loadSignature(string $reference): string;
}
