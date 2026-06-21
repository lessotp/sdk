# Changelog

## 0.2.0

- Add Telegram channel support for inbound phone authentication.
- Add `VerificationChannel` value object.
- Add `$client->requestTelegramAuth(...)` convenience method.
- Add `$client->requestAuth(VerificationChannel, $phoneNumber, $environment)` for multi-channel calls.
- `AuthRequestResult` now exposes `getChannel()`, `getTelegramLink()`, `getTelegramText()`.
- `VerificationSuccess` now exposes `getChannel()`, `getTelegramUserId()`, `getTelegramUsername()`.
- Bump User-Agent to `lessotp-sdk-php/0.2.0`.

## 0.1.0

- Initial WhatsApp-only release.
