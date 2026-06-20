/**
 * LessOTP Inbound WhatsApp Authentication API — client SDK.
 */

export type LessOTPEnvironment = "production" | "staging";
export type VerificationMode = "strict" | "frictionless";

export interface AuthRequestResult {
  requestId: string;
  uniqueCode: string;
  waLink: string;
  expiresIn: number;
  mode: VerificationMode;
}

export interface VerificationSuccessEvent {
  event: "verification.success";
  requestId: string;
  phoneNumber: string;
  timestamp?: string;
}

export interface ClientOptions {
  apiKey: string;
  environment?: LessOTPEnvironment;
  baseUrl?: string;
  fetch?: typeof fetch;
  timeoutMs?: number;
}

export interface AuthRequestOptions {
  environment?: LessOTPEnvironment;
}

export class LessOTPError extends Error {
  override readonly name = "LessOTPError" as const;
  constructor(message: string, public readonly cause?: unknown) {
    super(message);
  }
}

const DEFAULT_BASE_URL = "https://api.lessotp.com";
const DEFAULT_TIMEOUT_MS = 10_000;
const ENVIRONMENTS: Record<LessOTPEnvironment, string> = {
  production: "/api/v1/auth/request",
  staging: "/api/v1/staging/auth/request",
};

function normalizeBaseUrl(raw: string): string {
  return raw.replace(/\/+$/, "");
}

function resolveEnvironment(value: unknown): LessOTPEnvironment {
  if (value === undefined || value === null) {
    return "production";
  }
  if (value === "production" || value === "staging") {
    return value;
  }
  throw new LessOTPError(
    `LessOTP environment must be 'production' or 'staging', got '${String(value)}'`,
  );
}

function endpointFor(environment: LessOTPEnvironment): string {
  return ENVIRONMENTS[environment];
}

/** Stateless HTTP client for the LessOTP API. */
export class LessOTPClient {
  private readonly apiKey: string;
  private readonly environment: LessOTPEnvironment;
  private readonly baseUrl: string;
  private readonly fetchImpl: typeof fetch;
  private readonly timeoutMs: number;

  constructor(options: ClientOptions) {
    if (!options.apiKey) {
      throw new LessOTPError("apiKey is required");
    }
    this.apiKey = options.apiKey;
    this.environment = resolveEnvironment(options.environment);
    this.baseUrl = normalizeBaseUrl(options.baseUrl ?? DEFAULT_BASE_URL);
    this.fetchImpl = options.fetch ?? fetch;
    this.timeoutMs = options.timeoutMs ?? DEFAULT_TIMEOUT_MS;
  }

  /**
   * Create a verification request.
   *
   * The endpoint is selected from the client environment. Use
   * `{ environment: "staging" }` on the constructor or this method for staging.
   */
  async authRequest(
    phoneNumber?: string,
    options?: AuthRequestOptions,
  ): Promise<AuthRequestResult> {
    const environment = resolveEnvironment(options?.environment ?? this.environment);
    const url = `${this.baseUrl}${endpointFor(environment)}`;
    const response = await this.fetchImpl(url, {
      method: "POST",
      headers: {
        authorization: `Bearer ${this.apiKey}`,
        "content-type": "application/json",
        accept: "application/json",
        "user-agent": "lessotp-sdk-js/0.1.0",
      },
      body: JSON.stringify(
        phoneNumber === undefined ? {} : { phone_number: phoneNumber },
      ),
      signal: AbortSignal.timeout(this.timeoutMs),
    });

    if (!response.ok) {
      throw new LessOTPError(
        `LessOTP authRequest failed: ${response.status} ${response.statusText}`,
      );
    }

    const json: unknown = await response.json();
    return parseAuthRequest(json);
  }

  /** Constant-time HMAC SHA256 verification of a LessOTP webhook. */
  static async verifyWebhookSignature(
    rawBody: string | Uint8Array,
    signatureHeader: string | null,
    secret: string,
  ): Promise<boolean> {
    return verifyWebhookSignature(rawBody, signatureHeader, secret);
  }
}

