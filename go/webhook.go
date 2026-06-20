package sdk

import (
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"errors"
	"strings"
)

// VerifyWebhookSignature returns true when signatureHeader is a valid
// HMAC-SHA256 of rawBody using secret. Accepts both raw hex and `sha256=`
// prefixed values. Always returns a boolean; never panics on malformed input.
func VerifyWebhookSignature(rawBody []byte, signatureHeader string, secret string) bool {
	if signatureHeader == "" || secret == "" {
		return false
	}
	stripped := strings.TrimSpace(signatureHeader)
	stripped = strings.TrimPrefix(stripped, "sha256=")
	stripped = strings.TrimPrefix(stripped, "hmac-sha256=")
	if stripped == "" {
		return false
	}
	expected, err := hex.DecodeString(stripped)
	if err != nil || len(expected) == 0 {
		return false
	}
	mac := hmac.New(sha256.New, []byte(secret))
	mac.Write(rawBody)
	computed := mac.Sum(nil)
	return hmac.Equal(computed, expected)
}

// ParseVerificationSuccess parses a `verification.success` payload. The
// caller must verify the signature first using VerifyWebhookSignature.
func ParseVerificationSuccess(rawBody []byte) (VerificationSuccess, error) {
	var v VerificationSuccess
	if err := jsonUnmarshalStrict(rawBody, &v); err != nil {
		return VerificationSuccess{}, err
	}
	if v.Event != "verification.success" {
		return VerificationSuccess{}, errors.New("lessotp: unexpected webhook event: " + v.Event)
	}
	if v.RequestID == "" || v.PhoneNumber == "" {
		return VerificationSuccess{}, errors.New("lessotp: webhook missing request_id or phone_number")
	}
	return v, nil
}
