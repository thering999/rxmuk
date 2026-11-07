# Phase B Analytics Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Add the Phase B analytics: outlier alerts per unit, a seasonal pattern chart, the generic drug ratio, and antibiotic DDD/1000.

**Architecture:** Each feature is one function block in `index.html`, called from the render function of the tab it extends. No new files and no libraries. Charts are inline SVG.

**Tech Stack:** vanilla ES2020, inline SVG, existing helpers (`getFilteredTransactions`, `escapeHtml`, `drillBtn`, `isAntibiotic`).

**Spec:** `docs/superpowers/specs/2026-11-06-rxmuk-full-features-design.md` (Phase B)

## Global Constraints
- Single file `index.html`. Raw ES2020. No new CDN libraries.
- Thai UI strings, English code.
- `escapeHtml()` on every imported string put into innerHTML. No inline `onclick` with interpolated data.
- No regression in the existing 12 tabs. Cold load must not grow by >500ms. No extra full `rawTransactions` scans on initial render beyond what the feature's own tab needs.

## Rulings
- **B1** is skipped. The spec's Open Builder Call 6 says the YoY tab already supports N years.
- **B4** is skipped. `renderDistrictBreakdown()` (index.html:4752) already shows budget per 1,000 population. That is cost per capita.
- **B2** uses the current filter selection as "current month". The user picks a month in the filter; with "all", it compares over the whole selection.
- **B3** ignores the year/month/date filters, because seasonality needs every year. It still respects the district/hosp filters.
- **B5** is a name heuristic and is labelled "ประมาณการ" in the UI.
- **B6** parses strength (mg) from `dname`. It assumes `amount` counts dosage units (tablets, capsules or vials). When a drug can't be parsed, the cell shows `–`.

---

### Task B2: Outlier Alert per Unit
**Files:** Modify `index.html`: `renderHospBreakdown()` (~4788).
**Produces:** `computeUnitOutliers(list) -> Map<hospcode, {pct:number, dir:'high'|'low'}>`

- [ ] `computeUnitOutliers(list)` takes the hosp breakdown's aggregated rows (each has `hospcode`, budget, and district code; reuse whatever fields the aggregation has). It groups rows by district.
  - Districts with fewer than 3 units, or with stddev 0, are skipped.
  - It uses the population stddev.
  - A unit is flagged when `|budget - mean| > 2 * stddev`. `pct = (budget - mean) / mean * 100`.
- [ ] In the hosp table name cell, append a flag `<span class="outlier-flag" title="...">`:
  - high: `⚠️`, title `สูงกว่าค่าเฉลี่ยอำเภอ ${pct.toFixed(0)}%`
  - low: `🔻`, title `ต่ำกว่าค่าเฉลี่ยอำเภอ ${Math.abs(pct).toFixed(0)}%`
- [ ] Add a stat or line above the table: `พบหน่วยบริการผิดปกติ N แห่ง (±2 SD จากค่าเฉลี่ยอำเภอ)`.
- [ ] Unit check in node, using an extracted copy of the function:
  - budgets [100,100,100,100,100,100,1000] in one district: the 1000 unit is flagged high.
  - 2 units: none flagged.
  - all equal: none flagged.
- [ ] Commit `feat rxmuk: flag outlier units vs district mean (±2 SD)`.

### Task B3: Seasonal Pattern Analysis
**Files:** Modify `index.html`: the drugs tab section and `renderDrugBreakdown()` (~4489).
**Produces:** `computeSeasonalProfile(didstd) -> number[12]` (index 0 = Oct … 11 = Sep, fiscal order), `renderSeasonalChart()`

- [ ] Add a panel inside `#tab-drugs` titled `📆 รูปแบบตามฤดูกาล (Seasonal Pattern)`, with a drug `<select id="seasonal-drug-select">` and `<div id="seasonal-chart">`.
  - Options: the top 50 drugs by budget in the current drug-tab list. Default is the first.
- [ ] `computeSeasonalProfile(didstd)`:
  - Source is the rows in `rawTransactions` for that didstd, filtered only by the drug tab's district/hosp select values.
  - For each calendar month, average Σamount over the distinct years that have data in that month.
- [ ] `renderSeasonalChart()` draws a 12-bar inline SVG with Thai month labels in fiscal order (ต.ค. … ก.ย.).
  - Bars above `mean * 1.2` are orange (`var(--c-yellow)`) and peaks; other bars use `var(--c-primary)`.
  - Each bar's `<title>` shows its value.
  - If no month has data: `ไม่มีข้อมูล`. If only one year is loaded, add the note `ข้อมูลปีเดียว — รูปแบบฤดูกาลอาจไม่แม่น`.
- [ ] Re-render on select change, and at the end of `renderDrugBreakdown()` (keep the current selection if it is still in the options).
- [ ] Commit `feat rxmuk: seasonal usage pattern chart in drug tab`.

