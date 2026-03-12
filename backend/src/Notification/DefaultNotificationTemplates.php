<?php

declare(strict_types=1);

namespace App\Notification;

use App\Enum\NotificationChannel;
use App\Enum\NotificationTriggerType;

final class DefaultNotificationTemplates
{
    /** @var array<string, array<string, string>> */
    private const TEMPLATES = [
        'reminder' => [
            'sms' => 'Hola {recipient_name}, mañana recibirás tu envío entre {time_window}. Sigue tu entrega: {tracking_url}',
            'whatsapp' => 'Hola {recipient_name}, mañana tienes una entrega programada entre {time_window}. Puedes ver los detalles aquí: {tracking_url}',
        ],
        'presence_check' => [
            'sms' => '{recipient_name}, tu envío llega en ~{eta}. ¿Estarás? Confirma: {tracking_url}',
            'whatsapp' => '{recipient_name}, tu entrega está cerca (~{eta}). ¿Estarás para recibirla? Confirma aquí: {tracking_url}',
        ],
        'delivered' => [
            'sms' => 'Tu envío fue entregado. Detalles: {tracking_url}',
            'whatsapp' => 'Tu envío fue entregado correctamente. Ver detalles: {tracking_url}',
        ],
        'delivery_exception' => [
            'sms' => 'No pudimos entregar tu envío. Reprograma aquí: {tracking_url}',
            'whatsapp' => 'No pudimos completar tu entrega. Puedes reprogramar o elegir alternativas aquí: {tracking_url}',
        ],
        'eta_change' => [
            'sms' => 'Tu entrega ahora llega a las ~{eta}. Seguimiento: {tracking_url}',
            'whatsapp' => 'Actualización: tu entrega ahora llega aproximadamente a las {eta}. Sigue en tiempo real: {tracking_url}',
        ],
        'out_for_delivery' => [
            'sms' => '{recipient_name}, tu envío va en camino. Sigue la entrega: {tracking_url}',
            'whatsapp' => '{recipient_name}, tu envío ya salió a reparto. Sigue tu entrega en tiempo real: {tracking_url}',
        ],
    ];

    public static function resolve(
        NotificationTriggerType $trigger,
        NotificationChannel $channel,
        ?string $customTemplate,
    ): string {
        return $customTemplate ?? self::TEMPLATES[$trigger->value][$channel->value];
    }
}
