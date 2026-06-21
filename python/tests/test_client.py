from __future__ import annotations

import hashlib
import hmac
import json
from http.server import BaseHTTPRequestHandler, HTTPServer
import threading
from urllib import error as urlerror

import pytest

from lessotp_sdk import (
    DEFAULT_ENVIRONMENT,
    LessOTPClient,
    LessOTPError,
    parse_verified_webhook,
    verify_webhook_signature,
)


class _Handler(BaseHTTPRequestHandler):
    status = 200
    response = {
        "status": "success",
        "data": {
            "request_id": "req_abc",
            "unique_code": "A7X92",
            "channel": "whatsapp",
            "wa_link": "https://wa.me/628999999999?text=%2FSTART%20A7X92",
            "expires_in": 180,
            "mode": "strict",
        },
    }
    requests: list = []

    def do_POST(self) -> None:  # noqa: N802
        length = int(self.headers.get("content-length", "0"))
        body = self.rfile.read(length).decode("utf-8")
        self.__class__.requests.append(
            {
                "path": self.path,
                "authorization": self.headers.get("authorization", ""),
                "body": body,
            }
        )
        raw = json.dumps(self.__class__.response).encode("utf-8")
        self.send_response(self.__class__.status)
        self.send_header("content-type", "application/json")
        self.send_header("content-length", str(len(raw)))
        self.end_headers()
        self.wfile.write(raw)

    def log_message(self, format: str, *args: object) -> None:
        return


@pytest.fixture
def server():
    _Handler.status = 200
    _Handler.response = _whatsapp_response()
    _Handler.requests = []
    srv = HTTPServer(("127.0.0.1", 0), _Handler)
    thread = threading.Thread(target=srv.serve_forever, daemon=True)
    thread.start()
    try:
        yield srv
    finally:
        srv.shutdown()
        thread.join(timeout=2)


def _url(server: HTTPServer) -> str:
    return "http://127.0.0.1:%d" % server.server_port


def _whatsapp_response(
    request_id: str = "req_abc", unique_code: str = "A7X92", mode: str = "strict"
) -> dict:
    return {
        "status": "success",
        "data": {
            "request_id": request_id,
            "unique_code": unique_code,
            "channel": "whatsapp",
            "wa_link": "https://wa.me/628999999999?text=%2FSTART%20%s" % unique_code,
            "expires_in": 180,
            "mode": mode,
        },
    }


def _telegram_response(
    request_id: str = "req_tg", unique_code: str = "TGR12", mode: str = "strict"
) -> dict:
    return {
        "status": "success",
        "data": {
            "request_id": request_id,
            "unique_code": unique_code,
            "channel": "telegram",
            "telegram_link": "https://t.me/lessotp_bot?start=%s" % unique_code,
            "telegram_text": "/start %s" % unique_code,
            "expires_in": 180,
            "mode": mode,
        },
    }


def test_default_environment_is_production(server: HTTPServer) -> None:
    client = LessOTPClient("key_test", base_url=_url(server))
    assert client._environment == DEFAULT_ENVIRONMENT == "production"

    client.auth_request("6281234567890")
    assert _Handler.requests[0]["path"] == "/api/v1/auth/request"


def test_constructor_environment_routes_to_staging(server: HTTPServer) -> None:
    client = LessOTPClient("key_test", environment="staging", base_url=_url(server))
    _Handler.response = _whatsapp_response("req_stage", "S1T2G", "strict")

    result = client.auth_request("6281234567890")
    assert result.request_id == "req_stage"
    assert _Handler.requests[0]["path"] == "/api/v1/staging/auth/request"


def test_per_call_environment_override_beats_constructor(server: HTTPServer) -> None:
    client = LessOTPClient("key_test", base_url=_url(server))
    _Handler.response = _whatsapp_response("req_stage", "S1T2G", "strict")

    client.auth_request("6281234567890", environment="staging")
    assert _Handler.requests[0]["path"] == "/api/v1/staging/auth/request"


def test_auth_request_strict_posts_to_production_endpoint(server: HTTPServer) -> None:
    client = LessOTPClient("key_test", base_url=_url(server))
    result = client.auth_request("6281234567890")

    assert result.request_id == "req_abc"
    assert result.channel == "whatsapp"
    assert result.wa_link is not None
    assert result.mode == "strict"
    assert _Handler.requests[0]["path"] == "/api/v1/auth/request"
    assert _Handler.requests[0]["authorization"] == "Bearer key_test"
    assert json.loads(_Handler.requests[0]["body"]) == {
        "channel": "whatsapp",
        "phone_number": "6281234567890",
    }


