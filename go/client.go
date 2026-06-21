// Package sdk provides a lightweight LessOTP API client and webhook verification helpers.
package sdk

import (
	"bytes"
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net/http"
	"strings"
	"time"
)

// Environment selects the endpoint family. Production is the default.
type Environment string

const (
	EnvironmentProduction Environment = "production"
	EnvironmentStaging    Environment = "staging"
)

// VerificationChannel selects the inbound phone authentication channel.
// WhatsApp is the default for backward compatibility.
type VerificationChannel string

const (
	ChannelWhatsApp VerificationChannel = "whatsapp"
	ChannelTelegram VerificationChannel = "telegram"
)

// VerificationMode is the `mode` value returned by LessOTP's auth request API.
type VerificationMode string

const (
	ModeStrict       VerificationMode = "strict"
	ModeFrictionless VerificationMode = "frictionless"
)

// AuthRequestParams configure a multi-channel auth request.
type AuthRequestParams struct {
	// Channel defaults to ChannelWhatsApp when empty.
	Channel VerificationChannel
	// PhoneNumber enables strict mode. Nil means frictionless mode.
	PhoneNumber *string
}

// AuthRequestResult is the normalized response of POST /api/v1/auth/request.
type AuthRequestResult struct {
	RequestID    string              `json:"request_id"`
	UniqueCode   string              `json:"unique_code"`
	Channel      VerificationChannel `json:"channel"`
	WaLink       string              `json:"wa_link,omitempty"`
	TelegramLink string              `json:"telegram_link,omitempty"`
	TelegramText string              `json:"telegram_text,omitempty"`
	ExpiresIn    int                 `json:"expires_in"`
	Mode         VerificationMode    `json:"mode"`
}

// VerificationSuccess represents a `verification.success` webhook payload.
type VerificationSuccess struct {
	Event            string              `json:"event"`
	Channel          VerificationChannel `json:"channel,omitempty"`
	RequestID        string              `json:"request_id"`
	PhoneNumber      string              `json:"phone_number"`
	TelegramUserID   string              `json:"telegram_user_id,omitempty"`
	TelegramUsername string              `json:"telegram_username,omitempty"`
	Timestamp        string              `json:"timestamp,omitempty"`
}

// Options configure a Client. All fields are optional except APIKey.
type Options struct {
	APIKey      string
	Environment Environment // default: production
	BaseURL     string      // default: https://api.lessotp.com
	Timeout     time.Duration // default: 10s
	HTTPClient  *http.Client
}

// AuthRequestOptions are optional per-request overrides.
type AuthRequestOptions struct {
	Environment Environment
}

// Client is a stateless HTTP client for the LessOTP API. Safe for concurrent use.
type Client struct {
	apiKey      string
	environment Environment
	baseURL     string
	httpClient  *http.Client
}

// NewClient constructs a Client from the provided Options.
func NewClient(opts Options) (*Client, error) {
	if opts.APIKey == "" {
		return nil, errors.New("lessotp: APIKey is required")
	}
	env, err := resolveEnvironment(opts.Environment)
	if err != nil {
		return nil, err
	}
	if opts.BaseURL == "" {
		opts.BaseURL = "https://api.lessotp.com"
	}
	opts.BaseURL = strings.TrimRight(opts.BaseURL, "/")
	if opts.Timeout == 0 {
		opts.Timeout = 10 * time.Second
	}
	if opts.HTTPClient == nil {
		opts.HTTPClient = &http.Client{Timeout: opts.Timeout}
	}
	return &Client{
		apiKey:      opts.APIKey,
		environment: env,
		baseURL:     opts.BaseURL,
		httpClient:  opts.HTTPClient,
	}, nil
}

// AuthRequest creates a WhatsApp verification request.
//
// If phoneNumber is nil, the request is frictionless. Otherwise the
// phoneNumber is sent as strict-mode `phone_number`. The endpoint is selected
// from AuthRequestOptions.Environment when provided, otherwise the Client's
// environment. This method is kept for backward compatibility; use RequestAuth
// or RequestTelegramAuth for explicit multi-channel calls.
func (c *Client) AuthRequest(ctx context.Context, phoneNumber *string, opts ...AuthRequestOptions) (AuthRequestResult, error) {
	return c.RequestWhatsAppAuth(ctx, phoneNumber, opts...)
}

// RequestWhatsAppAuth creates a WhatsApp verification request.
func (c *Client) RequestWhatsAppAuth(ctx context.Context, phoneNumber *string, opts ...AuthRequestOptions) (AuthRequestResult, error) {
	return c.RequestAuth(ctx, AuthRequestParams{Channel: ChannelWhatsApp, PhoneNumber: phoneNumber}, opts...)
}

