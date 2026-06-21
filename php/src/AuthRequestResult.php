<?php

declare(strict_types=1);

namespace LessOTP\Sdk;

/**
 * Verified, normalized response from `POST /api/v1/auth/request`.
 *
 * The wire format uses snake_case; this object uses camelCase to match
 * PSR-12 / IDE conventions. Use {@see fromResponse()} for the mapping.
 */
final class AuthRequestResult
{
    /**
     * @var string
     */
    private $requestId;

    /**
     * @var string
     */
    private $uniqueCode;

    /**
     * @var VerificationChannel
     */
    private $channel;

    /**
     * @var string|null
     */
    private $waLink;

    /**
     * @var string|null
     */
    private $telegramLink;

    /**
     * @var string|null
     */
    private $telegramText;

    /**
     * @var int
     */
    private $expiresIn;

    /**
     * @var VerificationMode
     */
    private $mode;

    /**
     * @param string             $requestId
     * @param string             $uniqueCode
     * @param VerificationChannel $channel
     * @param int                $expiresIn
     * @param VerificationMode   $mode
     * @param string|null        $waLink
     * @param string|null        $telegramLink
     * @param string|null        $telegramText
     */
    public function __construct(
        $requestId,
        $uniqueCode,
        VerificationChannel $channel,
        $expiresIn,
        VerificationMode $mode,
        $waLink = null,
        $telegramLink = null,
        $telegramText = null
    ) {
        $this->requestId = $requestId;
        $this->uniqueCode = $uniqueCode;
        $this->channel = $channel;
        $this->expiresIn = $expiresIn;
        $this->mode = $mode;
        $this->waLink = $waLink;
        $this->telegramLink = $telegramLink;
        $this->telegramText = $telegramText;
    }

    /**
     * @return string
     */
    public function getRequestId()
    {
        return $this->requestId;
    }

    /**
     * @return string
     */
    public function getUniqueCode()
    {
        return $this->uniqueCode;
    }

    /**
     * @return VerificationChannel
     */
    public function getChannel()
    {
        return $this->channel;
    }

    /**
     * @return int
     */
    public function getExpiresIn()
    {
        return $this->expiresIn;
    }

    /**
     * @return VerificationMode
     */
    public function getMode()
    {
        return $this->mode;
    }

    /**
     * @return string|null
     */
    public function getWaLink()
    {
        return $this->waLink;
    }

    /**
     * @return string|null
     */
    public function getTelegramLink()
    {
        return $this->telegramLink;
    }

    /**
     * @return string|null
     */
    public function getTelegramText()
    {
        return $this->telegramText;
    }

    /**
     * Backward-compatible constructor invocation: legacy callers may still
     * pass a `wa_link` style positional argument list. When upgrading, prefer
     * {@see AuthRequestWhatsAppResult::fromResponse()} or
     * {@see AuthRequestTelegramResult::fromResponse()}.
     *
     * @param array<string, mixed> $body
     * @return self
     * @throws LessOTPException
     */
    public static function fromResponse(array $body)
    {
        $data = isset($body['data']) && is_array($body['data']) ? $body['data'] : null;
        if (!is_array($data)) {
            throw new LessOTPException('LessOTP response missing "data" object.');
        }
        foreach (array('request_id', 'unique_code', 'expires_in', 'mode', 'channel') as $required) {
            if (!array_key_exists($required, $data)) {
                throw new LessOTPException("LessOTP response missing '$required'.");
            }
        }
        $channel = VerificationChannel::from((string) $data['channel']);
        if ($channel->value() === VerificationChannel::TELEGRAM) {
            return new self(
                (string) $data['request_id'],
                (string) $data['unique_code'],
                $channel,
                (int) $data['expires_in'],
                VerificationMode::from((string) $data['mode']),
                null,
                isset($data['telegram_link']) ? (string) $data['telegram_link'] : null,
                isset($data['telegram_text']) ? (string) $data['telegram_text'] : null
            );
        }
        return new self(
            (string) $data['request_id'],
            (string) $data['unique_code'],
            $channel,
            (int) $data['expires_in'],
            VerificationMode::from((string) $data['mode']),
            isset($data['wa_link']) ? (string) $data['wa_link'] : null
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        $out = array(
            'requestId' => $this->requestId,
            'uniqueCode' => $this->uniqueCode,
            'channel' => $this->channel->value(),
            'expiresIn' => $this->expiresIn,
            'mode' => $this->mode->value(),
        );
        if ($this->waLink !== null) {
            $out['waLink'] = $this->waLink;
        }
        if ($this->telegramLink !== null) {
            $out['telegramLink'] = $this->telegramLink;
        }
        if ($this->telegramText !== null) {
            $out['telegramText'] = $this->telegramText;
        }
        return $out;
    }
}
