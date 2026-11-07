# Phase C Planning Tools Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development.

**Goal:** Phase C covers a budget forecast, a VED reclassification simulator, stock-days estimation from an imported stock sheet, a procurement calendar, and a co-prescription heatmap.

**Architecture:** Each feature is one delimited function block in `index.html`. Charts are inline SVG and HTML. Per-browser state lives in `localStorage`, and stock data lives in IndexedDB via the existing `idbGet`/`idbSet`. C5 runs in a Web Worker built from a Blob URL.

**Spec:** `docs/superpowers/specs/2026-11-06-rxmuk-full-features-design.md` (Phase C)

## Global Constraints
- Single file `index.html`. Raw ES2020. No new libraries; SheetJS `XLSX` is already loaded.
- Thai UI, English code.
- Use `escapeHtml()` on every imported string that goes into innerHTML. Never use an inline `onclick` with interpolated data; use `data-*` plus `addEventListener`.
- Bind listeners once, and create modals once.
- No regression in the existing tabs. Cold load must not grow: nothing new computes until its tab or panel is shown.
- Commit with git through the PowerShell tool. End every commit with `-m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"`.

## Rulings
- **C1 data:** C1 uses the dashboard's filtered transactions, the same `txs` that `updateDashboardOverview` uses. If no year is selected, it uses the latest fiscal year present.
- **C1 regression:** x = fiscal month index (Oct=0…Sep=11). Fewer than 3 months gives `ข้อมูลไม่พอสำหรับคาดการณ์`.
- **C2 storage:** C2 never mutates `drugVedMap`. The simulation is stored separately and only shown in the modal.
- **C2 accessibility:** HTML5 drag and drop is not keyboard-accessible, so every card also has a `<select>` V/E/D/ไม่ระบุ that moves it.
- **C3 scope:** Stock is province-level per `didstd` (sum if repeated). It is stored only in this browser (IndexedDB key `rxmuk_stock`) and never pushed to the cloud.
- **C4 granularity:** C4 is per drug, not per category; the spec's "category" has no data source. Cards are coloured by VED class.
- **C5 placement:** C5 lives as a panel in the RDU tab, not as a new tab. The panel is hidden when no row has a usable `pid`.

---

### Task C1: Budget Forecast
**Files:** `index.html`. Add a dashboard panel inside `#tab-dashboard` after the stats grid, and call it from `updateDashboardOverview()` (~5989).
**Produces:** `forecastFiscalYear(monthTotals: number[12|null]) -> {projected:number, band:number, used:number, monthsUsed:number} | null`

- [ ] `forecastFiscalYear(monthTotals)` takes `monthTotals[i]` = total budget for fiscal month i, or `null` if not loaded.
  - Fit ordinary least squares y = a + b·x on the non-null months.
  - `used` = sum of the actual months.
  - For each null month after the last actual month, add max(0, a + b·x) to the projection.
  - `residualSD` = population SD of (y − fit) over the actual months.
  - `band` = residualSD × (number of projected months).
  - Return `null` when fewer than 3 actual months.
- [ ] Add a panel `📈 คาดการณ์งบสิ้นปีงบประมาณ` with:
  - the text `คาดว่าจะใช้งบ ${projected} ฿ ณ สิ้นปีงบประมาณ (±${band/projected*100}%)`
  - a line `เทียบงบประมาณที่ตั้งไว้ ${annual} ฿: ${projected/annual*100}%` (annual = `window.rxmukAnnualBudget || 250000000`, same as the burn-rate code)
  - a small inline SVG with actual months as a solid line and projected months as a dashed line plus a shaded band
- [ ] Node check:
  - [10,10,10,null×9]: projected 120, band 0
  - [10,20,30,null×9]: b=10, so the projection adds 40+…+120 = 720, used 60, total 780
  - [10,20,null×10]: returns null
  - all 12 actual: projected = used, band 0
- [ ] Commit: `feat rxmuk: year-end budget forecast on dashboard`

### Task C2: VED Simulation Modal
**Files:** `index.html`. Add a button `🧪 จำลองการจัดกลุ่ม VED` in the ABC-VED tab, plus a modal.
**Produces:** `openVedSimulation()`, `vedSimSummary(drugs, overrides) -> {cat1, cat2, cat3, unclassified}` (each `{count, budget}`, using the existing `abcVedCategory(abc, ved)` mapping)

- [ ] Data comes from the current ABC-VED computation: the top 100 drugs by budget with their `abc` and current `ved`. Refactor `renderAbcVedMatrix` minimally so the list is reusable, e.g. store `_abcVedDrugList`.
- [ ] Modal layout:
  - 4 columns: V / E / D / ไม่ระบุ.
  - Draggable cards show the name, ABC letter and budget.
  - Each card has a `<select>` that moves it too.
  - A summary bar shows budget per category 1/2/3 and unclassified, before → after, with the Δ ฿ highlighted.
- [ ] Buttons:
  - `บันทึกการจำลอง` saves `{didstd: ved}` overrides to `localStorage.rxmuk_ved_sim`, wrapped in try/catch.
  - `รีเซ็ต` clears the overrides.
  - On open, load the saved overrides that apply to the listed drugs.
- [ ] The modal closes with ✕, a backdrop click, or Esc. It is created once.
- [ ] Node check of `vedSimSummary`:
  - Moving an A-class drug from '' to 'V' moves its budget from unclassified to cat1.
  - With no overrides, the result equals the current matrix totals.
- [ ] Commit: `feat rxmuk: VED reclassification simulator`

