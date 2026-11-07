# Phase D Integration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development.

**Goal:** Phase D adds four things: offline PWA support, an n8n webhook after import, a Google Sheets export (TSV through the clipboard), and flags for drugs outside the National Essential Drug List (NED).

**Spec:** `docs/superpowers/specs/2026-11-06-rxmuk-full-features-design.md` (Phase D)

## Global Constraints
- `index.html` stays a single file. D1 may add `manifest.webmanifest`, `sw.js` and an icon at the repo root, next to `index.html`. nginx serves the repo root.
- Raw ES2020. No new libraries. Thai UI, English code.
- Use `escapeHtml()` on any imported string that goes into innerHTML. No inline `onclick` with interpolated data.
- No regression and no cold-load growth.
- Commit with git through the PowerShell tool, with `-m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"`.

## Rulings
- **D1 storage:** Transaction data already lives in IndexedDB (`idbGet/idbSet('rxmuk_raw_data')`), so the spec's "move to IndexedDB" step is done.
  - Reconnect sync reuses the existing `sbCloudIsNewer` and `supabasePullData(true)` on the `online` event. There is no new delta API.
- **D1 service-worker caching:**
  - Navigation and `index.html` are network-first, so new deploys arrive, and the existing `reloadIfStale` keeps working. The cache is the fallback when offline.
  - Same-origin static files (`hospitals_db.js`, the icon and the manifest) and the versioned cdnjs scripts are cache-first.
  - Never cache: non-GET requests, `*.php`, `/api/`, Supabase hosts (`*.supabase.co`), `uploads/`, and any request with an `Authorization` header.
  - Register the worker only on `https:` or `localhost`.
- **D2:** The webhook URL must be `https://`, or `http://` on localhost, 127.0.0.1 or the n8n Docker host `n8n`. Anything else is rejected with a Thai message.
  - The payload holds counts only, with no patient-level data: `{event:'rxmuk.import', filename, months:[...], rowCount, hospCount, importedBy, at}`. `importedBy` is the existing `rxmuk_auth_user` value.
  - Send it fire-and-forget, with a 10s timeout through AbortController. A failure shows a toast and never blocks the import.
  - Add a "ทดสอบ" (test) button that sends `event:'rxmuk.test'`.
- **D3:** Copy as TSV through `navigator.clipboard.writeText`, falling back to a hidden textarea + `execCommand('copy')`. Then show the toast `คัดลอกแล้ว — เปิด Google Sheets แล้วกด Ctrl+V` plus a link to `https://sheets.new` with `rel="noopener"`.
- **D4:** We do **not** embed a NED list. Codes written from memory would be fabricated data, so the user imports the official list instead.
  - The user imports a CSV/XLSX of the official list, with columns mapped for didstd and/or drug name.
  - It is stored in IndexedDB `rxmuk_ned` (`{savedAt, source, dids:[...], names:[...]}`).
  - Until a list is loaded, the badges and stat stay hidden, and a hint reads `ยังไม่ได้นำเข้าบัญชียาหลักแห่งชาติ`.

---

### Task D1: PWA Offline Mode
**Files:** Create `manifest.webmanifest`, `sw.js` and `icon.svg` (a simple 💊-style SVG). Modify `index.html` `<head>` and the init code.
- [ ] Manifest: `name` "RxMuk", `short_name` "RxMuk", `start_url` "./index.html", `display` "standalone", `background_color`/`theme_color` `#0b0f19`, and the SVG icon with `sizes:"any"`.
- [ ] `sw.js`:
  - Use `CACHE='rxmuk-v1'`. Precache `./`, `./index.html`, `./hospitals_db.js`, `./manifest.webmanifest`, `./icon.svg` and the cdnjs xlsx URL from index.html:13.
  - Add a network-first or cache-first router following the Rulings. On activate, delete old caches and call `clients.claim()`.
  - Precache failures of the CDN entry must not break install: use `Promise.allSettled`.