def test_auth_request_frictionless_sends_whatsapp_channel(server: HTTPServer) -> None:
    _Handler.response = _whatsapp_response("req_fric", "B1C34", "frictionless")
    client = LessOTPClient("key_test", base_url=_url(server))
    result = client.auth_request()
    assert result.mode == "frictionless"
    assert json.loads(_Handler.requests[0]["body"]) == {"channel": "whatsapp"}


def test_telegram_strict_request(server: HTTPServer) -> None:
    _Handler.response = _telegram_response()
    client = LessOTPClient("key_test", base_url=_url(server))
    result = client.request_telegram_auth("6281234567890")
    assert result.channel == "telegram"
    assert result.telegram_link == "https://t.me/lessotp_bot?start=TGR12"
    assert result.telegram_text == "/start TGR12"
    assert json.loads(_Handler.requests[0]["body"]) == {
        "channel": "telegram",
        "phone_number": "6281234567890",
    }


def test_telegram_frictionless_request(server: HTTPServer) -> None:
    _Handler.response = _telegram_response("req_tg_fric", "TGR12", "frictionless")
    client = LessOTPClient("key_test", base_url=_url(server))
    result = client.request_auth(channel="telegram")
    assert result.channel == "telegram"
    assert result.mode == "frictionless"
    assert json.loads(_Handler.requests[0]["body"]) == {"channel": "telegram"}


def test_unknown_channel_raises(server: HTTPServer) -> None:
    client = LessOTPClient("key_test", base_url=_url(server))
    with pytest.raises(LessOTPError, match="channel must be"):
        client.request_auth(channel="signal")


def test_non_2xx_raises_lessotp_error(server: HTTPServer) -> None:
    _Handler.status = 401
    _Handler.response = {"error": "invalid_api_key"}
    client = LessOTPClient("bad", base_url=_url(server))
    with pytest.raises(LessOTPError, match="401"):
        client.auth_request()


def test_unknown_environment_raises(server: HTTPServer) -> None:
    with pytest.raises(LessOTPError, match="environment must be"):
        LessOTPClient("k", environment="qa", base_url=_url(server))


def test_verify_webhook_signature_accepts_hex_and_prefix() -> None:
    body = json.dumps({"event": "verification.success", "request_id": "r1", "phone_number": "628"})
    secret = "whsec_test"
    sig = hmac.new(secret.encode(), body.encode(), hashlib.sha256).hexdigest()

    assert verify_webhook_signature(body, sig, secret) is True
    assert verify_webhook_signature(body, "sha256=" + sig, secret) is True


def test_verify_webhook_signature_rejects_bad_inputs() -> None:
    assert verify_webhook_signature("{}", None, "secret") is False
    assert verify_webhook_signature("{}", "abc", "secret") is False
    assert verify_webhook_signature("{}", "a" * 64, "secret") is False
    assert verify_webhook_signature("{}", "a" * 64, "") is False


def test_parse_verified_webhook_returns_none_on_bad_signature() -> None:
    body = json.dumps({"event": "verification.success", "request_id": "r1", "phone_number": "628"})
    assert parse_verified_webhook(body, None, "secret") is None


def test_parse_verified_webhook_parses_valid_payload() -> None:
    payload = {
        "event": "verification.success",
        "channel": "whatsapp",
        "request_id": "r1",
        "phone_number": "6281234567890",
        "timestamp": "2026-06-20T10:00:00Z",
    }
    body = json.dumps(payload)
    secret = "secret"
    sig = hmac.new(secret.encode(), body.encode(), hashlib.sha256).hexdigest()

    event = parse_verified_webhook(body, sig, secret)
    assert event is not None
    assert event.channel == "whatsapp"
    assert event.request_id == "r1"
    assert event.phone_number == "6281234567890"
    assert event.timestamp == "2026-06-20T10:00:00Z"


def test_parse_verified_webhook_parses_telegram_payload() -> None:
    payload = {
        "event": "verification.success",
        "channel": "telegram",
        "request_id": "r2",
        "phone_number": "6281234567890",
        "telegram_user_id": "123456789",
        "telegram_username": "fajarbc",
    }
    body = json.dumps(payload)
    sig = hmac.new(b"secret", body.encode(), hashlib.sha256).hexdigest()

    event = parse_verified_webhook(body, sig, "secret")
    assert event is not None
    assert event.channel == "telegram"
    assert event.telegram_user_id == "123456789"
    assert event.telegram_username == "fajarbc"


def test_parse_verified_webhook_rejects_wrong_event() -> None:
    payload = {"event": "verification.failed", "request_id": "r1", "phone_number": "628"}
    body = json.dumps(payload)
    secret = "secret"
    sig = hmac.new(secret.encode(), body.encode(), hashlib.sha256).hexdigest()

    with pytest.raises(LessOTPError, match="unexpected webhook event"):
        parse_verified_webhook(body, sig, secret)
