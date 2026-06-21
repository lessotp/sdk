# LessOTP JavaScript / Bun / Node SDK

Client for the **LessOTP Inbound Phone Authentication API**.

Supported channels:

- WhatsApp — inbound `/START {code}` phone verification.
- Telegram — bot `/start {code}` plus official **Share phone number** contact verification.

## Install

```bash
npm install @lessotp/sdk
bun add @lessotp/sdk
pnpm add @lessotp/sdk
```

## Usage

```ts
import { LessOTPClient, parseVerifiedWebhook } from "@lessotp/sdk";

// production (default)
const client = new LessOTPClient({ apiKey: process.env.LESSOTP_API_KEY! });

// staging
const staging = new LessOTPClient({
  apiKey: process.env.LESSOTP_STAGING_API_KEY!,
  environment: "staging",
});

// WhatsApp strict (legacy-compatible)
const whatsappStrict = await client.authRequest("6281234567890");
console.log(whatsappStrict.channel, whatsappStrict.waLink);

// WhatsApp frictionless (legacy-compatible)
const whatsappFrictionless = await client.authRequest();

// Telegram strict
const telegramStrict = await client.requestAuth({
  channel: "telegram",
  phoneNumber: "6281234567890",
});
if (telegramStrict.channel === "telegram") {
  console.log(telegramStrict.telegramLink, telegramStrict.telegramText);
}

// Telegram frictionless: user shares phone number via Telegram contact button
const telegramFrictionless = await client.requestAuth({ channel: "telegram" });

// per-call environment override
const oneOff = await client.requestAuth({
  channel: "telegram",
  phoneNumber: "6281234567890",
  environment: "staging",
});

// webhook receiver
const event = await parseVerifiedWebhook(
  req.rawBody.toString("utf8"),
  req.header("x-signature"),
  process.env.LESSOTP_WEBHOOK_SECRET!,
);
if (!event) return res.status(403).end();

console.log(event.channel, event.requestId, event.phoneNumber);
if (event.channel === "telegram") {
  console.log(event.telegramUserId, event.telegramUsername);
}

void staging;
void whatsappFrictionless;
void telegramFrictionless;
void oneOff;
```

## API

### `new LessOTPClient({ apiKey, environment?, baseUrl?, timeoutMs?, fetch? })`

Options follow the same order as the Go SDK: apiKey → environment → baseUrl → timeout → custom transport.

| Option | Default | Description |
| --- | --- | --- |
| `apiKey` | required | App API key. |
| `environment` | `"production"` | `"production"` or `"staging"`. |
| `baseUrl` | `https://api.lessotp.com` | API host. |
| `timeoutMs` | `10000` | HTTP timeout. |
| `fetch` | global `fetch` | Custom fetcher for testing. |

### `client.authRequest(phoneNumber?, options?): Promise<AuthRequestResult>`

Legacy-compatible convenience method. Defaults to WhatsApp.

```ts
await client.authRequest("6281234567890"); // WhatsApp strict
await client.authRequest(); // WhatsApp frictionless
await client.authRequest("6281234567890", { environment: "staging" });
```

### `client.requestAuth(options): Promise<AuthRequestResult>`

Multi-channel request method.

```ts
await client.requestAuth({ channel: "whatsapp", phoneNumber: "6281234567890" });
await client.requestAuth({ channel: "telegram", phoneNumber: "6281234567890" });
await client.requestAuth({ channel: "telegram" }); // Telegram frictionless
```

```ts
type AuthRequestResult =
  | {
      channel: "whatsapp";
      requestId: string;
      uniqueCode: string;
      waLink: string;
      expiresIn: number;
      mode: "strict" | "frictionless";
    }
  | {
      channel: "telegram";
      requestId: string;
      uniqueCode: string;
      telegramLink: string;
      telegramText: string;
      expiresIn: number;
      mode: "strict" | "frictionless";
    };
```

Telegram note: LessOTP only accepts phone numbers shared through Telegram's official contact-sharing button. The platform verifies the shared contact belongs to the Telegram sender.

### `verifyWebhookSignature(rawBody, signatureHeader, secret): Promise<boolean>`

Constant-time HMAC-SHA256 verification. Accepts raw hex and `sha256=` prefixed values.

### `parseVerifiedWebhook(rawBody, signatureHeader, secret): Promise<VerificationSuccessEvent | null>`

Returns the parsed payload if the signature is valid; `null` otherwise.

```ts
type VerificationSuccessEvent =
  | {
      event: "verification.success";
      channel: "whatsapp";
      requestId: string;
      phoneNumber: string;
      timestamp?: string;
    }
  | {
      event: "verification.success";
      channel: "telegram";
      requestId: string;
      phoneNumber: string;
      telegramUserId?: string;
      telegramUsername?: string | null;
      timestamp?: string;
    };
```

## Errors

Throws `LessOTPError` on transport, auth, or payload problems.

## Tests

```bash
bun install
bun test
bun run build
```
