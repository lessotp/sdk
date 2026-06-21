<?php

declare(strict_types=1);

namespace LessOTP\Sdk;

/**
 * Canonical representation of a `verification.success` webhook payload.
 */
final class VerificationSuccess
{
    /** @var string */
    private $event;

    /** @var VerificationChannel */
    private $channel;

    /** @var string */
    private $requestId;

    /** @var string */
    private $phoneNumber;

    /** @var string|null */
    private $telegramUserId;

    /** @var string|null */
    private $telegramUsername;

    /** @var string|null */
    private $timestamp;

    /**
     * @param string              $event
     * @param VerificationChannel $channel
     * @param string              $requestId
     * @param string              $phoneNumber
     * @param string|null         $timestamp
     * @param string|null         $telegramUserId
     * @param string|null         $telegramUsername
     */
    public function __construct(
        $event,
        VerificationChannel $channel,
        $requestId,
        $phoneNumber,
        $timestamp = null,
        $telegramUserId = null,
        $telegramUsername = null
    ) {
        $this->event = $event;
        $this->channel = $channel;
        $this->requestId = $requestId;
        $this->phoneNumber = $phoneNumber;
        $this->timestamp = $timestamp;
        $this->telegramUserId = $telegramUserId;
        $this->telegramUsername = $telegramUsername;
    }

    /** @return string */
    public function getEvent()
    {
        return $this->event;
    }

    /** @return VerificationChannel */
    public function getChannel()
    {
        return $this->channel;
    }

    /** @return string */
    public function getRequestId()
    {
        return $this->requestId;
    }

    /** @return string */
    public function getPhoneNumber()
    {
        return $this->phoneNumber;
    }

    /** @return string|null */
    public function getTimestamp()
    {
        return $this->timestamp;
    }

    /** @return string|null */
    public function getTelegramUserId()
    {
        return $this->telegramUserId;
    }

    /** @return string|null */
    public function getTelegramUsername()
    {
        return $this->telegramUsername;
    }

    /**
     * @param array<string, mixed> $body
     * @return self
     * @throws LessOTPException
     */
    public static function fromArray(array $body)
    {
        $event = isset($body['event']) ? (string) $body['event'] : '';
        if ($event !== 'verification.success') {
            throw new LessOTPException("Unexpected webhook event: '$event'.");
        }
        foreach (array('request_id', 'phone_number') as $required) {
            if (!isset($body[$required]) || !is_string($body[$required])) {
                throw new LessOTPException("Webhook payload missing '$required'.");
            }
        }
        $channel = VerificationChannel::from(isset($body['channel']) ? (string) $body['channel'] : VerificationChannel::WHATSAPP);
        return new self(
            $event,
            $channel,
            (string) $body['request_id'],
            (string) $body['phone_number'],
            isset($body['timestamp']) ? (string) $body['timestamp'] : null,
            isset($body['telegram_user_id']) ? (string) $body['telegram_user_id'] : null,
            isset($body['telegram_username']) ? (string) $body['telegram_username'] : null
        );
    }
}
