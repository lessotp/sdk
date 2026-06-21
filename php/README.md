# LessOTP PHP SDK

Client for the **LessOTP Inbound Phone Authentication API** (PHP 7.4+).

Supported channels:

- WhatsApp — inbound `/START {code}` phone verification.
- Telegram — bot `/start {code}` plus the official **Share phone number** button.

## Install

```bash
composer require lessotp/sdk
```

## Usage

```php
use LessOTP\Sdk\Client;
use LessOTP\Sdk\VerificationChannel;
use LessOTP\Sdk\VerifyWebhookSignature;

// production (default)
$client = new Client(getenv('LESSOTP_API_KEY'), 'production', 'https://api.lessotp.com');

// staging
$staging = new Client(
    getenv('LESSOTP_STAGING_API_KEY'),
    'staging',
    'https://api.lessotp.com'
);

// WhatsApp strict (legacy-compatible)
$result = $client->authRequest('6281234567890');
echo $result->getChannel()->value(), $result->getWaLink();

// WhatsApp frictionless (legacy-compatible)
$result = $client->authRequest();

// Telegram strict
$result = $client->requestTelegramAuth('6281234567890');
echo $result->getTelegramLink(), PHP_EOL;
echo $result->getTelegramText(), PHP_EOL;

// Telegram frictionless
$result = $client->requestAuth(VerificationChannel::telegram());

// per-call override
$result = $client->requestTelegramAuth('6281234567890', 'staging');

// webhook verification
$raw = file_get_contents('php://input');
$sig = $_SERVER['HTTP_X_SIGNATURE'] ?? '';

if (!VerifyWebhookSignature::hmac($raw, $sig, getenv('LESSOTP_WEBHOOK_SECRET'))) {
    http_response_code(403);
    exit;
}
```

## API

### `new Client($apiKey, $environment = 'production', $baseUrl = 'https://api.lessotp.com', $timeoutSeconds = 10, $http = null)`

Parameters follow the same order as the Go SDK: apiKey → environment → baseUrl → timeout → custom transport.

| Parameter | Default | Description |
| --- | --- | --- |
| `$apiKey` | required | App API key. |
| `$environment` | `'production'` | `'production'` or `'staging'`. |
| `$baseUrl` | `https://api.lessotp.com` | API host. |
| `$timeoutSeconds` | `10` | HTTP timeout. |
| `$http` | `null` | Optional `GuzzleHttp\Client`. |

### `$client->authRequest($phoneNumber = null, $environment = null): AuthRequestResult`

Legacy WhatsApp convenience method. The per-call `$environment` overrides the client environment.

### `$client->requestTelegramAuth($phoneNumber = null, $environment = null): AuthRequestResult`

Telegram short-hand. Strict when `$phoneNumber` is supplied; frictionless when omitted.

### `$client->requestAuth(VerificationChannel $channel, $phoneNumber = null, $environment = null): AuthRequestResult`

Multi-channel call. Use `VerificationChannel::whatsapp()` or `VerificationChannel::telegram()`.

The normalized result includes:

- `getChannel()` — the resolved channel.
- `getWaLink()` — only when channel is `whatsapp`.
- `getTelegramLink()` / `getTelegramText()` — only when channel is `telegram`.

### `VerificationSuccess::fromArray(array $body): VerificationSuccess`

Canonical representation of a `verification.success` webhook payload. Includes `getChannel()` and `getTelegramUserId()` / `getTelegramUsername()` when channel is `telegram`.

### `VerifyWebhookSignature::hmac($rawBody, $signatureHeader, $secret): bool`

Constant-time HMAC-SHA256 verification. Accepts raw hex and `sha256=` prefixed values.

## Errors

Throws `LessOTP\Sdk\LessOTPException` on transport, auth, or payload problems.

## Tests

```bash
composer install
composer test
```
