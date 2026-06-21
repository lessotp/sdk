import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import {
  LessOTPClient,
  LessOTPError,
  parseVerifiedWebhook,
  verifyWebhookSignature,
} from "../src/index.js";

const productionResponse = {
  status: "success",
  data: {
    request_id: "req_abc",
    unique_code: "A7X92",
    channel: "whatsapp",
    wa_link: "https://wa.me/628999999999?text=%2FSTART%20A7X92",
    expires_in: 180,
    mode: "strict",
  },
};

const stagingResponse = {
  status: "success",
  data: {
    request_id: "req_stage",
    unique_code: "S1T2G",
    channel: "whatsapp",
    wa_link: "https://wa.me/628999999999?text=%2FSTART%20S1T2G",
    expires_in: 180,
    mode: "strict",
  },
};

const telegramResponse = {
  status: "success",
  data: {
    request_id: "req_tg",
    unique_code: "TGR12",
    channel: "telegram",
    telegram_link: "https://t.me/lessotp_bot?start=TGR12",
    telegram_text: "/start TGR12",
    expires_in: 180,
    mode: "strict",
  },
};

// Replace `globalThis.fetch` for the duration of a test without using
// vi.stubGlobal / vi.unstubAllGlobals so the tests work on Vitest 1.x and 2.x.
const ORIGINAL_FETCH = globalThis.fetch;
let fetchMock: ReturnType<typeof vi.fn>;

function jsonResponse(payload: unknown, status = 200): Response {
  return new Response(JSON.stringify(payload), { status });
}

function responseError(status: number, body: unknown): Response {
  return jsonResponse(body, status);
}

describe("LessOTPClient.authRequest", () => {
  beforeEach(() => {
    fetchMock = vi.fn();
    (globalThis as { fetch: typeof fetch }).fetch = fetchMock as unknown as typeof fetch;
  });

  afterEach(() => {
    (globalThis as { fetch: typeof fetch }).fetch = ORIGINAL_FETCH;
  });

  it("defaults to production endpoint", async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(productionResponse));

    const client = new LessOTPClient({
      apiKey: "key_test",
      baseUrl: "https://api.lessotp.example",
    });

    const result = await client.authRequest("6281234567890");

    expect(result.channel).toBe("whatsapp");
    expect(result.requestId).toBe("req_abc");
    if (result.channel === "whatsapp") {
      expect(result.waLink).toBe(productionResponse.data.wa_link);
    }
    const [url, init] = fetchMock.mock.calls[0]!;
    expect(url).toBe("https://api.lessotp.example/api/v1/auth/request");
    expect(init.method).toBe("POST");
    expect((init.headers as Record<string, string>).authorization).toBe("Bearer key_test");
    expect(JSON.parse(init.body as string)).toEqual({ channel: "whatsapp", phone_number: "6281234567890" });
  });

  it("uses the staging endpoint when constructed with environment: 'staging'", async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(stagingResponse));

    const client = new LessOTPClient({
      apiKey: "key_test",
      baseUrl: "https://api.lessotp.example",
      environment: "staging",
    });

    const result = await client.authRequest("6281234567890");
    expect(result.requestId).toBe("req_stage");

    const [url] = fetchMock.mock.calls[0]!;
    expect(url).toBe("https://api.lessotp.example/api/v1/staging/auth/request");
  });

  it("allows a per-call environment override", async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(stagingResponse));

    const client = new LessOTPClient({
      apiKey: "key_test",
      baseUrl: "https://api.lessotp.example",
      environment: "production",
    });

    await client.authRequest("6281234567890", { environment: "staging" });

    const [url] = fetchMock.mock.calls[0]!;
    expect(url).toBe("https://api.lessotp.example/api/v1/staging/auth/request");
  });

  it("sends an empty body for frictionless mode", async () => {
    fetchMock.mockResolvedValueOnce(
      jsonResponse({
        ...productionResponse,
        data: { ...productionResponse.data, request_id: "req_fric", mode: "frictionless" },
      }),
    );

    const client = new LessOTPClient({
      apiKey: "key_test",
      baseUrl: "https://api.lessotp.example",
    });
    const result = await client.authRequest();

    expect(result.mode).toBe("frictionless");
    const [, init] = fetchMock.mock.calls[0]!;
    expect(JSON.parse(init.body as string)).toEqual({ channel: "whatsapp" });
  });

  it("creates Telegram strict request when channel=telegram with phoneNumber", async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(telegramResponse));

    const client = new LessOTPClient({
      apiKey: "key_test",
      baseUrl: "https://api.lessotp.example",
    });
    const result = await client.requestAuth({
      channel: "telegram",
      phoneNumber: "6281234567890",
    });

    expect(result.channel).toBe("telegram");
    if (result.channel === "telegram") {
      expect(result.telegramLink).toBe(telegramResponse.data.telegram_link);
      expect(result.telegramText).toBe("/start TGR12");
    }

    const [url, init] = fetchMock.mock.calls[0]!;
    expect(url).toBe("https://api.lessotp.example/api/v1/auth/request");
    expect(JSON.parse(init.body as string)).toEqual({
      channel: "telegram",
      phone_number: "6281234567890",
    });
  });

  it("creates Telegram frictionless request without phoneNumber", async () => {
    fetchMock.mockResolvedValueOnce(
      jsonResponse({
        ...telegramResponse,
        data: { ...telegramResponse.data, mode: "frictionless" },
      }),
    );

    const client = new LessOTPClient({
      apiKey: "key_test",
      baseUrl: "https://api.lessotp.example",
    });
    const result = await client.requestAuth({ channel: "telegram" });
    expect(result.mode).toBe("frictionless");

    const [, init] = fetchMock.mock.calls[0]!;
    expect(JSON.parse(init.body as string)).toEqual({ channel: "telegram" });
  });

  it("rejects unknown channel values", async () => {
    const client = new LessOTPClient({ apiKey: "key_test" });
    await expect(
      // @ts-expect-error — invalid channel value passed at runtime
      client.requestAuth({ channel: "signal" }),
    ).rejects.toThrow(/channel must be/);
  });

  it("throws LessOTPError on non-2xx", async () => {
    const client = new LessOTPClient({ apiKey: "bad" });

    fetchMock.mockResolvedValueOnce(responseError(401, { error: "invalid_api_key" }));
    await expect(client.authRequest()).rejects.toBeInstanceOf(LessOTPError);

    fetchMock.mockResolvedValueOnce(responseError(401, { error: "invalid_api_key" }));
    await expect(client.authRequest()).rejects.toThrow(/401/);
  });

  it("throws on invalid payload shape", async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse({ status: "success", data: {} }));
    const client = new LessOTPClient({ apiKey: "key_test" });
    await expect(client.authRequest()).rejects.toThrow(/request_id/);
  });

  it("rejects unknown environment values at construction", () => {
    expect(
      () =>
        new LessOTPClient({
          apiKey: "k",
          // @ts-expect-error — invalid environment for compile-time coverage
          environment: "qa",
        }),
    ).toThrow(LessOTPError);
  });
});

