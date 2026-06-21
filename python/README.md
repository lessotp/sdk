# LessOTP Python SDK

Client for the **LessOTP Inbound Phone Authentication API** (Python 3.8+).

Supported channels:

- WhatsApp — inbound `/START {code}` phone verification.
- Telegram — bot `/start {code}` plus the official **Share phone number** button.

## Install

```bash
pip install lessotp-sdk
```

## Usage

```python
import os
from lessotp_sdk import LessOTPClient, parse_verified_webhook

# production (default)
client = LessOTPClient(api_key=os.environ["LESSOTP_API_KEY"])

# staging
staging = LessOTPClient(
    api_key=os.environ["LESSOTP_STAGING_API_KEY"],
    environment="staging",
)

# WhatsApp strict (legacy-compatible)
whatsapp_strict = client.auth_request("6281234567890")
print(whatsapp_strict.channel, whatsapp_strict.wa_link)

# WhatsApp frictionless (legacy-compatible)
whatsapp_frictionless = client.auth_request()

# Telegram strict
telegram_strict = client.request_telegram_auth("6281234567890")
print(telegram_strict.telegram_link, telegram_strict.telegram_text)

# Telegram frictionless: user shares phone via Telegram contact button
telegram_frictionless = client.request_auth(channel="telegram")

# per-call override
one_off = client.request_telegram_auth("6281234567890", environment="staging")

# webhook verification
event = parse_verified_webhook(
    request.get_data(as_text=True),
    request.headers.get("X-Signature"),
    os.environ["LESSOTP_WEBHOOK_SECRET"],
)
if event is None:
    return "bad signature", 403
print(event.channel, event.request_id, event.phone_number)
if event.channel == "telegram":
    print(event.telegram_user_id, event.telegram_username)
```

## API

### `LessOTPClient(api_key, environment='production', base_url='https://api.lessotp.com', timeout_seconds=10)`

Parameters follow the same order as the Go SDK: api_key → environment → base_url → timeout.

| Option | Default | Description |
| --- | --- | --- |
| `api_key` | required | App API key. |
| `environment` | `"production"` | `"production"` or `"staging"`. |
| `base_url` | `https://api.lessotp.com` | API host. |
| `timeout_seconds` | `10` | HTTP timeout. |

### `client.auth_request(phone_number=None, environment=None) -> AuthRequestResult`

WhatsApp convenience method (legacy-compatible).

### `client.request_telegram_auth(phone_number=None, environment=None) -> AuthRequestResult`

Telegram short-hand. Strict when `phone_number` is supplied; frictionless when omitted.

### `client.request_auth(channel='whatsapp', phone_number=None, environment=None) -> AuthRequestResult`

Multi-channel call. `channel` must be `'whatsapp'` or `'telegram'`.

Telegram note: LessOTP only accepts phone numbers shared through Telegram's official contact-sharing button. The platform verifies the shared contact belongs to the Telegram sender.

### `verify_webhook_signature(raw_body, signature_header, secret) -> bool`

Constant-time HMAC-SHA256 verification. Accepts raw hex and `sha256=` prefixed values.

### `parse_verified_webhook(raw_body, signature_header, secret) -> VerificationSuccess | None`

Returns the parsed payload if the signature is valid; `None` otherwise.

`VerificationSuccess` includes a `channel` field (`'whatsapp'` or `'telegram'`). Telegram payloads also include `telegram_user_id` and `telegram_username`.

## Errors

Raises `LessOTPError` on transport, auth, or payload problems.

## Tests

```bash
python -m pip install -e ".[test]"
python -m pytest
```
