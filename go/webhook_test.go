package sdk

import (
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"strings"
	"testing"
)

func TestVerifyWebhookSignature(t *testing.T) {
	body := []byte(`{"event":"verification.success","request_id":"r1","phone_number":"6281234567890"}`)
	secret := "whsec_test"
	mac := hmac.New(sha256.New, []byte(secret))
	mac.Write(body)
	goodHex := hex.EncodeToString(mac.Sum(nil))

	tests := []struct {
		name   string
		header string
		want   bool
	}{
		{"raw hex valid", goodHex, true},
		{"sha256= prefix valid", "sha256=" + goodHex, true},
		{"empty header", "", false},
		{"empty secret still rejects", goodHex, true}, // sanity behavior test below
		{"not hex", "not-hex", false},
		{"odd-length hex", "abc", false},
		{"mismatched", strings.Repeat("a", 64), false},
		{"prefix only", "sha256=", false},
	}
	for _, tc := range tests {
		t.Run(tc.name, func(t *testing.T) {
			if tc.name == "empty secret still rejects" {
				if VerifyWebhookSignature(body, goodHex, "") {
					t.Fatal("empty secret must reject")
				}
				return
			}
			got := VerifyWebhookSignature(body, tc.header, secret)
			if got != tc.want {
				t.Fatalf("want=%v got=%v", tc.want, got)
			}
		})
	}
}

func TestParseVerificationSuccess(t *testing.T) {
	body := []byte(`{"event":"verification.success","request_id":"r1","phone_number":"6281234567890","timestamp":"2026-06-20T10:00:00Z"}`)
	v, err := ParseVerificationSuccess(body)
	if err != nil {
		t.Fatalf("parse: %v", err)
	}
	if v.RequestID != "r1" || v.PhoneNumber != "6281234567890" {
		t.Fatalf("unexpected: %+v", v)
	}
}

func TestParseVerificationSuccessRejectsWrongEvent(t *testing.T) {
	body := []byte(`{"event":"verification.failed","request_id":"r1","phone_number":"628"}`)
	if _, err := ParseVerificationSuccess(body); err == nil {
		t.Fatal("expected error on wrong event")
	}
}

func TestParseVerificationSuccessRejectsMissingFields(t *testing.T) {
	body := []byte(`{"event":"verification.success","phone_number":"628"}`)
	if _, err := ParseVerificationSuccess(body); err == nil {
		t.Fatal("expected error on missing request_id")
	}
}
