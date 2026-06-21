<?php

declare(strict_types=1);

namespace LessOTP\Sdk;

/**
 * Inbound authentication channel supported by LessOTP.
 *
 * - `whatsapp`: WhatsApp phone verification via `/START {code}`.
 * - `telegram`: Telegram phone verification via `/start {code}` plus the
 *               official Telegram **Share phone number** button.
 */
final class VerificationChannel
{
    public const WHATSAPP = 'whatsapp';
    public const TELEGRAM = 'telegram';

    /**
     * @var string
     */
    private $value;

    /**
     * @param string $value
     * @throws LessOTPException
     */
    public function __construct($value)
    {
        if ($value !== self::WHATSAPP && $value !== self::TELEGRAM) {
            throw new LessOTPException("LessOTP channel must be 'whatsapp' or 'telegram'.");
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
     * @throws LessOTPException
     */
    public static function from($value)
    {
        return new self($value);
    }

    /**
     * @return self
     */
    public static function whatsapp()
    {
        return new self(self::WHATSAPP);
    }

    /**
     * @return self
     */
    public static function telegram()
    {
        return new self(self::TELEGRAM);
    }
}
