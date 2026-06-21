# LessOTP SDKs

Official client libraries for the **LessOTP Inbound Phone Authentication API** (WhatsApp by default, Telegram supported).

## Install

| Runtime | Package | Install |
| --- | --- | --- |
| PHP | `lessotp/sdk` | `composer require lessotp/sdk` |
| JavaScript / Bun / Node | `@lessotp/sdk` | `npm install @lessotp/sdk` |
| Go | `github.com/lessotp/sdk/go` | `go get github.com/lessotp/sdk/go` |
| Python | `lessotp-sdk` | `pip install lessotp-sdk` |

## Surface

Each SDK uses one auth request method. The endpoint is selected by the client `environment`:

- `production` (default) → `POST /api/v1/auth/request`
- `staging` → `POST /api/v1/staging/auth/request`

Per-call environment overrides are also supported.

```ts
const client = new LessOTPClient({ apiKey: "..." }); // production
await client.authRequest("6281234567890");
await client.authRequest(undefined); // frictionless
await client.authRequest("6281234567890", { environment: "staging" });
```

Common helpers:

```ts
verifyWebhookSignature(raw, header, secret)
parseVerifiedWebhook(raw, header, secret)
```

See the per-language README for details:

- [PHP](php/README.md)
- [JavaScript / Bun / Node](js/README.md)
- [Go](go/README.md)
- [Python](python/README.md)

## License

[MIT](LICENSE).
