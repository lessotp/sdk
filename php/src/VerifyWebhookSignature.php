<?php

declare(strict_types=1);

namespace LessOTP\Sdk;

/**
 * Stateless HMAC-SHA256 verifier for client webhook payloads.
 *
 * LessOTP sends a `X-Signature` header containing a hex-encoded HMAC SHA256
 * over the raw JSON request body, computed with the App's
 * `webhookSigningSecret`.
 */
final class VerifyWebhookSignature
{
    /**
     * Constant-time HMAC SHA256 compare.
     *
     * Always returns a boolean. Never throws on bad header shape — callers
     * should treat false as "verification failed" without surfacing details.
     *
     * @param  string      $rawBody
     * @param  string|null $signatureHeader
     * @param  string      $secret
     * @return bool
     */
    public static function hmac($rawBody, $signatureHeader, $secret)
    {
        if ($signatureHeader === null || $signatureHeader === '' || $secret === '') {
            return false;
        }

        $stripped = preg_replace('/^sha256=/i', '', trim($signatureHeader));
        if ($stripped === '' || !ctype_xdigit($stripped)) {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($expected, $stripped);
    }

    /** Alias accepted by callers used to either name. */
    public static function verify($rawBody, $signatureHeader, $secret)
    {
        return self::hmac($rawBody, $signatureHeader, $secret);
    }
}
