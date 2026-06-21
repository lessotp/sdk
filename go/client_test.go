package sdk

import (
	"context"
	"encoding/json"
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
		var body map[string]string
		if err := json.Unmarshal(buf, &body); err != nil {
			t.Fatalf("request body: %v", err)
		}
		if body["channel"] != "whatsapp" || body["phone_number"] != "6281234567890" {
			t.Fatalf("unexpected body: %#v", body)
		}
		w.Header().Set("content-type", "application/json")
		_, _ = io.WriteString(w, `{"status":"success","data":{"request_id":"req_abc","unique_code":"A7X92","wa_link":"https://wa.me/628999999999?text=%2FSTART%20A7X92","expires_in":180,"mode":"strict"}}`)
	}))
	defer srv.Close()

	phone := "6281234567890"
	res, err := newTestClient(t, srv.URL, "k_test").AuthRequest(context.Background(), &phone)
	if err != nil {
		t.Fatalf("auth request: %v", err)
	}
	if res.RequestID != "req_abc" || res.Mode != ModeStrict || res.Channel != ChannelWhatsApp {
		t.Fatalf("unexpected result: %+v", res)
	}
	if received != "/api/v1/auth/request" {
		t.Fatalf("path: %q", received)
	}
}

func TestRequestAuthWhatsAppExplicit(t *testing.T) {
	var body map[string]string
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		buf, _ := io.ReadAll(r.Body)
		if err := json.Unmarshal(buf, &body); err != nil {
			t.Fatalf("request body: %v", err)
		}
		w.Header().Set("content-type", "application/json")
		_, _ = io.WriteString(w, `{"status":"success","data":{"request_id":"req_wa","unique_code":"W1A2P","channel":"whatsapp","wa_link":"https://wa.me/628999999999?text=%2FSTART%20W1A2P","expires_in":180,"mode":"strict"}}`)
	}))
	defer srv.Close()

	phone := "6281234567890"
	res, err := newTestClient(t, srv.URL, "k_test").RequestAuth(context.Background(), AuthRequestParams{
		Channel:     ChannelWhatsApp,
		PhoneNumber: &phone,
	})
	if err != nil {
		t.Fatalf("request auth: %v", err)
	}
	if body["channel"] != "whatsapp" || body["phone_number"] != phone {
		t.Fatalf("unexpected body: %#v", body)
	}
	if res.Channel != ChannelWhatsApp || res.WaLink == "" {
		t.Fatalf("unexpected result: %+v", res)
	}
}

func TestRequestTelegramAuthStrict(t *testing.T) {
	var body map[string]string
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		buf, _ := io.ReadAll(r.Body)
		if err := json.Unmarshal(buf, &body); err != nil {
			t.Fatalf("request body: %v", err)
		}
		w.Header().Set("content-type", "application/json")
		_, _ = io.WriteString(w, `{"status":"success","data":{"request_id":"req_tg","unique_code":"T1G2M","channel":"telegram","telegram_link":"https://t.me/lessotp_bot?start=T1G2M","telegram_text":"/start T1G2M","expires_in":180,"mode":"strict"}}`)
	}))
	defer srv.Close()

	phone := "6281234567890"
	res, err := newTestClient(t, srv.URL, "k_test").RequestTelegramAuth(context.Background(), &phone)
	if err != nil {
		t.Fatalf("telegram auth request: %v", err)
	}
	if body["channel"] != "telegram" || body["phone_number"] != phone {
		t.Fatalf("unexpected body: %#v", body)
	}
	if res.Channel != ChannelTelegram || res.TelegramLink == "" || res.TelegramText != "/start T1G2M" || res.Mode != ModeStrict {
		t.Fatalf("unexpected result: %+v", res)
	}
}

