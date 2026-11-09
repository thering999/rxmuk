// RxMuk Partner API: record-level drug dispensing rows for approved agencies.
// GET /functions/v1/rxmuk-partner-api?month=2026-09[&hospcode=..][&amp_code=..][&didstd=..][&limit=..][&offset=..]
// Header: X-API-Key: rxk_...   (keys are created and revoked in the RxMuk API tab by a super admin)
//
// Deploy: supabase functions deploy rxmuk-partner-api --no-verify-jwt
// Secrets: RXMUK_PID_SALT (long random string; needed only for keys allowed a pid hash)
// Patient ids are never returned. A key with allow_pid_hash gets a salted one-way hash instead,
// so a partner can count distinct patients without learning who they are.
import { createClient } from "jsr:@supabase/supabase-js@2";

const sb = createClient(Deno.env.get("SUPABASE_URL")!, Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!, {
  auth: { persistSession: false },
});
const BUCKET = "rxmuk";
const PID_SALT = Deno.env.get("RXMUK_PID_SALT") ?? "";
const MAX_LIMIT = 50000;
const CORS = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Headers": "x-api-key, content-type",
  "Access-Control-Allow-Methods": "GET, OPTIONS",
};

type Row = Record<string, unknown>;
const monthCache = new Map<string, { at: string; rows: Row[] }>();

const json = (status: number, body: unknown) =>
  new Response(JSON.stringify(body), { status, headers: { ...CORS, "Content-Type": "application/json; charset=utf-8" } });

async function sha256Hex(s: string): Promise<string> {
  const buf = await crypto.subtle.digest("SHA-256", new TextEncoder().encode(s));
  return [...new Uint8Array(buf)].map((b) => b.toString(16).padStart(2, "0")).join("");
}

async function download(name: string): Promise<Uint8Array> {
  const { data, error } = await sb.storage.from(BUCKET).download(name);
  if (error || !data) throw new Error(`storage ${name}: ${error?.message ?? "missing"}`);
  return new Uint8Array(await data.arrayBuffer());
}

async function gunzipJson(bytes: Uint8Array): Promise<unknown> {
  const stream = new Blob([bytes]).stream().pipeThrough(new DecompressionStream("gzip"));
  return JSON.parse(await new Response(stream).text());
}

// Rows of one month from the same gzip files the web app uploads (listed in meta.json)
async function monthRows(month: string): Promise<Row[] | null> {
  const meta = JSON.parse(new TextDecoder().decode(await download("meta.json")));
  const files = (Array.isArray(meta.files) ? meta.files : []).filter((f: Row) => f.month === month);
  if (!files.length) return null;
  const at = files.map((f: Row) => `${f.name}@${f.at}`).join(",");
  const hit = monthCache.get(month);
  if (hit && hit.at === at) return hit.rows;
  const parts = await Promise.all(files.map(async (f: Row) => gunzipJson(await download(String(f.name)))));
  const rows = parts.flatMap((p) => (Array.isArray(p) ? p : [])) as Row[];
  monthCache.clear(); // keep one month in memory
  monthCache.set(month, { at, rows });
  return rows;
}

Deno.serve(async (req) => {
  if (req.method === "OPTIONS") return new Response(null, { headers: CORS });
  if (req.method !== "GET") return json(405, { error: "GET only" });

  const key = req.headers.get("x-api-key") ?? "";
  if (!/^rxk_[0-9a-f]{48}$/.test(key)) return json(401, { error: "missing or malformed X-API-Key" });
  const { data: k, error: kErr } = await sb.from("rxmuk_api_keys")
    .select("id, allow_pid_hash, expires_at, revoked_at").eq("key_hash", await sha256Hex(key)).maybeSingle();
  if (kErr) return json(500, { error: "key lookup failed" });
  if (!k || k.revoked_at || (k.expires_at && new Date(k.expires_at) < new Date())) {
    return json(403, { error: "key invalid, revoked or expired" });
  }

  const url = new URL(req.url);
  const q = url.searchParams;
  const log = (status: number, rows: number) =>
    sb.from("rxmuk_api_log").insert({ key_id: k.id, query: url.search.slice(0, 500), rows, status }).then(() => {});

  const month = q.get("month") ?? "";
  if (!/^\d{4}-\d{2}$/.test(month)) { await log(400, 0); return json(400, { error: "month=YYYY-MM (CE) is required" }); }
  const limit = Math.min(Math.max(parseInt(q.get("limit") ?? "5000") || 5000, 1), MAX_LIMIT);
  const offset = Math.max(parseInt(q.get("offset") ?? "0") || 0, 0);
  const hospcode = q.get("hospcode"), amp = q.get("amp_code"), didstd = q.get("didstd");

  let rows: Row[] | null;
  try { rows = await monthRows(month); } catch (_e) { await log(500, 0); return json(500, { error: "could not read data" }); }
  if (!rows) { await log(404, 0); return json(404, { error: `no data for ${month}` }); }

  const filtered = rows.filter((t) =>
    (!hospcode || t.hospcode === hospcode) && (!amp || t.amphurCode === amp) && (!didstd || t.didstd === didstd));
  const page = filtered.slice(offset, offset + limit);
  const withHash = k.allow_pid_hash && PID_SALT.length >= 16;
  const out = await Promise.all(page.map(async (t) => {
    const r: Row = {
      month: t.month, date: t.date ?? null,
      hospcode: t.hospcode, hospname: t.hospname, amp_code: t.amphurCode, amp_name: t.amphurName,
      didstd: t.didstd, dname: t.dname, amount: t.amount, value: t.price, cost: t.cost,
    };
    if (withHash && t.pid) r.pid_hash = (await sha256Hex(PID_SALT + String(t.pid))).slice(0, 16);
    return r;
  }));

  await log(200, out.length);
  await sb.from("rxmuk_api_keys").update({ last_used_at: new Date().toISOString() }).eq("id", k.id);
  return json(200, { month, total: filtered.length, offset, limit, next_offset: offset + out.length < filtered.length ? offset + out.length : null, rows: out });
});
