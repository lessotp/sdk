# Changelog

All notable changes to the `lessotp/sdk/go` Go SDK.

## 0.2.0 — 2026-06-21

- Multi-channel support: WhatsApp (default) and Telegram.
- New helpers `RequestWhatsAppAuth`, `RequestTelegramAuth`, and the generic `RequestAuth` accepting `AuthRequestParams`.
- `AuthRequestParams{Channel, PhoneNumber}` configures strict vs frictionless mode per channel.
- `AuthRequestResult` now includes `Channel`, `TelegramLink`, and `TelegramText`.
- `VerificationSuccess` parses the new `channel`, `telegram_user_id`, and `telegram_username` webhook fields (legacy payloads without `channel` default to WhatsApp).
- `AuthRequest` is kept as a backward-compatible WhatsApp-only helper.
- Tests cover explicit WhatsApp, Telegram strict, Telegram frictionless, staging environment, and dynamic channel/error handling.

## 0.1.0 — 2026-06-20

- Initial release.
- WhatsApp request via `AuthRequest(phoneNumber, opts...)`.
- `VerifyWebhookSignature` and `ParseVerificationSuccess` helpers.
