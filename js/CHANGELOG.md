# Changelog

## 0.2.0

- Add Telegram channel support for inbound phone authentication.
- Add `VerificationChannel` type (`"whatsapp" | "telegram"`).
- Add `client.requestAuth({ channel, phoneNumber, environment })` for multi-channel requests.
- `authRequest()` now defaults to WhatsApp and remains backward compatible.
- Response type is now a discriminated union by `channel`:
  - WhatsApp: `waLink`
  - Telegram: `telegramLink`, `telegramText`
- Webhook parsing now includes `channel` and optional Telegram fields (`telegramUserId`, `telegramUsername`).
- Bump user-agent to `lessotp-sdk-js/0.2.0`.

## 0.1.0

- Initial WhatsApp-only release.
