<?php

declare(strict_types=1);

namespace LessOTP\Sdk;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

/**
 * LessOTP API client.
 *
 * Talks to {@code POST /api/v1/auth/request} (production) and
 * {@code POST /api/v1/staging/auth/request} (staging), returning a
 * strongly-typed {@see AuthRequestResult}. Stateless.
 *
 * Supported channels:
 * - WhatsApp — default; presence of `phone_number` toggles strict mode.
 * - Telegram — user shares their phone via the official Telegram contact button.
 */
final class Client
{
    /**
     * @var string
     */
    private $apiKey;

    /**
     * @var string
     */
    private $baseUrl;

    /**
     * @var HttpClient|null
     */
    private $http;

    /**
     * @var int
     */
    private $timeoutSeconds;

    /**
     * @var string
     */
    private $environment;

    /**
     * @param string             $apiKey
     * @param string             $environment production or staging
     * @param string             $baseUrl
     * @param int                $timeoutSeconds
     * @param HttpClient|null    $http
     */
    public function __construct(
        $apiKey,
        $environment = 'production',
        $baseUrl = 'https://api.lessotp.com',
        $timeoutSeconds = 10,
        $http = null
    ) {
        $this->apiKey = $apiKey;
        $this->environment = self::resolveEnvironment($environment);
        $this->baseUrl = $baseUrl;
        $this->timeoutSeconds = $timeoutSeconds;
        $this->http = $http;
    }

    /**
     * Create a WhatsApp verification request (backward-compatible).
     *
     * Endpoint is selected from the client environment. Pass `$environment`
     * to override for one call.
     *
     * @param  string|null $phoneNumber  E.164-like number without `+` (e.g. `6281234567890`).
     * @param  string|null $environment production or staging
     * @return AuthRequestResult
     * @throws LessOTPException
     */
    public function authRequest($phoneNumber = null, $environment = null)
    {
        return $this->requestAuth(VerificationChannel::whatsapp(), $phoneNumber, $environment);
    }

    /**
     * Create a Telegram verification request.
     *
     * Strict if `$phoneNumber` is supplied: the user's shared contact must
     * match this number. Frictionless if `$phoneNumber` is `null`: the
     * resolved phone from the user's contact share will be returned on the
     * outbound webhook.
     *
     * @param  string|null $phoneNumber
     * @param  string|null $environment production or staging
     * @return AuthRequestResult
     * @throws LessOTPException
     */
    public function requestTelegramAuth($phoneNumber = null, $environment = null)
    {
        return $this->requestAuth(VerificationChannel::telegram(), $phoneNumber, $environment);
    }

    /**
     * Multi-channel request method.
     *
     * @param  VerificationChannel $channel
     * @param  string|null         $phoneNumber
     * @param  string|null         $environment production or staging
     * @return AuthRequestResult
     * @throws LessOTPException
     */
    public function requestAuth(VerificationChannel $channel, $phoneNumber = null, $environment = null)
    {
        $path = $this->endpointFor($environment);
        return $this->performAuthRequest($path, $channel, $phoneNumber);
    }

    /**
     * @param  string             $path
     * @param  VerificationChannel $channel
     * @param  string|null        $phoneNumber
     * @return AuthRequestResult
     * @throws LessOTPException
     */
    private function performAuthRequest($path, VerificationChannel $channel, $phoneNumber)
    {
        $body = array('channel' => $channel->value());
        if ($phoneNumber !== null && $phoneNumber !== '') {
            $body['phone_number'] = $phoneNumber;
        }
        try {
            $response = $this->httpClient()->post($this->endpoint($path), array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ),
                'json' => $body,
                'timeout' => $this->timeoutSeconds,
            ));
        } catch (RequestException $e) {
            throw new LessOTPException(
                'LessOTP authRequest failed: '
                    . ($e->getResponse() ? $e->getResponse()->getStatusCode() : 'network error'),
                0,
                $e
            );
        } catch (GuzzleException $e) {
            throw new LessOTPException(
                'LessOTP authRequest transport error: ' . $e->getMessage(),
                0,
                $e
            );
        }

        return AuthRequestResult::fromResponse(self::jsonBody($response));
    }

    /**
     * @param  string|null $environment
     * @return string
     * @throws LessOTPException
     */
    private function endpointFor($environment)
    {
        $resolved = self::resolveEnvironment($environment === null ? $this->environment : $environment);
        if ($resolved === 'production') {
            return '/api/v1/auth/request';
        }
        return '/api/v1/staging/auth/request';
    }

    /**
     * @param  string|null $environment
     * @return string
     * @throws LessOTPException
     */
    private static function resolveEnvironment($environment)
    {
        if ($environment === null || $environment === '') {
            return 'production';
        }
        if ($environment === 'production' || $environment === 'staging') {
            return $environment;
        }
        throw new LessOTPException("LessOTP environment must be 'production' or 'staging'.");
    }

    /**
     * @return HttpClient
     */
    private function httpClient()
    {
        if ($this->http !== null) {
            return $this->http;
        }
        return new HttpClient(array(
            'base_uri' => rtrim($this->baseUrl, '/'),
            'headers' => array(
                'User-Agent' => 'lessotp-sdk-php/0.2.0',
            ),
        ));
    }

    /**
     * @param  string $path
     * @return string
     */
    private function endpoint($path)
    {
        return rtrim($this->baseUrl, '/') . $path;
    }

    /**
     * @param  ResponseInterface $response
     * @return array<string, mixed>
     * @throws LessOTPException
     */
    private static function jsonBody(ResponseInterface $response)
    {
        $decoded = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new LessOTPException('LessOTP response was not a JSON object.');
        }
        return $decoded;
    }
}