func TestRequestTelegramAuthFrictionless(t *testing.T) {
	var body map[string]string
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		buf, _ := io.ReadAll(r.Body)
		if err := json.Unmarshal(buf, &body); err != nil {
			t.Fatalf("request body: %v", err)
		}
		w.Header().Set("content-type", "application/json")
		_, _ = io.WriteString(w, `{"status":"success","data":{"request_id":"req_tg_fric","unique_code":"T1F2R","channel":"telegram","telegram_link":"https://t.me/lessotp_bot?start=T1F2R","telegram_text":"/start T1F2R","expires_in":180,"mode":"frictionless"}}`)
	}))
	defer srv.Close()

	res, err := newTestClient(t, srv.URL, "k_test").RequestAuth(context.Background(), AuthRequestParams{Channel: ChannelTelegram})
	if err != nil {
		t.Fatalf("telegram auth request: %v", err)
	}
	if body["channel"] != "telegram" {
		t.Fatalf("unexpected body: %#v", body)
	}
	if _, ok := body["phone_number"]; ok {
		t.Fatalf("frictionless body must not include phone_number: %#v", body)
	}
	if res.Channel != ChannelTelegram || res.Mode != ModeFrictionless || res.TelegramLink == "" {
		t.Fatalf("unexpected result: %+v", res)
	}
}

func TestAuthRequestWithStagingEnvironment(t *testing.T) {
	var received string
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		received = r.URL.Path
		_, _ = io.ReadAll(r.Body)
		w.Header().Set("content-type", "application/json")
		_, _ = io.WriteString(w, `{"status":"success","data":{"request_id":"req_stage","unique_code":"S1T2G","channel":"whatsapp","wa_link":"https://wa.me/628999999999?text=%2FSTART%20S1T2G","expires_in":180,"mode":"strict"}}`)
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

func TestRequestTelegramAuthWithStagingEnvironment(t *testing.T) {
	var received string
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		received = r.URL.Path
		_, _ = io.ReadAll(r.Body)
		w.Header().Set("content-type", "application/json")
		_, _ = io.WriteString(w, `{"status":"success","data":{"request_id":"req_stage_tg","unique_code":"S1T2G","channel":"telegram","telegram_link":"https://t.me/lessotp_bot?start=S1T2G","telegram_text":"/startS1T2G","expires_in":180,"mode":"frictionless"}}`)
	}))
	defer srv.Close()

	c, err := NewClient(Options{APIKey: "k_test", BaseURL: srv.URL, Environment: EnvironmentStaging})
	if err != nil {
		t.Fatalf("new client: %v", err)
	}
	if _, err := c.RequestTelegramAuth(context.Background(), nil); err != nil {
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
		_, _ = io.WriteString(w, `{"status":"success","data":{"request_id":"req_stage","unique_code":"S1T2G","channel":"whatsapp","wa_link":"https://wa.me/628999999999?text=%2FSTART%20S1T2G","expires_in":180,"mode":"strict"}}`)
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
		var body map[string]string
		if err := json.Unmarshal(buf, &body); err != nil {
			t.Fatalf("request body: %v", err)
		}
		if body["channel"] != "whatsapp" {
			t.Errorf("expected whatsapp channel, got %#v", body)
		}
		if _, ok := body["phone_number"]; ok {
			t.Errorf("frictionless body must not include phone_number, got %#v", body)
		}
		w.Header().Set("content-type", "application/json")
		_, _ = io.WriteString(w, `{"status":"success","data":{"request_id":"req_fric","unique_code":"B1C34","channel":"whatsapp","wa_link":"https://wa.me/628999999999?text=%2FSTART%20B1C34","expires_in":180,"mode":"frictionless"}}`)
	}))
	defer srv.Close()

	res, err := newTestClient(t, srv.URL, "k_test").AuthRequest(context.Background(), nil)
	if err != nil {
		t.Fatalf("auth request: %v", err)
	}
	if res.Mode != ModeFrictionless || res.Channel != ChannelWhatsApp {
		t.Fatalf("expected whatsapp frictionless, got %+v", res)
	}
}

func TestAuthRequestRejectsUnknownChannel(t *testing.T) {
	if _, err := newTestClient(t, "https://example.invalid", "k_test").RequestAuth(context.Background(), AuthRequestParams{Channel: VerificationChannel("sms")}); err == nil {
		t.Fatal("expected error for unknown channel")
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
