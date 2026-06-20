<?php

declare(strict_types=1);

namespace LessOTP\Sdk;

/**
 * Canonical representation of a `verification.success` webhook payload.
 */
final class VerificationSuccess
{
    /**
     * @var string
     */
    private $event;

    /**
     * @var string
     */
    private $requestId;

    /**
     * @var string
     */
    private $phoneNumber;

    /**
     * @var string|null
     */
    private $timestamp;

    /**
     * @param string      $event
     * @param string      $requestId
     * @param string      $phoneNumber
     * @param string|null $timestamp
     */
    public function __construct(
        $event,
        $requestId,
        $phoneNumber,
        $timestamp = null
    ) {
        $this->event = $event;
        $this->requestId = $requestId;
        $this->phoneNumber = $phoneNumber;
        $this->timestamp = $timestamp;
    }

    /**
     * @return string
     */
    public function getEvent()
    {
        return $this->event;
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
    public function getPhoneNumber()
    {
        return $this->phoneNumber;
    }

    /**
     * @return string|null
     */
    public function getTimestamp()
    {
        return $this->timestamp;
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
        return new self(
            $event,
            (string) $body['request_id'],
            (string) $body['phone_number'],
            isset($body['timestamp']) ? (string) $body['timestamp'] : null
        );
    }
}
