<?php

declare(strict_types=1);

namespace App\Service;

use DateTimeImmutable;

final class DeliveryEvidenceFactory
{
    public function build(
        string $recipientIdEncoded,
        bool $confirmedByDriver,
        string $routeStopId,
        string $clientActionId,
        string $driverUserId,
        string $driverIp,
        string $driverUserAgent,
    ): array {
        $now = new DateTimeImmutable();
        $bucket = $now->format('Y-m-d\\TH:i');

        $actionFingerprint = hash('sha256', implode('|', [
            $routeStopId,
            $clientActionId,
            $driverUserId,
            $bucket,
        ]));

        return [
            'confirmation_mode' => 'recipient_id_encoded',
            'recipient_id_sha256' => hash('sha256', $recipientIdEncoded),
            'confirmed_by_driver' => $confirmedByDriver,
            'confirmed_at' => $now->format(DATE_ATOM),
            'driver_ip' => $driverIp,
            'driver_user_agent' => $driverUserAgent,
            'action_fingerprint' => $actionFingerprint,
            'fingerprint_bucket' => $bucket,
        ];
    }
}
