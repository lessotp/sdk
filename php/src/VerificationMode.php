<?php

declare(strict_types=1);

namespace LessOTP\Sdk;

/**
 * Verification mode reported by LessOTP's auth request API.
 *
 * Implemented as a small class with class constants so the package works on
 * PHP 7.4+ (no `enum` keyword required).
 */
final class VerificationMode
{
    const STRICT = 'strict';
    const FRICTIONLESS = 'frictionless';

    /**
     * @var string
     */
    private $value;

    /**
     * @param string $value one of {@see self::STRICT} or {@see self::FRICTIONLESS}.
     */
    public function __construct($value)
    {
        if ($value !== self::STRICT && $value !== self::FRICTIONLESS) {
            throw new LessOTPException(sprintf(
                "Invalid verification mode '%s'",
                is_string($value) ? $value : gettype($value)
            ));
        }
        $this->value = $value;
    }

    /**
     * @return string
     */
    public function value()
    {
        return $this->value;
    }

    /**
     * @param string $value
     * @return self
     */
    public static function from($value)
    {
        return new self($value);
    }

    public function __toString()
    {
        return $this->value;
    }
}
