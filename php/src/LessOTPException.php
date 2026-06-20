<?php

declare(strict_types=1);

namespace LessOTP\Sdk;

use RuntimeException;

/**
 * Surface error type for SDK consumers.
 *
 * Always wraps a stable, sanitized message; original transport errors are
 * attached as `previous` for debugging.
 */
final class LessOTPException extends RuntimeException
{
}
