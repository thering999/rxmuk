# RxMuk API (F2)

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
