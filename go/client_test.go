package sdk

import (
	"context"
	"io"
	"net/http"
	"net/http/httptest"
	"testing"
)

func newTestClient(t *testing.T, serverURL, key string) *Client {
	t.Helper()
	c, err := NewClient(Options{APIKey: key, BaseURL: serverURL})
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}
	return c
}

func TestNewClientRejectsEmptyAPIKey(t *testing.T) {
	if _, err := NewClient(Options{}); err == nil {
		t.Fatal("expected error for empty api key")
	}
}

func TestNewClientRejectsUnknownEnvironment(t *testing.T) {
	if _, err := NewClient(Options{APIKey: "k", Environment: Environment("qa")}); err == nil {
		t.Fatal("expected error for unknown environment")
	}
}

func TestAuthRequestStrictDefaultsToProduction(t *testing.T) {
	var received string
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		received = r.URL.Path
		buf, _ := io.ReadAll(r.Body)
		_ = buf
		w.Header().Set("content-type", "application/json")
		_, _ = io.WriteString(w, `{"status":"success","data":{"request_id":"req_abc","unique_code":"A7X92","wa_link":"https://wa.me/628999999999?text=%2FLOGIN%20A7X92","expires_in":180,"mode":"strict"}}`)
	}))
	defer srv.Close()

	phone := "6281234567890"
	res, err := newTestClient(t, srv.URL, "k_test").AuthRequest(context.Background(), &phone)
	if err != nil {
		t.Fatalf("auth request: %v", err)
	}
	if res.RequestID != "req_abc" || res.Mode != ModeStrict {
		t.Fatalf("unexpected result: %+v", res)
	}
	if received != "/api/v1/auth/request" {
		t.Fatalf("path: %q", received)
	}
}

func TestAuthRequestWithStagingEnvironment(t *testing.T) {
	var received string
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		received = r.URL.Path
		_, _ = io.ReadAll(r.Body)
		w.Header().Set("content-type", "application/json")
		_, _ = io.WriteString(w, `{"status":"success","data":{"request_id":"req_stage","unique_code":"S1T2G","wa_link":"https://wa.me/628999999999?text=%2FLOGIN%20S1T2G","expires_in":180,"mode":"strict"}}`)
	}))
	defer srv.Close()

	c, err := NewClient(Options{APIKey: "k_test", BaseURL: srv.URL, Environment: EnvironmentStaging})
	if err != nil {
		t.Fatalf("new client: %v", err)
	}
	phone := "6281234567890"
	if _, err := c.AuthRequest(context.Background(), &phone); err != nil {
		t.Fatalf("auth request: %v", err)
	}
	if received != "/api/v1/staging/auth/request" {
		t.Fatalf("path: %q", received)
	}
}

func TestAuthRequestPerCallEnvironmentOverride(t *testing.T) {
	var received string
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		received = r.URL.Path
		_, _ = io.ReadAll(r.Body)
		w.Header().Set("content-type", "application/json")
		_, _ = io.WriteString(w, `{"status":"success","data":{"request_id":"req_stage","unique_code":"S1T2G","wa_link":"https://wa.me/628999999999?text=%2FLOGIN%20S1T2G","expires_in":180,"mode":"strict"}}`)
	}))
	defer srv.Close()

	c, err := NewClient(Options{APIKey: "k_test", BaseURL: srv.URL, Environment: EnvironmentProduction})
	if err != nil {
		t.Fatalf("new client: %v", err)
	}
	phone := "6281234567890"
	if _, err := c.AuthRequest(context.Background(), &phone, AuthRequestOptions{Environment: EnvironmentStaging}); err != nil {
		t.Fatalf("auth request: %v", err)
	}
	if received != "/api/v1/staging/auth/request" {
		t.Fatalf("path: %q", received)
	}
}

func TestAuthRequestFrictionless(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		buf, _ := io.ReadAll(r.Body)
		if buf != nil && len(buf) > 0 && string(buf) != "{}" {
			t.Errorf("expected empty body or '{}', got %q", string(buf))
		}
		w.Header().Set("content-type", "application/json")
		_, _ = io.WriteString(w, `{"status":"success","data":{"request_id":"req_fric","unique_code":"B1C34","wa_link":"https://wa.me/628999999999?text=%2FLOGIN%20B1C34","expires_in":180,"mode":"frictionless"}}`)
	}))
	defer srv.Close()

	res, err := newTestClient(t, srv.URL, "k_test").AuthRequest(context.Background(), nil)
	if err != nil {
		t.Fatalf("auth request: %v", err)
	}
	if res.Mode != ModeFrictionless {
		t.Fatalf("expected frictionless, got %v", res.Mode)
	}
}

func TestAuthRequestSurfacesHTTPError(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusUnauthorized)
		_, _ = io.WriteString(w, `{"error":"invalid_api_key"}`)
	}))
	defer srv.Close()

	if _, err := newTestClient(t, srv.URL, "k").AuthRequest(context.Background(), nil); err == nil {
		t.Fatal("expected error on 401 response")
	}
}

