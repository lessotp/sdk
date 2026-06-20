"""LessOTP Inbound WhatsApp Authentication API — client SDK."""

from __future__ import annotations

from dataclasses import dataclass
import hashlib
import hmac
import json
from typing import Any, Dict, Optional, Union
from urllib import error as urlerror
from urllib import request as urlrequest

DEFAULT_BASE_URL = "https://api.lessotp.com"
DEFAULT_TIMEOUT_SECONDS = 10
DEFAULT_ENVIRONMENT = "production"
VALID_ENVIRONMENTS = ("production", "staging")
RawBody = Union[str, bytes]


class LessOTPError(RuntimeError):
    """Surface error type for SDK consumers."""


@dataclass(frozen=True)
class AuthRequestResult:
    """Normalized response from ``POST /api/v1/auth/request``."""

    request_id: str
    unique_code: str
    wa_link: str
    expires_in: int
    mode: str

    @classmethod
    def from_response(cls, payload: Dict[str, Any]) -> "AuthRequestResult":
        data = payload.get("data")
        if not isinstance(data, dict):
            raise LessOTPError("LessOTP response missing 'data' object")
        required = ["request_id", "unique_code", "wa_link", "expires_in", "mode"]
        for key in required:
            if key not in data:
                raise LessOTPError("LessOTP response missing '%s'" % key)
        mode = str(data["mode"])
        if mode not in {"strict", "frictionless"}:
            raise LessOTPError(
                "LessOTP response mode must be 'strict' or 'frictionless', got '%s'" % mode
            )
        return cls(
            request_id=str(data["request_id"]),
            unique_code=str(data["unique_code"]),
            wa_link=str(data["wa_link"]),
            expires_in=int(data["expires_in"]),
            mode=mode,
        )


@dataclass(frozen=True)
class VerificationSuccess:
    """Canonical representation of a ``verification.success`` webhook payload."""

    event: str
    request_id: str
    phone_number: str
    timestamp: Optional[str] = None

    @classmethod
    def from_payload(cls, payload: Dict[str, Any]) -> "VerificationSuccess":
        event = str(payload.get("event", ""))
        if event != "verification.success":
            raise LessOTPError("unexpected webhook event '%s'" % event)
        request_id = payload.get("request_id")
        phone_number = payload.get("phone_number")
        if not isinstance(request_id, str) or not isinstance(phone_number, str):
            raise LessOTPError("webhook payload missing request_id or phone_number")
        timestamp = payload.get("timestamp")
        return cls(
            event=event,
            request_id=request_id,
            phone_number=phone_number,
            timestamp=timestamp if isinstance(timestamp, str) else None,
        )


class LessOTPClient:
    """Stateless HTTP client for the LessOTP API."""

    def __init__(
        self,
        api_key: str,
        environment: str = DEFAULT_ENVIRONMENT,
        base_url: str = DEFAULT_BASE_URL,
        timeout_seconds: int = DEFAULT_TIMEOUT_SECONDS,
    ) -> None:
        if not api_key:
            raise LessOTPError("api_key is required")
        self._api_key = api_key
        self._environment = _resolve_environment(environment)
        self._base_url = base_url.rstrip("/")
        self._timeout_seconds = timeout_seconds

    def auth_request(
        self,
        phone_number: Optional[str] = None,
        environment: Optional[str] = None,
    ) -> AuthRequestResult:
        """Create a verification request.

        Endpoint is selected from the client environment. Pass ``environment``
        to override for this call.
        """

        env = _resolve_environment(environment if environment is not None else self._environment)
        return self._auth_request(_endpoint_for(env), phone_number)

    def _auth_request(self, path: str, phone_number: Optional[str]) -> AuthRequestResult:
        body = {} if phone_number is None else {"phone_number": phone_number}
        raw_body = json.dumps(body, separators=(",", ":")).encode("utf-8")
        req = urlrequest.Request(
            self._base_url + path,
            data=raw_body,
            method="POST",
            headers={
                "Authorization": "Bearer %s" % self._api_key,
                "Content-Type": "application/json",
                "Accept": "application/json",
                "User-Agent": "lessotp-sdk-python/0.1.0",
            },
        )
        try:
            with urlrequest.urlopen(req, timeout=self._timeout_seconds) as response:
                status = getattr(response, "status", response.getcode())
                raw = response.read()
        except urlerror.HTTPError as exc:
            raw = exc.read().decode("utf-8", errors="replace")
            raise LessOTPError("LessOTP auth_request failed: %s: %s" % (exc.code, raw)) from exc
        except urlerror.URLError as exc:
            raise LessOTPError("LessOTP auth_request transport error: %s" % exc.reason) from exc

        if status < 200 or status >= 300:
            raise LessOTPError(
                "LessOTP auth_request failed: %s: %s"
                % (status, raw.decode("utf-8", errors="replace"))
            )

        try:
            payload = json.loads(raw.decode("utf-8"))
        except json.JSONDecodeError as exc:
            raise LessOTPError("LessOTP response was not valid JSON") from exc
        if not isinstance(payload, dict) or payload.get("status") != "success":
            raise LessOTPError("LessOTP response missing 'status: success'")
        return AuthRequestResult.from_response(payload)


def _resolve_environment(value: Optional[str]) -> str:
    if value is None or value == "":
        return "production"
    if value not in VALID_ENVIRONMENTS:
        raise LessOTPError(
            "LessOTP environment must be 'production' or 'staging', got '%s'" % value
        )
    return value


def _endpoint_for(environment: str) -> str:
    if environment == "staging":
        return "/api/v1/staging/auth/request"
    return "/api/v1/auth/request"


def verify_webhook_signature(
    raw_body: RawBody,
    signature_header: Optional[str],
    secret: str,
) -> bool:
    """Return true when ``signature_header`` is a valid HMAC-SHA256."""

    if not signature_header or not secret:
        return False
    stripped = signature_header.strip()
    if stripped.lower().startswith("sha256="):
        stripped = stripped[7:]
    if len(stripped) == 0 or len(stripped) % 2 != 0:
        return False
    try:
        provided = bytes.fromhex(stripped)
    except ValueError:
        return False
    body = raw_body.encode("utf-8") if isinstance(raw_body, str) else raw_body
    expected = hmac.new(secret.encode("utf-8"), body, hashlib.sha256).digest()
    return hmac.compare_digest(expected, provided)


def parse_verified_webhook(
    raw_body: RawBody,
    signature_header: Optional[str],
    secret: str,
) -> Optional[VerificationSuccess]:
    """Verify and parse a LessOTP ``verification.success`` webhook payload."""

    if not verify_webhook_signature(raw_body, signature_header, secret):
        return None
    text = raw_body.decode("utf-8") if isinstance(raw_body, bytes) else raw_body
    payload = json.loads(text)
    if not isinstance(payload, dict):
        raise LessOTPError("webhook payload is not an object")
    return VerificationSuccess.from_payload(payload)


__all__ = [
    "AuthRequestResult",
    "LessOTPClient",
    "LessOTPError",
    "VerificationSuccess",
    "parse_verified_webhook",
    "verify_webhook_signature",
]