### Task B5: Generic Drug Ratio
**Files:** Modify `index.html`: the RDU tab HTML and `renderRduMonitoring()` (~7351).
**Produces:** `isLikelyBrandName(dname) -> boolean`

- [ ] `isLikelyBrandName(dname)` returns true when any of these hold:
  - it contains `®` or `™`
  - it contains a parenthesised word starting with an uppercase Latin letter, e.g. `Paracetamol (Tylenol)`
  - its first Latin word is ALL CAPS, at least 4 letters, and not a dosage token (TAB, CAP, INJ, SYR, SUSP, CREAM, OINT, DROP, SOLN, AMP, VIAL)
  - otherwise false.
- [ ] Add a card to the RDU stats bar: `💊 สัดส่วน Generic (ประมาณการ)`, the province % of distinct didstd that are generic, among the filtered rows.
- [ ] Add a panel and table: per unit, `รหัส | หน่วยบริการ | จำนวนรายการยา | Generic % `. Sort ascending by %, show 10 rows. Escape names.
- [ ] Node unit check:
  - `Paracetamol 500 mg TAB`: false
  - `Paracetamol (Tylenol) 500mg`: true
  - `AUGMENTIN 1 g`: true
  - `Amoxicillin® 500`: true
  - `TAB Amoxicillin`: false
- [ ] Commit `feat rxmuk: generic drug ratio in RDU tab`.

### Task B6: Antibiotic DDD/1000 Prescriptions
**Files:** Modify `index.html`: the RDU antibiotic table header (~3160-3170) and its rows (~7388-7404).
**Produces:** `ANTIBIOTIC_DDD` table, `parseStrengthMg(dname) -> number|null`, `antibioticDddPer1000(rows) -> number|null`

- [ ] `const ANTIBIOTIC_DDD = [{re:/amoxicillin.*clav|augmentin|co-amoxiclav/i, g:1.5}, {re:/amoxicillin/i, g:1.5}, {re:/ampicillin/i, g:6}, {re:/dicloxacillin/i, g:2}, {re:/cloxacillin/i, g:2}, {re:/penicillin v|phenoxymethyl/i, g:2}, {re:/cephalexin|cefalexin/i, g:2}, {re:/cefazolin/i, g:3}, {re:/ceftriaxone/i, g:2}, {re:/cefixime/i, g:0.4}, {re:/cefdinir/i, g:0.6}, {re:/ciprofloxacin/i, g:1}, {re:/norfloxacin/i, g:0.8}, {re:/ofloxacin/i, g:0.4}, {re:/levofloxacin/i, g:0.5}, {re:/azithromycin/i, g:0.3}, {re:/clarithromycin/i, g:0.5}, {re:/roxithromycin/i, g:0.3}, {re:/erythromycin/i, g:1}, {re:/doxycycline/i, g:0.1}, {re:/tetracycline/i, g:1}, {re:/clindamycin/i, g:1.2}, {re:/gentamicin/i, g:0.24}, {re:/metronidazole/i, g:2}]` (WHO ATC/DDD, first match wins; order matters).
- [ ] `parseStrengthMg(dname)`:
  - takes the first `(\d+(?:\.\d+)?)\s*(mg|g)\b`; g is ×1000.
  - combination strings like `500/125 mg` use the first number.
  - returns null when nothing matches.
- [ ] Per antibiotic didstd: `DDDs = Σamount * strengthMg / (g*1000)`, then `DDD/1000 = DDDs / (rowCount/1000)`.
  - `–` when there is no DDD match or no strength.
  - Add a column header `DDD/1000 ใบสั่งยา` and the value, 1 decimal.
  - Fix the empty-state colspan (3 → 4).
- [ ] Add a footnote under the table: `DDD ตาม WHO ATC/DDD · คำนวณจากความแรงในชื่อยา · ประมาณการ`.
- [ ] Node unit check:
  - `parseStrengthMg('Amoxicillin 500 mg CAP')`: 500
  - `'Ceftriaxone 1 g INJ'`: 1000
  - `'Augmentin 500/125mg'`: 500
  - `'Syrup'`: null
  - 10 rows of Amoxicillin 500 mg, amount 30 each: DDDs = 300*500/1500 = 100; DDD/1000 = 100/(10/1000) = 10000.
- [ ] Commit `feat rxmuk: antibiotic DDD/1000 prescriptions in RDU tab`.

## Review Focus
- B2: districts with very few units or zero variance must produce no flags and no NaN.
- B3: a drug present in only one year, and months with no data, must show 0 bars, not NaN.
- B5: Thai-only drug names (no Latin letters) must count as generic, not crash.
- B6: injections/syrups without mg strength must show `–`, not Infinity.
- All: XSS via imported drug/unit names.
