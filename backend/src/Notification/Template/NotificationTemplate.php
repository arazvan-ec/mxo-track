<?php

declare(strict_types=1);

namespace App\Notification\Template;

abstract class NotificationTemplate
{
    abstract public function getTemplateName(): string;

    abstract public function getSmsText(): string;

    abstract public function getWhatsAppTemplateName(): string;

    /**
     * @return array<string, string>
     */
    abstract public function getWhatsAppParameters(): array;

    abstract public function getSubject(): string;
}
