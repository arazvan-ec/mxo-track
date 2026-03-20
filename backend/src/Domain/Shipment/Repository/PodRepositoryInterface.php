<?php

declare(strict_types=1);

namespace App\Domain\Shipment\Repository;

use App\Domain\Shipment\Model\Pod;

interface PodRepositoryInterface
{
    public function findOneByPublicId(string $publicId): ?Pod;

    public function save(Pod $pod): void;

    public function flush(): void;
}
