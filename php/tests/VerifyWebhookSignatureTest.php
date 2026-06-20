<?php

declare(strict_types=1);

namespace LessOTP\Sdk\Tests;

use PHPUnit\Framework\TestCase;
use LessOTP\Sdk\VerifyWebhookSignature;

final class VerifyWebhookSignatureTest extends TestCase
{
    public function testReturnsTrueForValidHexSignature(): void
    {
        $body = '{"event":"verification.success","request_id":"req_1"}';
        $secret = 'whsec_test';
        $expected = hash_hmac('sha256', $body, $secret);
        self::assertTrue(VerifyWebhookSignature::hmac($body, $expected, $secret));
    }

    public function testAcceptsSha256Prefix(): void
    {
        $body = '{"event":"verification.success","request_id":"req_1"}';
        $secret = 'whsec_test';
        $expected = 'sha256=' . hash_hmac('sha256', $body, $secret);
        self::assertTrue(VerifyWebhookSignature::hmac($body, $expected, $secret));
    }

    public function testRejectsMissingHeader(): void
    {
        self::assertFalse(VerifyWebhookSignature::hmac('{"a":1}', null, 'whsec_test'));
        self::assertFalse(VerifyWebhookSignature::hmac('{"a":1}', '', 'whsec_test'));
    }

    public function testRejectsMismatchedSignature(): void
    {
        self::assertFalse(VerifyWebhookSignature::hmac('{"a":1}', str_repeat('a', 64), 'whsec_test'));
    }

    public function testRejectsNonHexHeader(): void
    {
        self::assertFalse(VerifyWebhookSignature::hmac('{"a":1}', 'not-hex', 'whsec_test'));
        self::assertFalse(VerifyWebhookSignature::hmac('{"a":1}', 'sha256=', 'whsec_test'));
    }

    public function testRejectsEmptySecret(): void
    {
        self::assertFalse(VerifyWebhookSignature::hmac('{"a":1}', str_repeat('a', 64), ''));
    }
}