// RequestTelegramAuth creates a Telegram verification request.
//
// Strict mode: pass phoneNumber. Frictionless mode: pass nil. Telegram users
// always verify by tapping the official Share phone number button; LessOTP does
// not accept manually typed phone numbers as Telegram identity.
func (c *Client) RequestTelegramAuth(ctx context.Context, phoneNumber *string, opts ...AuthRequestOptions) (AuthRequestResult, error) {
	return c.RequestAuth(ctx, AuthRequestParams{Channel: ChannelTelegram, PhoneNumber: phoneNumber}, opts...)
}

// RequestAuth creates a multi-channel verification request.
//
// Channel defaults to WhatsApp for backward compatibility. Endpoint selection
// follows the client environment unless overridden per call.
func (c *Client) RequestAuth(ctx context.Context, params AuthRequestParams, opts ...AuthRequestOptions) (AuthRequestResult, error) {
	env := c.environment
	if len(opts) > 0 {
		resolved, err := resolveEnvironment(opts[0].Environment)
		if err != nil {
			return AuthRequestResult{}, err
		}
		env = resolved
	}
	return c.doAuthRequest(ctx, endpointFor(env), params)
}

func (c *Client) doAuthRequest(ctx context.Context, path string, params AuthRequestParams) (AuthRequestResult, error) {
	channel, err := resolveChannel(params.Channel)
	if err != nil {
		return AuthRequestResult{}, err
	}
	body := map[string]any{"channel": string(channel)}
	if params.PhoneNumber != nil {
		body["phone_number"] = *params.PhoneNumber
	}
	buf, err := json.Marshal(body)
	if err != nil {
		return AuthRequestResult{}, fmt.Errorf("lessotp: marshal request: %w", err)
	}

	url := c.baseURL + path
	req, err := http.NewRequestWithContext(ctx, http.MethodPost, url, bytes.NewReader(buf))
	if err != nil {
		return AuthRequestResult{}, fmt.Errorf("lessotp: build request: %w", err)
	}
	req.Header.Set("authorization", "Bearer "+c.apiKey)
	req.Header.Set("content-type", "application/json")
	req.Header.Set("accept", "application/json")
	req.Header.Set("user-agent", "lessotp-sdk-go/0.2.0")

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return AuthRequestResult{}, fmt.Errorf("lessotp: http: %w", err)
	}
	defer resp.Body.Close()

	raw, _ := io.ReadAll(io.LimitReader(resp.Body, 1<<20))
	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		return AuthRequestResult{}, fmt.Errorf("lessotp: authRequest failed %d: %s", resp.StatusCode, string(raw))
	}

	var envelope struct {
		Status string            `json:"status"`
		Data   AuthRequestResult `json:"data"`
	}
	if err := json.Unmarshal(raw, &envelope); err != nil {
		return AuthRequestResult{}, fmt.Errorf("lessotp: decode response: %w", err)
	}
	if envelope.Status != "success" {
		return AuthRequestResult{}, fmt.Errorf("lessotp: response status %q (raw: %s)", envelope.Status, string(raw))
	}
	if err := validateAuthRequestResult(&envelope.Data); err != nil {
		return AuthRequestResult{}, err
	}
	return envelope.Data, nil
}

func validateAuthRequestResult(result *AuthRequestResult) error {
	if result.Channel == "" {
		result.Channel = ChannelWhatsApp
	}
	channel, err := resolveChannel(result.Channel)
	if err != nil {
		return err
	}
	result.Channel = channel
	if result.RequestID == "" || result.UniqueCode == "" {
		return errors.New("lessotp: response missing request_id or unique_code")
	}
	if result.ExpiresIn <= 0 {
		return errors.New("lessotp: response missing expires_in")
	}
	switch result.Mode {
	case ModeStrict, ModeFrictionless:
	default:
		return fmt.Errorf("lessotp: mode must be 'strict' or 'frictionless', got %q", string(result.Mode))
	}
	if result.Channel == ChannelTelegram {
		if result.TelegramLink == "" || result.TelegramText == "" {
			return errors.New("lessotp: Telegram response missing telegram_link or telegram_text")
		}
		return nil
	}
	if result.WaLink == "" {
		return errors.New("lessotp: WhatsApp response missing wa_link")
	}
	return nil
}

func resolveEnvironment(value Environment) (Environment, error) {
	if value == "" {
		return EnvironmentProduction, nil
	}
	switch value {
	case EnvironmentProduction, EnvironmentStaging:
		return value, nil
	}
	return "", fmt.Errorf("lessotp: environment must be 'production' or 'staging', got %q", string(value))
}

func resolveChannel(value VerificationChannel) (VerificationChannel, error) {
	if value == "" {
		return ChannelWhatsApp, nil
	}
	switch value {
	case ChannelWhatsApp, ChannelTelegram:
		return value, nil
	}
	return "", fmt.Errorf("lessotp: channel must be 'whatsapp' or 'telegram', got %q", string(value))
}

func endpointFor(env Environment) string {
	if env == EnvironmentStaging {
		return "/api/v1/staging/auth/request"
	}
	return "/api/v1/auth/request"
}