describe("verifyWebhookSignature", () => {
  it("accepts a valid hex HMAC SHA256", async () => {
    const { createHmac } = await import("node:crypto");
    const body = JSON.stringify({ event: "verification.success", request_id: "r1", phone_number: "6281234567890" });
    const secret = "whsec_test";
    const sig = createHmac("sha256", secret).update(body).digest("hex");

    expect(await verifyWebhookSignature(body, sig, secret)).toBe(true);
  });

  it("accepts sha256= prefix", async () => {
    const { createHmac } = await import("node:crypto");
    const body = "{}";
    const secret = "whsec_test";
    const sig = `sha256=${createHmac("sha256", secret).update(body).digest("hex")}`;
    expect(await verifyWebhookSignature(body, sig, secret)).toBe(true);
  });

  it("rejects missing header / empty secret", async () => {
    expect(await verifyWebhookSignature("{}", null, "s")).toBe(false);
    expect(await verifyWebhookSignature("{}", "abcd", "")).toBe(false);
  });

  it("rejects hex of wrong length", async () => {
    expect(await verifyWebhookSignature("{}", "abc", "s")).toBe(false);
  });

  it("rejects mismatched signature", async () => {
    expect(await verifyWebhookSignature("{}", "a".repeat(64), "s")).toBe(false);
  });
});

describe("parseVerifiedWebhook", () => {
  it("returns null on bad signature", async () => {
    const body = JSON.stringify({ event: "verification.success", request_id: "r1", phone_number: "628" });
    expect(await parseVerifiedWebhook(body, null, "s")).toBeNull();
  });

  it("parses valid signed payload", async () => {
    const { createHmac } = await import("node:crypto");
    const payload = { event: "verification.success", channel: "whatsapp", request_id: "r1", phone_number: "6281234567890", timestamp: "2026-06-20T10:00:00Z" };
    const body = JSON.stringify(payload);
    const sig = createHmac("sha256", "secret").update(body).digest("hex");

    const parsed = await parseVerifiedWebhook(body, sig, "secret");
    expect(parsed).toMatchObject({
      event: "verification.success",
      channel: "whatsapp",
      requestId: "r1",
      phoneNumber: "6281234567890",
    });
  });

  it("parses Telegram webhook payload with telegram_user_id", async () => {
    const { createHmac } = await import("node:crypto");
    const payload = {
      event: "verification.success",
      channel: "telegram",
      request_id: "r2",
      phone_number: "6281234567890",
      telegram_user_id: "123456789",
      telegram_username: "fajarbc",
      timestamp: "2026-06-21T10:00:00Z",
    };
    const body = JSON.stringify(payload);
    const sig = createHmac("sha256", "secret").update(body).digest("hex");

    const parsed = await parseVerifiedWebhook(body, sig, "secret");
    expect(parsed).toMatchObject({
      event: "verification.success",
      channel: "telegram",
      requestId: "r2",
      phoneNumber: "6281234567890",
      telegramUserId: "123456789",
      telegramUsername: "fajarbc",
    });
  });

  it("throws on signed payload with wrong event", async () => {
    const { createHmac } = await import("node:crypto");
    const payload = { event: "verification.failed", request_id: "r1", phone_number: "628" };
    const body = JSON.stringify(payload);
    const sig = createHmac("sha256", "secret").update(body).digest("hex");

    await expect(parseVerifiedWebhook(body, sig, "secret")).rejects.toBeInstanceOf(LessOTPError);
  });
});

// Reference the unused helper so it stays in the harness for future use.
void ORIGINAL_FETCH;
