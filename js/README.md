# LessOTP JavaScript / Bun / Node SDK

Client for the **LessOTP Inbound WhatsApp Authentication API**.

- Bun (native ESM)
- Node.js 18+
- Modern browsers (`crypto.subtle`)

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

// strict
const strict = await client.authRequest("6281234567890");

// frictionless
const frictionless = await client.authRequest();

// per-call override
const oneOff = await client.authRequest("6281234567890", { environment: "staging" });

// webhook receiver
const event = await parseVerifiedWebhook(
  req.rawBody.toString("utf8"),
  req.header("x-signature"),
  process.env.LESSOTP_WEBHOOK_SECRET!,
);
if (!event) return res.status(403).end();
console.log(event.requestId, event.phoneNumber);

void staging;
void strict;
void frictionless;
void oneOff;
```

## API

### `new LessOTPClient({ apiKey, environment?, baseUrl?, fetch?, timeoutMs? })`

| Option | Default | Description |
| --- | --- | --- |
| `apiKey` | required | App API key. |
| `environment` | `"production"` | `"production"` or `"staging"`. |
| `baseUrl` | `https://api.lessotp.com` | API host. |
| `fetch` | global `fetch` | Custom fetcher for testing. |
| `timeoutMs` | `10000` | HTTP timeout. |

### `client.authRequest(phoneNumber?, options?): Promise<AuthRequestResult>`

Calls the auth request endpoint selected by `environment`. `options.environment` overrides the client environment for one call.

```ts
interface AuthRequestResult {
  requestId: string;
  uniqueCode: string;
  waLink: string;
  expiresIn: number;
  mode: "strict" | "frictionless";
}
```

### `verifyWebhookSignature(rawBody, signatureHeader, secret): Promise<boolean>`

Constant-time HMAC-SHA256 verification. Accepts raw hex and `sha256=` prefixed values.

### `parseVerifiedWebhook(rawBody, signatureHeader, secret): Promise<VerificationSuccessEvent | null>`

Returns the parsed payload if the signature is valid; `null` otherwise.

## Errors

Throws `LessOTPError` on transport, auth, or payload problems.

## Tests

```bash
bun install
bun test
bun run build
```
