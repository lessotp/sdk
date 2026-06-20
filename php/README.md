# LessOTP PHP SDK

Client for the **LessOTP Inbound WhatsApp Authentication API** (PHP 7.4+).

## Install

```bash
composer require lessotp/sdk
```

## Usage

```php
use LessOTP\Sdk\Client;
use LessOTP\Sdk\VerifyWebhookSignature;

// production (default)
$client = new Client(getenv('LESSOTP_API_KEY'), 'https://api.lessotp.com');

// staging
$staging = new Client(
    getenv('LESSOTP_STAGING_API_KEY'),
    'https://api.lessotp.com',
    null,
    10,
    'staging'
);

// strict
$result = $client->authRequest('6281234567890');
echo $result->getRequestId(), PHP_EOL;

// frictionless
$result = $client->authRequest();

// per-call override
$result = $client->authRequest('6281234567890', 'staging');

// webhook verification
$raw = file_get_contents('php://input');
$sig = $_SERVER['HTTP_X_SIGNATURE'] ?? '';

if (!VerifyWebhookSignature::hmac($raw, $sig, getenv('LESSOTP_WEBHOOK_SECRET'))) {
    http_response_code(403);
    exit;
}
```

## API

### `new Client($apiKey, $baseUrl = 'https://api.lessotp.com', $http = null, $timeoutSeconds = 10, $environment = 'production')`

| Parameter | Default | Description |
| --- | --- | --- |
| `$apiKey` | required | App API key. |
| `$baseUrl` | `https://api.lessotp.com` | API host. |
| `$http` | `null` | Optional `GuzzleHttp\Client`. |
| `$timeoutSeconds` | `10` | HTTP timeout. |
| `$environment` | `'production'` | `'production'` or `'staging'`. |

### `$client->authRequest($phoneNumber = null, $environment = null): AuthRequestResult`

Calls the endpoint selected by `$environment`. The per-call `$environment` overrides the client environment.

### `VerifyWebhookSignature::hmac($rawBody, $signatureHeader, $secret): bool`

Constant-time HMAC-SHA256 verification. Accepts raw hex and `sha256=` prefixed values.

## Errors

Throws `LessOTP\Sdk\LessOTPException` on transport, auth, or payload problems.

## Tests

```bash
composer install
composer test
```
