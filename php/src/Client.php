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
     * @param string             $baseUrl
     * @param HttpClient|null    $http
     * @param int                $timeoutSeconds
     * @param string             $environment production or staging
     */
    public function __construct(
        $apiKey,
        $baseUrl = 'https://api.lessotp.com',
        $http = null,
        $timeoutSeconds = 10,
        $environment = 'production'
    ) {
        $this->apiKey = $apiKey;
        $this->baseUrl = $baseUrl;
        $this->http = $http;
        $this->timeoutSeconds = $timeoutSeconds;
        $this->environment = self::resolveEnvironment($environment);
    }

    /**
     * Create a verification request.
     *
     * Endpoint is selected from the client environment. Pass `$environment` to
     * override for this call.
     *
     * @param  string|null $phoneNumber  E.164-like number without `+` (e.g. `6281234567890`).
     *                                  When omitted, the API returns a frictionless mode.
     * @param  string|null $environment production or staging
     * @return AuthRequestResult
     * @throws LessOTPException on transport, auth, or validation failure.
     */
    public function authRequest($phoneNumber = null, $environment = null)
    {
        return $this->performAuthRequest($this->endpointFor($environment), $phoneNumber);
    }

    /** Alias of {@see authRequest()}. */
    public function requestAuth($phoneNumber = null, $environment = null)
    {
        return $this->authRequest($phoneNumber, $environment);
    }

    /**
     * @param  string     $path
     * @param  string|null $phoneNumber
     * @return AuthRequestResult
     * @throws LessOTPException
     */
    private function performAuthRequest($path, $phoneNumber)
    {
        try {
            $response = $this->httpClient()->post($this->endpoint($path), [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => $phoneNumber === null
                    ? new \stdClass()
                    : array('phone_number' => $phoneNumber),
                'timeout' => $this->timeoutSeconds,
            ]);
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
                'User-Agent' => 'lessotp-sdk-php/0.1.0',
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
