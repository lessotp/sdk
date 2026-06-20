<?php

declare(strict_types=1);

namespace LessOTP\Sdk\Tests;

use PHPUnit\Framework\TestCase;
use LessOTP\Sdk\LessOTPException;
use LessOTP\Sdk\VerificationSuccess;

final class VerificationSuccessTest extends TestCase
{
    public function testParsesValidPayload(): void
    {
        $payload = array(
            'event' => 'verification.success',
            'request_id' => 'req_42',
            'phone_number' => '6281234567890',
            'timestamp' => '2026-06-20T10:00:00Z',
        );
        $vs = VerificationSuccess::fromArray($payload);

        self::assertSame('verification.success', $vs->getEvent());
        self::assertSame('req_42', $vs->getRequestId());
        self::assertSame('6281234567890', $vs->getPhoneNumber());
        self::assertSame('2026-06-20T10:00:00Z', $vs->getTimestamp());
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
