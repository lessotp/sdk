<?php

declare(strict_types=1);

namespace LessOTP\Sdk\Tests;

use PHPUnit\Framework\TestCase;
use LessOTP\Sdk\LessOTPException;
use LessOTP\Sdk\VerificationChannel;
use LessOTP\Sdk\VerificationSuccess;

final class VerificationSuccessTest extends TestCase
{
    public function testParsesValidPayload(): void
    {
        $payload = array(
            'event' => 'verification.success',
            'channel' => 'whatsapp',
            'request_id' => 'req_42',
            'phone_number' => '6281234567890',
            'timestamp' => '2026-06-20T10:00:00Z',
        );
        $vs = VerificationSuccess::fromArray($payload);

        self::assertSame('verification.success', $vs->getEvent());
        self::assertSame(VerificationChannel::WHATSAPP, $vs->getChannel()->value());
        self::assertSame('req_42', $vs->getRequestId());
        self::assertSame('6281234567890', $vs->getPhoneNumber());
        self::assertSame('2026-06-20T10:00:00Z', $vs->getTimestamp());
    }

    public function testParsesTelegramPayload(): void
    {
        $payload = array(
            'event' => 'verification.success',
            'channel' => 'telegram',
            'request_id' => 'req_tg',
            'phone_number' => '6281234567890',
            'telegram_user_id' => '123456789',
            'telegram_username' => 'fajarbc',
            'timestamp' => '2026-06-21T10:00:00Z',
        );
        $vs = VerificationSuccess::fromArray($payload);

        self::assertSame(VerificationChannel::TELEGRAM, $vs->getChannel()->value());
        self::assertSame('123456789', $vs->getTelegramUserId());
        self::assertSame('fajarbc', $vs->getTelegramUsername());
    }

    public function testDefaultsToWhatsAppWhenChannelMissing(): void
    {
        $vs = VerificationSuccess::fromArray(array(
            'event' => 'verification.success',
            'request_id' => 'req_legacy',
            'phone_number' => '6281234567890',
        ));
        self::assertSame(VerificationChannel::WHATSAPP, $vs->getChannel()->value());
    }

    public function testRejectsUnknownEvent(): void
    {
        $this->expectException(LessOTPException::class);
        VerificationSuccess::fromArray(array(
            'event' => 'verification.failed',
            'request_id' => 'req_1',
            'phone_number' => '6281234567890',
        ));
    }

    public function testRejectsMissingFields(): void
    {
        $this->expectException(LessOTPException::class);
        VerificationSuccess::fromArray(array(
            'event' => 'verification.success',
            'phone_number' => '6281234567890',
        ));
    }
}
