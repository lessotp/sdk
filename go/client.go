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

// VerificationMode is the `mode` value returned by LessOTP's auth request API.
type VerificationMode string

const (
	ModeStrict       VerificationMode = "strict"
	ModeFrictionless VerificationMode = "frictionless"
)

// AuthRequestResult is the normalized response of POST /api/v1/auth/request.
type AuthRequestResult struct {
	RequestID  string          `json:"request_id"`
	UniqueCode string          `json:"unique_code"`
	WaLink     string          `json:"wa_link"`
	ExpiresIn  int             `json:"expires_in"`
	Mode       VerificationMode `json:"mode"`
}

// VerificationSuccess represents a `verification.success` webhook payload.
type VerificationSuccess struct {
	Event       string `json:"event"`
	RequestID   string `json:"request_id"`
	PhoneNumber string `json:"phone_number"`
	Timestamp   string `json:"timestamp,omitempty"`
}

// Options configure a Client. All fields are optional except APIKey.
type Options struct {
	APIKey      string
	Environment Environment // default: production
	BaseURL     string        // default: https://api.lessotp.com
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

// AuthRequest creates a verification request.
//
// If phoneNumber is nil, the request is frictionless. Otherwise the
// phoneNumber is sent as the strict-mode `phone_number`. The endpoint is
// selected from AuthRequestOptions.Environment when provided, otherwise the
// Client's environment.
func (c *Client) AuthRequest(ctx context.Context, phoneNumber *string, opts ...AuthRequestOptions) (AuthRequestResult, error) {
	env := c.environment
	if len(opts) > 0 {
		resolved, err := resolveEnvironment(opts[0].Environment)
		if err != nil {
			return AuthRequestResult{}, err
		}
		env = resolved
	}
	return c.doAuthRequest(ctx, endpointFor(env), phoneNumber)
}

func (c *Client) doAuthRequest(ctx context.Context, path string, phoneNumber *string) (AuthRequestResult, error) {
	body := map[string]any{}
	if phoneNumber != nil {
		body["phone_number"] = *phoneNumber
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
	req.Header.Set("user-agent", "lessotp-sdk-go/0.1.0")

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
		Status string             `json:"status"`
		Data   AuthRequestResult `json:"data"`
	}
	if err := json.Unmarshal(raw, &envelope); err != nil {
		return AuthRequestResult{}, fmt.Errorf("lessotp: decode response: %w", err)
	}
	if envelope.Status != "success" {
		return AuthRequestResult{}, fmt.Errorf("lessotp: response status %q (raw: %s)", envelope.Status, string(raw))
	}
	return envelope.Data, nil
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

func endpointFor(env Environment) string {
	if env == EnvironmentStaging {
		return "/api/v1/staging/auth/request"
	}
	return "/api/v1/auth/request"
}
