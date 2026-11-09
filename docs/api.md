# RxMuk API (F2)

## 0. Public API (no account, no key)

Monthly summaries only (totals per drug, district and unit; no patient-level rows).
Swagger UI: `https://thering999.github.io/rxmuk/api/` · spec: `api/openapi.json`

| Purpose | Request |
|---|---|
| List months and file links | `GET .../rxmuk-public/index.json` |
| One month, everything | `GET .../rxmuk-public/summary/2026-09.json` |
| One view, JSON or CSV | `GET .../rxmuk-public/{drugs,districts,hospitals}/2026-09.{json,csv}` |
| Monthly trend per drug | `GET .../rxmuk-public/trend/drugs.{json,csv}` |

`...` = `$SB_URL/storage/v1/object/public`. Months are CE (`2026-09` = Sep 2569). CSV is UTF-8 with BOM; Google Sheets: `=IMPORTDATA("<csv url>")`.

Setup once: run `supabase/migrations/20261109_03_public_api_bucket.sql`. Files refresh automatically after the super admin (role `province`) uploads data to the cloud, or on demand with "เผยแพร่ / อัปเดต API สาธารณะ" in the API tab.

Privacy and policy (schema 1.3):

- `district_drugs` / `hospital_drugs`: drugs given fewer than 5 times in an area are folded into one "อื่นๆ" row per area (`suppressed_items` = how many). Area totals stay exact.
- Cost columns are optional: the super admin's "เผยแพร่ต้นทุน" checkbox (also used by auto-publish). `index.json.includes_cost` tells readers. Months not republished keep whatever they were published with.
- Republishing keeps months listed in the previous `index.json` that are not loaded in this browser (older fiscal years).
- Usage: Supabase dashboard > Logs > Storage, filter `rxmuk-public`, shows which files are read and how often.

## Partner API (record-level, approved agencies only)

Rows without patient ids (optional salted `pid_hash`), via a per-agency key that expires, can be revoked, and logs every call.
Approval first: `docs/approval/rxmuk-api-data-sharing-request.md` (memo for the director and DPO, plus DSA checklist).

Setup once:

1. SQL Editor: run `supabase/migrations/20261109_04_partner_api.sql`.
2. Deploy the function (CLI): `supabase functions deploy rxmuk-partner-api --no-verify-jwt --project-ref vbxijqbgwgippdrhsjnb`
   or Dashboard > Edge Functions > Create, paste `supabase/functions/rxmuk-partner-api/index.ts`, turn off "Verify JWT".
3. Only if some key needs `pid_hash`: Edge Functions > Secrets, add `RXMUK_PID_SALT` (32+ random characters, never change it or hashes stop matching).
4. Super admin creates keys in the API tab ("API สำหรับหน่วยงานที่ได้รับอนุญาต"). The key is shown once.

Call: `GET $SB_URL/functions/v1/rxmuk-partner-api?month=2026-09[&hospcode=][&amp_code=][&didstd=][&limit=5000][&offset=0]` with `X-API-Key: rxk_...`.
Returns `{ month, total, offset, limit, next_offset, rows: [{ month, date, hospcode, hospname, amp_code, amp_name, didstd, dname, amount, value, cost, pid_hash? }] }`. Max limit 50,000; page with `next_offset`.
Errors: 401 bad key format, 403 revoked/expired, 400 bad month, 404 no data. Logs: table `rxmuk_api_log` (readable by role `province`).

The sections below are the authenticated API (approved Supabase accounts).

RxMuk has no server of its own. The API is Supabase's REST endpoints, and RLS decides who sees what.
Every call needs an approved account (a row in `rxmuk_users`).

## 1. Get a token

```bash
curl -s "$SB_URL/auth/v1/token?grant_type=password" \
  -H "apikey: $SB_KEY" -H "Content-Type: application/json" \
  -d '{"email":"you@example.com","password":"..."}'
```

`access_token` is valid for 1 hour. Renew it with `grant_type=refresh_token` and `{"refresh_token":"..."}`.
Every call below sends `-H "apikey: $SB_KEY" -H "Authorization: Bearer $TOKEN"`.

## 2. Data files (storage)

| Purpose | Request |
|---|---|
| List files | `POST $SB_URL/storage/v1/object/list/rxmuk` body `{"prefix":""}` |
| Download a file | `GET $SB_URL/storage/v1/object/authenticated/rxmuk/<name>` (gzip JSON) |
| Upload or replace | `POST` / `PUT` same path. Needs role `province` |

## 3. Tables (PostgREST)

| Table | Read | Write |
|---|---|---|
| `rxmuk_import_log` | `GET /rest/v1/rxmuk_import_log?order=created_at.desc&limit=50` | `POST` `{filename,row_count,months}` |
| `rxmuk_annotations` | `GET /rest/v1/rxmuk_annotations?hospcode=eq.10712&month=eq.2026-10` | `POST` `{hospcode,month,note}`. Delete only your own: `DELETE ?id=eq.<id>` |
| `rxmuk_snapshots` | Your own: `GET /rest/v1/rxmuk_snapshots`. Anyone's, by id: `POST /rest/v1/rpc/rxmuk_get_snapshot` `{"snap_id":"<uuid>"}` | `POST` `{title,data}` (aggregated data only; expires after 7 days) |
| `rxmuk_users` | Your own row only | Admin only (SQL editor) |

POST and DELETE requests also need `-H "Content-Type: application/json"`.
Errors: `401` means the token has expired. `403`, or an empty result, means the account has not been approved or RLS denied the request.

## Example: n8n / Google Sheets

Use an HTTP Request node. Get a token first (step 1), then call `GET /rest/v1/rxmuk_import_log` with that token.
Store the password in n8n credentials. Never put it in the workflow itself.