export async function verifyWebhookSignature(
  rawBody: string | Uint8Array,
  signatureHeader: string | null,
  secret: string,
): Promise<boolean> {
  if (!signatureHeader || !secret) return false;

  const stripped = signatureHeader.trim().replace(/^sha256=/i, "");
  if (!/^[a-f0-9]+$/i.test(stripped) || stripped.length % 2 !== 0) {
    return false;
  }

  let expected: Uint8Array;
  if (typeof globalThis.crypto?.subtle?.sign === "function") {
    const key = await globalThis.crypto.subtle.importKey(
      "raw",
      new TextEncoder().encode(secret),
      { name: "HMAC", hash: "SHA-256" },
      false,
      ["sign"],
    );
    const buf: BufferSource =
      typeof rawBody === "string"
        ? new TextEncoder().encode(rawBody)
        : (new Uint8Array(rawBody) as Uint8Array<ArrayBuffer> as BufferSource);
    const signature = await globalThis.crypto.subtle.sign("HMAC", key, buf);
    expected = new Uint8Array(signature);
  } else {
    const { createHmac } = await import("node:crypto");
    const bodyBuf = typeof rawBody === "string" ? rawBody : Buffer.from(rawBody);
    expected = new Uint8Array(createHmac("sha256", secret).update(bodyBuf).digest());
  }

  const provided = hexToBytes(stripped);
  return constantTimeEqual(expected, provided);
}

function parseAuthRequest(json: unknown): AuthRequestResult {
  if (!isObject(json) || json.status !== "success" || !isObject(json.data)) {
    throw new LessOTPError("LessOTP response missing 'status: success' or 'data' object");
  }
  const d = json.data;
  const required = ["request_id", "unique_code", "wa_link", "expires_in", "mode"];
  for (const k of required) {
    if (!(k in d)) {
      throw new LessOTPError(`LessOTP response missing '${k}' in data`);
    }
  }
  const mode = d.mode;
  if (mode !== "strict" && mode !== "frictionless") {
    throw new LessOTPError(`LessOTP response mode must be 'strict' or 'frictionless', got '${String(mode)}'`);
  }
  return {
    requestId: String(d.request_id),
    uniqueCode: String(d.unique_code),
    waLink: String(d.wa_link),
    expiresIn: Number(d.expires_in),
    mode,
  };
}

function parseVerificationSuccess(json: unknown): VerificationSuccessEvent {
  if (!isObject(json)) {
    throw new LessOTPError("webhook payload is not an object");
  }
  if (json.event !== "verification.success") {
    throw new LessOTPError(`unexpected webhook event '${String(json.event)}'`);
  }
  if (
    typeof json.request_id !== "string" ||
    typeof json.phone_number !== "string"
  ) {
    throw new LessOTPError("webhook payload missing request_id or phone_number");
  }
  return {
    event: "verification.success",
    requestId: json.request_id,
    phoneNumber: json.phone_number,
    ...(typeof json.timestamp === "string" ? { timestamp: json.timestamp } : {}),
  };
}

export async function parseVerifiedWebhook(
  rawBody: string | Uint8Array,
  signatureHeader: string | null,
  secret: string,
): Promise<VerificationSuccessEvent | null> {
  const ok = await verifyWebhookSignature(rawBody, signatureHeader, secret);
  if (!ok) return null;
  const text = typeof rawBody === "string" ? rawBody : new TextDecoder().decode(rawBody);
  return parseVerificationSuccess(JSON.parse(text));
}

function isObject(x: unknown): x is Record<string, unknown> {
  return typeof x === "object" && x !== null && !Array.isArray(x);
}

function hexToBytes(hex: string): Uint8Array {
  const buf = new Uint8Array(hex.length / 2);
  for (let i = 0; i < buf.length; i++) {
    buf[i] = parseInt(hex.substr(i * 2, 2), 16);
  }
  return buf;
}

function constantTimeEqual(a: Uint8Array, b: Uint8Array): boolean {
  if (a.length !== b.length) return false;
  let diff = 0;
  for (let i = 0; i < a.length; i++) {
    diff |= a[i]! ^ b[i]!;
  }
  return diff === 0;
}
