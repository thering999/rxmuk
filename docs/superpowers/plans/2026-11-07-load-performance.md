# Load Performance Plan

**Goal:** Cut the wait on first load, on cloud refresh and on every page open, with about 728k rows loaded.

## Findings (from code)
1. `refreshAllUI()` (~3573) renders 11 tabs and panels, and each one scans all rows. The DOMContentLoaded init renders about 9 tabs again. `_supabasePullImpl` calls `refreshAllUI()` and then `populateAllFilterDropdowns()` a second time. In total there are about 20+ full scans before the user can interact.
2. `sbDownloadAll` downloads the month files one after another (`for … await`).
3. Every cloud-newer refresh downloads the whole fiscal year, even when only one month changed. `meta.json` has a per-file `at` timestamp, but nothing uses it for caching.
4. The whole array is written to IndexedDB as one value (`idbSet('rxmuk_raw_data', …)`). The structured clone of 728k objects blocks the main thread.
5. Gunzip and `JSON.parse` of each file run on the main thread.

## Tasks (in order; measure after each)
- **P0 Measure:** add `performance.mark/measure` around these steps, logged to the console only, not on screen:
  - download (per file)
  - gunzip+parse
  - idb write
  - populate filters
  - each render
  - total time to interactive
  Keep the marks: they're cheap and help future checks.
- **P1 Render only what's visible:**
  - `refreshAllUI()` becomes: `updateNavDataStatus(); populateAllFilterDropdowns(); renderTab(activeTabName());`, plus `renderDrugOverviewPanel()` only when its tab is the one being shown.
  - Other tabs render on `switchTab` as they do today, since `renderTab` already runs there.
  - Collapse the init render list to a single `renderTab(activeTabName())`.
  - Remove the duplicate `populateAllFilterDropdowns` in the pull.
  - Make sure every call site that relied on all tabs being drawn still works:
    - print
    - global search badges
    - `drillTo`
    - the C3 stock section
    - the B3 seasonal chart
    - the C1 forecast (dashboard only)
- **P2 Parallel download:** fetch the month files with a concurrency of 4, keep the result order stable, and keep the progress text as an aggregate.
- **P3 Per-month cache and delta fetch:**
  - Cache each month file's parsed rows in IndexedDB under the key `rxmuk_month_<name>` as `{at, rows}`.
  - When `meta.files[i].at` matches the cache, use the cache; otherwise download it. Delete cache entries for files that no longer appear in the meta.
  - Keep `rxmuk_raw_data` as the assembled fallback, or replace it with the per-month store if every reader goes through one loader.
  - This makes "cloud newer" download only the changed months, and reload instant when nothing changed.
- **P4 Worker parse:** gunzip + `JSON.parse` in a Blob-URL Worker (DecompressionStream works in workers). Return the parsed rows, falling back to the main thread when Worker creation fails.
  - Note: the structured-clone transfer back still costs time on the main thread. Keep P4 only if P0 shows parse is the bottleneck.
- **P5 Yield to paint:** between heavy steps, `await new Promise(requestAnimationFrame)` so the progress bar and text update. Show step text: `กำลังแตกไฟล์…`, `กำลังบันทึกในเครื่อง…`, `กำลังสร้างตาราง…`.

## Constraints
- No behaviour change in data content.
- No regression in the A–D features.
- `escapeHtml` rules unchanged.
- Measure before and after on synthetic 728k rows in Playwright, without using the real Supabase. Report the timings.
