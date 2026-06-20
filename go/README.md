# LessOTP Go SDK

Client for the **LessOTP Inbound WhatsApp Authentication API** (Go 1.21+).

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

    // strict
    res, err := client.AuthRequest(context.Background(), &phone)
    if err != nil {
        log.Fatal(err)
    }
    fmt.Println(res.WaLink)

    // per-call override
    _, err = client.AuthRequest(context.Background(), &phone, sdk.AuthRequestOptions{
        Environment: sdk.EnvironmentStaging,
    })
    if err != nil {
        log.Fatal(err)
    }

    _ = staging
}
```

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
    log.Printf("verified %s phone=%s", event.RequestID, event.PhoneNumber)
}
```

## API

### `NewClient(Options) (*Client, error)`

| Field | Default | Description |
| --- | --- | --- |
| `APIKey` | required | App API key. |
| `Environment` | `EnvironmentProduction` | `EnvironmentProduction` or `EnvironmentStaging`. |
| `BaseURL` | `https://api.lessotp.com` | API host. |
| `Timeout` | `10s` | HTTP timeout. |
| `HTTPClient` | `http.Client{Timeout: …}` | Custom client. |

### `(*Client).AuthRequest(ctx, *phoneNumber, opts...) (AuthRequestResult, error)`

Calls the endpoint selected by `Options.Environment`. `AuthRequestOptions{Environment: ...}` overrides per call.

### `VerifyWebhookSignature(rawBody, signatureHeader, secret) bool`

Constant-time HMAC-SHA256 verification. Accepts `sha256=` prefix.

### `ParseVerificationSuccess(rawBody) (VerificationSuccess, error)`

Parses an already-verified payload.

## Tests

```bash
go test ./...
```
