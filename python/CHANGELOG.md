# Changelog

## 0.2.0

- Add Telegram channel support for inbound phone authentication.
- Add `LessOTPClient.request_auth(channel=...)` for multi-channel requests.
- Add `LessOTPClient.request_telegram_auth(...)` convenience.
- `AuthRequestResult` now exposes `channel`, `wa_link`, `telegram_link`, `telegram_text`.
- `VerificationSuccess` now exposes `channel`, `telegram_user_id`, `telegram_username`.
- Bump User-Agent to `lessotp-sdk-python/0.2.0`.

## 0.1.0

- Initial WhatsApp-only release.
