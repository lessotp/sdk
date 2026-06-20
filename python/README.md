# LessOTP Python SDK

Client for the **LessOTP Inbound WhatsApp Authentication API** (Python 3.8+).

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

# strict
strict = client.auth_request("6281234567890")

# frictionless
frictionless = client.auth_request()

# per-call override
one_off = client.auth_request("6281234567890", environment="staging")

# webhook verification
event = parse_verified_webhook(
    request.get_data(as_text=True),
    request.headers.get("X-Signature"),
    os.environ["LESSOTP_WEBHOOK_SECRET"],
)
if event is None:
    return "bad signature", 403
print(event.request_id, event.phone_number)
```

## API

### `LessOTPClient(api_key, environment='production', base_url='https://api.lessotp.com', timeout_seconds=10)`

| Option | Default | Description |
| --- | --- | --- |
| `api_key` | required | App API key. |
| `environment` | `"production"` | `"production"` or `"staging"`. |
| `base_url` | `https://api.lessotp.com` | API host. |
| `timeout_seconds` | `10` | HTTP timeout. |

### `client.auth_request(phone_number=None, environment=None) -> AuthRequestResult`

Calls the endpoint selected by `environment`. The per-call `environment` overrides the client environment.

### `verify_webhook_signature(raw_body, signature_header, secret) -> bool`

Constant-time HMAC-SHA256 verification. Accepts raw hex and `sha256=` prefixed values.

### `parse_verified_webhook(raw_body, signature_header, secret) -> VerificationSuccess | None`

Returns the parsed payload if the signature is valid; `None` otherwise.

## Errors

Raises `LessOTPError` on transport, auth, or payload problems.

## Tests

```bash
python -m pip install -e ".[test]"
python -m pytest
```
