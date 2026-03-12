<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification\Transport;

use App\Notification\Transport\NullSmsTransport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;

#[CoversClass(NullSmsTransport::class)]
final class NullSmsTransportTest extends TestCase
{
    #[Test]
    public function send_returns_sent_message(): void
    {
        $transport = new NullSmsTransport(new NullLogger());
        $sms = new SmsMessage('+34600000000', 'Hello world');

        $result = $transport->send($sms);

        self::assertInstanceOf(SentMessage::class, $result);
    }

    #[Test]
    public function supports_sms_message(): void
    {
        $transport = new NullSmsTransport();

        self::assertTrue($transport->supports(new SmsMessage('+34600000000', 'test')));
    }

    #[Test]
    public function does_not_support_chat_message(): void
    {
        $transport = new NullSmsTransport();

        self::assertFalse($transport->supports(new ChatMessage('test')));
    }

    #[Test]
    public function to_string_returns_null(): void
    {
        $transport = new NullSmsTransport();

        self::assertSame('null', (string) $transport);
    }
}
