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
     * @var string
     */
    private $waLink;

    /**
     * @var int
     */
    private $expiresIn;

    /**
     * @var VerificationMode
     */
    private $mode;

    /**
     * @param string           $requestId
     * @param string           $uniqueCode
     * @param string           $waLink
     * @param int              $expiresIn
     * @param VerificationMode $mode
     */
    public function __construct(
        $requestId,
        $uniqueCode,
        $waLink,
        $expiresIn,
        VerificationMode $mode
    ) {
        $this->requestId = $requestId;
        $this->uniqueCode = $uniqueCode;
        $this->waLink = $waLink;
        $this->expiresIn = $expiresIn;
        $this->mode = $mode;
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
     * @return string
     */
    public function getWaLink()
    {
        return $this->waLink;
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
        foreach (array('request_id', 'unique_code', 'wa_link', 'expires_in', 'mode') as $required) {
            if (!array_key_exists($required, $data)) {
                throw new LessOTPException("LessOTP response missing '$required'.");
            }
        }
        return new self(
            (string) $data['request_id'],
            (string) $data['unique_code'],
            (string) $data['wa_link'],
            (int) $data['expires_in'],
            VerificationMode::from((string) $data['mode'])
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return array(
            'requestId' => $this->requestId,
            'uniqueCode' => $this->uniqueCode,
            'waLink' => $this->waLink,
            'expiresIn' => $this->expiresIn,
            'mode' => $this->mode->value(),
        );
    }
}
