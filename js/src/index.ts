/**
 * LessOTP Inbound Phone Authentication API — client SDK.
 *
 * Supports WhatsApp (default) and Telegram channels for inbound
 * phone authentication with signed webhook delivery.
 */

export type LessOTPEnvironment = "production" | "staging";
export type VerificationMode = "strict" | "frictionless";
export type VerificationChannel = "whatsapp" | "telegram";

export interface AuthRequestWhatsAppResult {
  channel: "whatsapp";
  requestId: string;
  uniqueCode: string;
  waLink: string;
  expiresIn: number;
  mode: VerificationMode;
}

export interface AuthRequestTelegramResult {
  channel: "telegram";
  requestId: string;
  uniqueCode: string;
  telegramLink: string;
  telegramText: string;
  expiresIn: number;
  mode: VerificationMode;
}

export type AuthRequestResult = AuthRequestWhatsAppResult | AuthRequestTelegramResult;

export type VerificationChannelEvent = VerificationChannel;

export interface VerificationSuccessBase {
  event: "verification.success";
  channel: VerificationChannel;
  requestId: string;
  phoneNumber: string;
  timestamp?: string;
}

export interface VerificationSuccessWhatsApp extends VerificationSuccessBase {
  channel: "whatsapp";
}

export interface VerificationSuccessTelegram extends VerificationSuccessBase {
  channel: "telegram";
  telegramUserId?: string;
  telegramUsername?: string | null;
}

export type VerificationSuccessEvent = VerificationSuccessWhatsApp | VerificationSuccessTelegram;

export interface ClientOptions {
  apiKey: string;
  environment?: LessOTPEnvironment;
  baseUrl?: string;
  timeoutMs?: number;
  fetch?: typeof fetch;
}

export interface AuthRequestOptions {
  environment?: LessOTPEnvironment;
  /** Optional channel override; default is "whatsapp". */
  channel?: VerificationChannel;
}

