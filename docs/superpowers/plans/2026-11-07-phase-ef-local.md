# Phase E/F (local-only part) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development.

**Goal:** Ship the two Phase E/F features that need no shared state and no real authentication: E4 (monthly report) and F3 (favorites).

**Spec:** `docs/superpowers/specs/2026-11-06-rxmuk-full-features-design.md` (E4, F3)

## Why only these
The current login is client-side only. The credentials are hard-coded in `index.html` (`admin`/`123456`, `rxmuk`/`123456`), and anyone can set `localStorage.rxmuk_auth_user`.

E1 (shared annotations), E2 (roles), E3 (shared audit log), E5 (public snapshot), F1 and F2 all depend on knowing who the user is, or on writing shared data. These are blocked until real auth exists (Supabase Auth + RLS) and the user decides on it.

## Global Constraints
- Single `index.html`, raw ES2020, no new libraries, Thai UI, English code.
- Use `escapeHtml()` for imported strings. No inline `onclick` with interpolated data.
- No regression and no cold-load growth.
- Commit through the PowerShell tool, with `-m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"`.

## Rulings
- **E4:** Use an HTML report printed through the browser (Save as PDF) instead of jsPDF. jsPDF has no Thai complex-script shaping, so vowels and tone marks render misplaced, and an embedded font would add a large file. This matches the spec's intent of a "formatted multi-page PDF".
- **F3:** Favorites are stored per browser in `localStorage.rxmuk_favorites = {drugs:[didstd...], units:[hospcode...]}`. Arrays are used because JSON can't store a Set. Each list holds at most 20.

---

### Task E4: Monthly Report
**Files:** `index.html`
**Produces:** `buildMonthlyReportHtml(data) -> string` (pure), `openMonthlyReport()`

- [ ] Add a dashboard button `📄 สร้างรายงานประจำเดือน` with class `no-print`.
- [ ] `openMonthlyReport()` collects data from the current filters: `getFilteredTransactions('drug')`, plus the year/month currently selected. It then opens `window.open('', '_blank')`, writes the full report document, and calls `print()` after the document loads.
  - If the popup is blocked, show the toast `เบราว์เซอร์บล็อกหน้าต่างใหม่ — อนุญาต popup แล้วลองอีกครั้ง`.
- [ ] `buildMonthlyReportHtml(data)` returns a self-contained HTML document with `<meta charset>`, the Sarabun font from Google Fonts, and an A4 print CSS (`@page { size: A4; margin: 15mm }`). The report has these sections:
  1. Cover: `รายงานการใช้ยา จังหวัดมุกดาหาร`, the period text (year/month or "ทั้งหมด"), the generation date (Thai locale), and the 💊 mark. Add `page-break-after`.
  2. Executive summary: total budget, item count, unit count, burn %, and the C1 forecast line if available. Reuse `forecastFiscalYear` if present.
  3. Top 20 drugs by budget: rank, name, amount, budget, %.
  4. District breakdown: district, units, budget, %, and budget per 1,000 population (`MUKDAHAN_POPULATION`).
  5. RDU indicators: antibiotic %, using `isAntibiotic(didstd, dname)` the same way the RDU tab does, and the generic % (`isLikelyBrandName`).
  6. Data quality: rows with missing/invalid didstd and zero price, plus an overall score using the same rules as `renderDataQualityScorecard`.
  - Every name passes through `escapeHtml`.
  - Number formatting uses `toLocaleString('th-TH')`.
- [ ] Node check of `buildMonthlyReportHtml` with a small fixture:
  - it contains all 6 section headings
  - it escapes `<script>` in a drug name
  - the top-20 list is capped at 20
- [ ] Commit `feat rxmuk: printable monthly report (browser PDF)`.

### Task F3: Bookmark Favorites
**Files:** `index.html`, in `renderDrugBreakdown` and `renderHospBreakdown`.
**Produces:** `getFavorites()`, `toggleFavorite(kind, id) -> boolean` (returns false when the list is full), `sortFavoritesFirst(list, kind, idField)`

- [ ] Add a star button `☆` / `★` (gold `#fbbf24`) as the first cell of each drug and unit row, with `aria-label` `ติดตาม` or `เลิกติดตาม`.
  - Use one delegated click listener per table body, bound once, and call `e.stopPropagation()` so the existing row click doesn't fire.
- [ ] Pinned rows render first, in their existing order. Above each table, add a `รายการที่ติดตาม (N)` line, shown only when N > 0.
- [ ] At the limit of 20, adding another shows the toast `ติดตามได้สูงสุด 20 รายการ`.
- [ ] Keep the column counts consistent: header, rows and every empty-state colspan each get +1. Search for every `colspan=` in those two tables.
- [ ] Node check:
  - toggle add/remove
  - the 21st add is refused
  - sorting keeps the relative order inside the pinned group and inside the unpinned group
  - corrupt localStorage JSON resets to empty
- [ ] Commit `feat rxmuk: favorite drugs and units pinned to top`.

## Review Focus
- E4: a popup blocker; empty data (a report with zeros, not a crash); XSS inside the new window's document.
- F3: the star click must not trigger drill-down; global search combined with favorites; column count alignment.
