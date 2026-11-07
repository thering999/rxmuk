# RxMuk Auth Migration — Design (draft for approval)

**Date:** 2026-11-07
**Status:** Draft. Nothing is built until this is approved.
**Unblocks:** E1, E2, E3 and E5 (Phase E), and F2.

## Current state (verified in code)
1. **The login is decorative.** Credentials are compared in browser JS (`index.html` ~6644, ~6717). The "session" is `localStorage.rxmuk_auth_user`, and anyone can set it in DevTools.
2. **Auto-login.** `public/rxmuk-config.json` is tracked in this public repo. It holds `supabase_url`, `supabase_key` and `default_user`. `loadAppConfig()` (~10589) sets `rxmuk_auth_user = default_user` for every visitor, so every visitor is logged in.
3. **The data is readable by anyone.** The app downloads `storage/v1/object/authenticated/rxmuk/...` using the key from that public config. Any request carrying that key passes the bucket's policies, so anyone who reads the repo can download every fiscal-year file.
   - These files contain row-level drug-dispensing data per hospital, and some imports include patient `pid`.
4. Old credentials and the key are in git history. This links to the open items in memory: Supabase bucket exposure and password rotation.

## Goals
- Data in the bucket is readable and writable only by signed-in, approved accounts.
- No secrets and no auto-login in the repo.
- The groundwork exists for shared annotations (E1), roles (E2) and an audit log (E3), with access enforced by the database rather than by the browser.

## Non-goals
- Self sign-up or social login.
- Row-level filtering inside the single gz data blob. See E2 below.
- Rewriting the app or adding a build step.

## Design
### Accounts
- **Supabase Auth** with email + password. The admin creates accounts in the Supabase dashboard, and sign-up is disabled.
- New table `public.rxmuk_users(user_id uuid pk references auth.users, role text check in ('province','district','unit'), amp_code text, hospcode text, display_name text)`, with RLS where each user can select only their own row.
- Only accounts present in `rxmuk_users` count as approved.

### Client (index.html, no new library)
- **Sign-in:**
  - Replace the hard-coded check with `POST {url}/auth/v1/token?grant_type=password` using the publishable key as `apikey`.
  - Store `access_token`, `refresh_token` and `expires_at` in `sessionStorage`, plus a "remember me" option that uses `localStorage`.
  - Refresh through `grant_type=refresh_token` before expiry.
- **Requests:** every storage and REST call sends `Authorization: Bearer <access_token>`, never the publishable key as the bearer.
- **Remove:** the hard-coded credentials, `default_user` auto-login, and `rxmuk_auth_user` as an auth signal. It may remain only as a display cache.
- **Sign-out:** call `POST /auth/v1/logout`, clear the tokens and clear IndexedDB raw data. Clearing raw data is a choice, see the questions below.
- **Service worker (D1):** it already bypasses `Authorization` requests and `*.supabase.co`, so it needs no change.

### Database / storage policies (SQL migration file, run by the admin)
- **Bucket `rxmuk`:** private. Policies on `storage.objects` allow select, insert and update only when `bucket_id = 'rxmuk'` and `auth.uid()` is in `rxmuk_users`. Writes are restricted to role `province`.
- **E3** `rxmuk_import_log(id, user_id default auth.uid(), filename, row_count, months text[], created_at)`: approved users can insert their own rows; everyone approved can select.
- **E1** `rxmuk_annotations(id, hospcode, month, note, user_id default auth.uid(), created_at)`: approved users can insert and select; users can delete only their own notes.
- **E5** `rxmuk_snapshots(id uuid, data jsonb, created_by, created_at, expires_at)`: see the open question below.

### E2 roles, honestly scoped
- The data lives in one province-wide gz file per fiscal year, so RLS can't hide individual rows inside it.
- **Phase 1:** role-based UI filtering only, labelled as convenience. Province and district users still technically receive the full file.
- **Phase 2, if needed:** write per-district files (`fy2569/amp-4901.json.gz`) and add storage policies keyed on `rxmuk_users.amp_code`.

## Cutover (ordered, reversible)
1. Admin creates the accounts, `rxmuk_users` and the tables. This is safe because nothing uses them yet.
2. Deploy the client that signs in with Supabase Auth. It still works while the bucket is open.
3. Everyone verifies they can sign in.
4. Apply the bucket policies, so the publishable key alone stops working.
   - Rollback: drop the new policies, which re-opens the bucket.
5. Remove `default_user` and the key from the tracked config, and stop tracking the file (`.gitignore` + `config.example.json`).
6. Optionally rotate the Supabase keys.
   - The publishable key is designed to be public, but rotating it cuts off any copy cached before the policies were applied.

## Risks
- **Lockout:** if step 4 runs before accounts exist, nobody can load data. The ordering above prevents this.
- **Offline (D1):** an expired token while offline means cloud sync fails, but local IndexedDB data still shows.
- **History:** the old credentials remain in git history. Once Supabase Auth is in place they no longer grant anything.

## Open questions for the owner
1. **Required, decided first:** after migration, must everyone sign in before seeing any data, including the dashboard? That means creating an account for every person who uses the app.
2. Should signing out wipe the local IndexedDB copy of the data on shared computers?
3. Should E5 share links require sign-in (recommended), or be public with an expiry (e.g. 7 days) and aggregated data only, with no pid and no row-level data?