/** Options for the new multi-channel request method. */
export interface AuthRequestAdvancedOptions extends AuthRequestOptions {
  /**
   * Phone number for strict verification.
   * For Telegram frictionless mode this can be omitted: when channel is
   * "telegram" and `phoneNumber` is omitted, the bot will resolve the phone
   * from the user's contact share button.
   */
  phoneNumber?: string | null;
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

function resolveChannel(value: unknown): VerificationChannel {
  if (value === undefined || value === null) {
    return "whatsapp";
  }
  if (value === "whatsapp" || value === "telegram") {
    return value;
  }
  throw new LessOTPError(
    `LessOTP channel must be 'whatsapp' or 'telegram', got '${String(value)}'`,
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
    this.timeoutMs = options.timeoutMs ?? DEFAULT_TIMEOUT_MS;
    this.fetchImpl = options.fetch ?? fetch;
  }

  /**
   * Create a verification request (legacy convenience method).
   *
   * Use {@link requestAuth} for multi-channel support.
   *
   * When called with only `phoneNumber`, this is equivalent to a WhatsApp
   * `requestAuth({ channel: "whatsapp", phoneNumber })`. When called with no
   * `phoneNumber`, this is WhatsApp frictionless.
   */
  async authRequest(
    phoneNumber?: string,
    options?: AuthRequestOptions,
  ): Promise<AuthRequestResult> {
    const environment = resolveEnvironment(options?.environment ?? this.environment);
    const channel = resolveChannel(options?.channel);
    return this.requestAuth({
      channel,
      phoneNumber: phoneNumber ?? null,
      environment,
    });
  }

  /**
   * Create a multi-channel verification request.
   *
   * Channel defaults to `whatsapp` (backward compatible). For Telegram:
   *
   * - Strict: pass `channel: "telegram"` and `phoneNumber`.
   * - Frictionless: pass `channel: "telegram"` and omit `phoneNumber`.
   *
   * The Telegram bot will always ask the user to share their phone number
   * via the official Telegram contact sharing button. Telegram never accepts
   * manually typed phone numbers.
   */
  async requestAuth(
    options: AuthRequestAdvancedOptions,
  ): Promise<AuthRequestResult> {
    const environment = resolveEnvironment(options.environment ?? this.environment);
    const channel = resolveChannel(options.channel);
    const url = `${this.baseUrl}${endpointFor(environment)}`;

    const body: Record<string, string> = { channel };
    if (options.phoneNumber) {
      body.phone_number = options.phoneNumber;
    }

    const response = await this.fetchImpl(url, {
      method: "POST",
      headers: {
        authorization: `Bearer ${this.apiKey}`,
        "content-type": "application/json",
        accept: "application/json",
        "user-agent": "lessotp-sdk-js/0.2.0",
      },
      body: JSON.stringify(body),
      signal: AbortSignal.timeout(this.timeoutMs),
    });

    if (!response.ok) {
      throw new LessOTPError(
        `LessOTP requestAuth failed: ${response.status} ${response.statusText}`,
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
  const required = ["request_id", "unique_code", "expires_in", "mode", "channel"];
  for (const k of required) {
    if (!(k in d)) {
      throw new LessOTPError(`LessOTP response missing '${k}' in data`);
    }
  }
  const mode = d.mode;
  if (mode !== "strict" && mode !== "frictionless") {
    throw new LessOTPError(`LessOTP response mode must be 'strict' or 'frictionless', got '${String(mode)}'`);
  }
  const channel = d.channel;
  if (channel === "telegram") {
    const link = d.telegram_link;
    const text = d.telegram_text;
    if (typeof link !== "string" || typeof text !== "string") {
      throw new LessOTPError("LessOTP Telegram response missing 'telegram_link' or 'telegram_text'");
    }
    return {
      channel: "telegram",
      requestId: String(d.request_id),
      uniqueCode: String(d.unique_code),
      telegramLink: link,
      telegramText: text,
      expiresIn: Number(d.expires_in),
      mode,
    };
  }
  if (channel === "whatsapp") {
    const link = d.wa_link;
    if (typeof link !== "string") {
      throw new LessOTPError("LessOTP WhatsApp response missing 'wa_link'");
    }
    return {
      channel: "whatsapp",
      requestId: String(d.request_id),
      uniqueCode: String(d.unique_code),
      waLink: link,
      expiresIn: Number(d.expires_in),
      mode,
    };
  }
  throw new LessOTPError(`LessOTP response channel must be 'whatsapp' or 'telegram', got '${String(channel)}'`);
}

function parseVerificationSuccess(json: unknown): VerificationSuccessEvent {
  if (!isObject(json)) {
    throw new LessOTPError("webhook payload is not an object");
  }
  if (json.event !== "verification.success") {
    throw new LessOTPError(`unexpected webhook event '${String(json.event)}'`);
  }
  if (typeof json.request_id !== "string" || typeof json.phone_number !== "string") {
    throw new LessOTPError("webhook payload missing request_id or phone_number");
  }
  const timestamp = typeof json.timestamp === "string" ? json.timestamp : undefined;
  const channel = json.channel;
  if (channel === "telegram") {
    return {
      event: "verification.success",
      channel: "telegram",
      requestId: json.request_id,
      phoneNumber: json.phone_number,
      telegramUserId: typeof json.telegram_user_id === "string" ? json.telegram_user_id : undefined,
      telegramUsername:
        typeof json.telegram_username === "string" ? json.telegram_username : null,
      ...(timestamp ? { timestamp } : {}),
    };
  }
  if (channel === "whatsapp") {
    return {
      event: "verification.success",
      channel: "whatsapp",
      requestId: json.request_id,
      phoneNumber: json.phone_number,
      ...(timestamp ? { timestamp } : {}),
    };
  }
  // Backward compatibility: pre-channel payloads default to WhatsApp.
  return {
    event: "verification.success",
    channel: "whatsapp",
    requestId: json.request_id,
    phoneNumber: json.phone_number,
    ...(timestamp ? { timestamp } : {}),
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
  for (let i = 0; i < buf.length; i += 2) {
    buf[i / 2] = parseInt(hex.substr(i, 2), 16);
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
