<?php

declare(strict_types=1);

namespace App\Notification\Channel;

use App\Notification\Template\NotificationTemplate;

interface NotificationChannelInterface
{
    public function send(string $recipient, NotificationTemplate $template): bool;

    public function supports(string $channelType): bool;
}
