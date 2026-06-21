# LessOTP Go SDK

Client for the **LessOTP Inbound Phone Authentication API** (Go 1.21+), supporting WhatsApp by default and Telegram as an additive channel.

## Install

```bash
go get github.com/lessotp/sdk/go
```

## Usage

```go
package main

import (
    "context"
    "fmt"
    "log"
    "os"

    sdk "github.com/lessotp/sdk/go"
)

func main() {
    // production (default)
    client, err := sdk.NewClient(sdk.Options{
        APIKey:  os.Getenv("LESSOTP_API_KEY"),
        BaseURL: "https://api.lessotp.com",
    })
    if err != nil {
        log.Fatal(err)
    }

    // staging
    staging, err := sdk.NewClient(sdk.Options{
        APIKey:      os.Getenv("LESSOTP_STAGING_API_KEY"),
        Environment: sdk.EnvironmentStaging,
        BaseURL:     "https://api.lessotp.com",
    })
    if err != nil {
        log.Fatal(err)
    }

    phone := "6281234567890"

    // WhatsApp strict (backward-compatible default)
    wa, err := client.AuthRequest(context.Background(), &phone)
    if err != nil {
        log.Fatal(err)
    }
    fmt.Println(wa.WaLink)

    // WhatsApp frictionless
    waFrictionless, err := client.AuthRequest(context.Background(), nil)
    if err != nil {
        log.Fatal(err)
    }
    fmt.Println(waFrictionless.WaLink)

    // Telegram strict
    tg, err := client.RequestTelegramAuth(context.Background(), &phone)
    if err != nil {
        log.Fatal(err)
    }
    fmt.Println(tg.TelegramLink, tg.TelegramText)

    // Telegram frictionless
    tgFrictionless, err := client.RequestTelegramAuth(context.Background(), nil)
    if err != nil {
        log.Fatal(err)
    }
    fmt.Println(tgFrictionless.TelegramLink)

    // Generic multi-channel call
    _, err = client.RequestAuth(context.Background(), sdk.AuthRequestParams{
        Channel:     sdk.ChannelTelegram,
        PhoneNumber: &phone,
    })
    if err != nil {
        log.Fatal(err)
    }

    // per-call endpoint override
    _, err = client.RequestTelegramAuth(context.Background(), nil, sdk.AuthRequestOptions{
        Environment: sdk.EnvironmentStaging,
    })
    if err != nil {
        log.Fatal(err)
    }

    _ = staging
}
```

Telegram requests return `TelegramLink` and `TelegramText`. The end user opens the bot link, sends `/start {code}`, then taps Telegram's official **Share phone number** button. LessOTP verifies Telegram ownership with Telegram's contact payload; manually typed phone numbers are not accepted as Telegram identity.

## Webhook verification

```go
func handler(w http.ResponseWriter, r *http.Request, secret string) {
    body, _ := io.ReadAll(r.Body)
    if !sdk.VerifyWebhookSignature(body, r.Header.Get("X-Signature"), secret) {
        http.Error(w, "bad signature", http.StatusForbidden)
        return
    }
    event, err := sdk.ParseVerificationSuccess(body)
    if err != nil {
        http.Error(w, "bad payload", http.StatusBadRequest)
        return
    }
    log.Printf("verified %s channel=%s phone=%s", event.RequestID, event.Channel, event.PhoneNumber)
    if event.Channel == sdk.ChannelTelegram {
        log.Printf("telegram user id=%s username=%s", event.TelegramUserID, event.TelegramUsername)
    }
}
```

## API

### `NewClient(Options) (*Client, error)`

Constructor parameter order follows the shared LessOTP SDK standard: `apiKey → environment → baseUrl → timeout → transport`.

| Field | Default | Description |
| --- | --- | --- |
| `APIKey` | required | App API key. |
| `Environment` | `EnvironmentProduction` | `EnvironmentProduction` or `EnvironmentStaging`. |
| `BaseURL` | `https://api.lessotp.com` | API host. |
| `Timeout` | `10s` | HTTP timeout. |
| `HTTPClient` | `http.Client{Timeout: …}` | Custom client. |

### `(*Client).AuthRequest(ctx, *phoneNumber, opts...) (AuthRequestResult, error)`

Backward-compatible WhatsApp request. Calls the endpoint selected by `Options.Environment`. `AuthRequestOptions{Environment: ...}` overrides per call.

### `(*Client).RequestWhatsAppAuth(ctx, *phoneNumber, opts...) (AuthRequestResult, error)`

Explicit WhatsApp helper. Passing `nil` uses frictionless mode.

### `(*Client).RequestTelegramAuth(ctx, *phoneNumber, opts...) (AuthRequestResult, error)`

Telegram helper. Passing a phone number uses strict mode; passing `nil` uses frictionless mode.

### `(*Client).RequestAuth(ctx, AuthRequestParams, opts...) (AuthRequestResult, error)`

Generic multi-channel request. `AuthRequestParams.Channel` accepts `ChannelWhatsApp` or `ChannelTelegram` and defaults to WhatsApp when empty.

### `VerifyWebhookSignature(rawBody, signatureHeader, secret) bool`

Constant-time HMAC-SHA256 verification. Accepts `sha256=` prefix.

### `ParseVerificationSuccess(rawBody) (VerificationSuccess, error)`

Parses an already-verified payload. Payloads without `channel` are treated as WhatsApp for backward compatibility. Telegram payloads populate `TelegramUserID` and `TelegramUsername` when present.

## Tests

```bash
go test ./...
```