### Task C3: Stock Import + Stock Days
**Files:** `index.html`. Add a section `📦 นำเข้าสต็อก & ประมาณวันคงคลัง` inside `#tab-import` (~1554).
**Produces:** `parseStockRows(rows, didCol, nameCol, qtyCol) -> Map<key, qty>`, `computeStockDays(stockMap, txs) -> [{didstd, name, stock, avgDaily, days}]`, `loadStock()`, `saveStock(map)`

- [ ] File input (.xlsx/.xls/.csv) is parsed with `XLSX`, using sheet 1 and `sheet_to_json`.
- [ ] Column-mapping UI: three selects, รหัสยา (didstd), ชื่อยา, and คงคลัง. Auto-guess them by header text (did/รหัส, name/ชื่อ, stock/qty/คงเหลือ/จำนวน).
  - Matching uses didstd when it is mapped. Otherwise it matches the lowercased trimmed name against `dname`.
  - Quantities that are not numeric or are negative are skipped. Show `นำเข้า N รายการ, ข้าม M`.
- [ ] `saveStock` writes `{savedAt, items:[[key,qty],...]}` to IndexedDB `rxmuk_stock`.
- [ ] `computeStockDays`:
  - Usage window = the last 3 distinct months present in `rawTransactions`, or all of them if there are fewer than 3.
  - `days in window` = the sum of calendar days of those months. Convert BE years with y>2400 by subtracting 543.
  - `avgDaily = Σamount(window) / daysInWindow`.
  - `days = stock / avgDaily`. Use `Infinity` when avgDaily is 0, and display it as `ไม่มีการใช้`.
- [ ] Table `รหัส | ชื่อยา | คงคลัง | ใช้เฉลี่ย/วัน | เหลืออีก (วัน)`:
  - Sort ascending by days.
  - Colours: red <30, yellow 30–90, green >90.
  - Also show a row for stock items that match no transaction, with usage `–`.
- [ ] Node check:
  - Stock 300, usage 30/day over the window gives 10 days (red).
  - Zero usage gives Infinity.
  - Header auto-guess works for `รหัสยา,ชื่อยา,คงเหลือ`.
- [ ] Commit: `feat rxmuk: stock import and stock-days estimation`

### Task C4: Procurement Calendar
**Files:** `index.html`, below the C3 section.
**Consumes:** `computeStockDays` (C3). **Produces:** `reorderDates(stockDays, leadDays, today) -> [{didstd, name, date: Date, overdue: bool}]`, `buildIcs(items) -> string`

- [ ] A lead-time input (default 30, min 0) is persisted in `localStorage.rxmuk_lead_days`.
- [ ] `reorderDates`:
  - `date = today + days - leadDays`, floored to the day.
  - `overdue = date < today`.
  - Items with Infinity days are excluded.
- [ ] Calendar:
  - A 3-month grid (current month + 2), Mon–Sun, with Thai month names.
  - Each day lists its drugs as small chips coloured by VED class (`getDrugVed`).
  - Overdue items go in a red list `ต้องสั่งซื้อแล้ว` above the grid.
  - Chips are escaped, with no inline handlers.
- [ ] Export buttons:
  - CSV (`didstd,name,reorder_date`) via a Blob download.
  - iCal `.ics`: one all-day VEVENT per item. Use `DTSTART;VALUE=DATE:YYYYMMDD`, `SUMMARY:สั่งซื้อ <name>`, and a UID from didstd+date. Escape `,;\` and newlines per RFC 5545, and use CRLF line endings.
- [ ] Node check:
  - today 2026-11-07, days 40, lead 30 gives 2026-11-17.
  - days 10, lead 30 is overdue.
  - The ICS contains `BEGIN:VCALENDAR`, CRLF line endings, and an escaped comma.
- [ ] Commit: `feat rxmuk: procurement calendar with CSV/iCal export`

### Task C5: Co-Prescription Heatmap
**Files:** `index.html`. Add a panel in the RDU tab.
**Produces:** `buildCoPrescription(rows, topN) -> {drugs:[{didstd,name}], matrix:number[][]}` (pure, also used as the worker body)

- [ ] Panel `🔗 ยาที่สั่งร่วมกัน (Co-prescription)`:
  - Hidden when no filtered RDU row has a `pid` other than empty or 'Unknown'.
  - Has a button `คำนวณ` and a status line.
- [ ] `buildCoPrescription`:
  - Picks the top N=50 didstd by row count.
  - Groups rows by `pid|month`, using unique didstd per group, restricted to the top N.
  - For each pair i<j in a group, increments matrix[i][j] and matrix[j][i]. The diagonal is 0.
- [ ] Worker:
  - Build `new Worker(URL.createObjectURL(new Blob([...])))` with the function source via `buildCoPrescription.toString()`.
  - Post only the slim rows `{d: didstd, n: dname, p: pid, m: month}`.
  - If Worker creation fails, fall back to the main thread.
  - Cache the result keyed by `rawTransactions` ref + length + the RDU filter values, and terminate the worker after it finishes.
- [ ] Render:
  - An HTML table of the top 20 by total co-occurrence, with short drug names in the header and the full name in `title`.
  - Cell colour is the intensity scaled to the max, with the count in `title`.
  - Under the table, list the top 10 pairs as text.
- [ ] Node check: a 3-pid synthetic fixture with known pair counts, a single-drug group (no pairs), and duplicate rows of the same drug in one group (no self-pair).
- [ ] Commit: `feat rxmuk: co-prescription heatmap in RDU tab`

## Review Focus
- C1: months with zero spend count as real 0s, while missing months are null. Projected values are never negative.
- C3: a stock file with Thai headers or extra blank rows; a drug present in stock but never dispensed.
- C4: a reorder date in a month beyond the 3-month grid still appears in the CSV/ICS.
- C5: a large dataset (~728k rows) must not freeze the UI; aggregated imports without pid hide the panel.
- All: XSS via imported names, both in the stock file and in transactions.