- [ ] Registration and status:
  - Register in `index.html` after load, following the Rulings guard.
  - Add an offline indicator: a small `#offline-badge` "ออฟไลน์" in the navbar, toggled by the `online`/`offline` events.
  - On `online`: if the cloud is configured and `await sbCloudIsNewer(cfg)`, call `supabasePullData(true)`. Debounce it to once per 30s.
- [ ] Verify: run `node --check sw.js`, and run `node --check` on the extracted main script. Then check in Playwright against `python -m http.server`:
  - the SW registers
  - after going offline (Playwright `context.setOffline(true)`) a reload still renders the app shell
  - the offline badge shows
- [ ] Commit `feat rxmuk: PWA offline mode (manifest + service worker)`.

### Task D2+D3: n8n Webhook + Google Sheets TSV
**Files:** `index.html`.
- [ ] D2 settings: add a small settings block in the Import tab containing:
  - the input `#n8n-url` (saved in `localStorage.rxmuk_n8n_url`)
  - "บันทึก" and "ทดสอบ" buttons
  - a status line
- [ ] Add `function sendImportWebhook(info)`, validated per the Rulings. Call it after a successful import in `processParsedExcelData`, after the `ok` point (not on the all-duplicate early return), and in `processPreAggregatedData`.
  - `months` comes from the new rows, `hospCount` = distinct hospcode in the new rows.
- [ ] Add `function isAllowedWebhookUrl(u) -> boolean`.
  - Node check: `https://x.y/webhook` true, `http://localhost:5678/webhook/a` true, `http://n8n:5678/x` true, `http://evil.com` false, `javascript:alert(1)` false, `ftp://x` false.
- [ ] D3: add `function tableToTsv(table) -> string`. It covers the header and the visible rows. Use cell `innerText` trimmed, with tabs and newlines inside cells replaced by spaces.
  - Add a button `📋 คัดลอกไป Google Sheets` next to each existing print button (`.print-keep`), or one global button in the navbar that targets the active tab's first visible `.data-table`. Ruling: one global navbar button, to avoid touching 12 tab headers.
  - Node check of `tableToTsv` on a fake table object: the header row, then 2 rows, with an embedded tab replaced.
- [ ] Commits `feat rxmuk: n8n webhook after import` and `feat rxmuk: copy active table as TSV for Google Sheets`.

### Task D4: NED (บัญชียาหลัก) Comparison
**Files:** `index.html`.
- [ ] Add an import block in the Import tab, `📚 นำเข้าบัญชียาหลักแห่งชาติ (NED)`, with:
  - a file input (xlsx/csv) and column mapping (didstd, name), reusing the C3 stock pattern and its helpers where sensible (e.g. `guessStockColumns`-style header guessing)
  - saving to `rxmuk_ned`
  - the status line `โหลดแล้ว N รายการ (วันที่)` and a clear button
- [ ] Add `function isOnNed(didstd, dname) -> boolean|null`. It returns null when no list is loaded. It matches didstd exactly, or else the normalised name (lowercase, collapsed spaces, strength/dosage tokens stripped — keep this simple and documented).
- [ ] Drug tab: when a list is loaded, add a badge `นอกบัญชี` next to the names of drugs not on NED, and a stat `X% มูลค่ายานอกบัญชี` (the off-list budget ÷ the total of the filtered drug list).
- [ ] RDU tab: add the same stat card.
- [ ] Load the NED list once at init, lazily (after first render, not blocking), into a module-level Set.
- [ ] Node check of `isOnNed`: did match, name match ignoring case, no list gives null.
- [ ] Commit `feat rxmuk: NED essential-drug-list comparison (user-imported list)`.

## Review Focus
- D1: the SW must never serve stale Supabase/PHP data, and must not break `reloadIfStale` or the login flow.
- D2: URL validation (SSRF-ish misuse is limited to the browser, but block non-http schemes), no PII in the payload, and the import is never blocked.
- D3: tables with drill buttons or inputs inside cells; empty tables.
- D4: XSS via the imported list, and no badges when no list is loaded.
