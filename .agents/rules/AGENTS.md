## ทำงานแบบ Loops
- เฝ้าดูไฟล์ PLANNER.md คอยดู Task ที่กำหนดให้ และดำเนินการตามนั้น
- ถ้ามีคำถามให้ตอบกลับที่ PLANNER.md แล้วรอ user ตอบกลับมาใหม่
- ให้ Test code หลังการแก้ไขเสร็จทุกครั้ง
- ให้ Run lintjs ทุกครั้ง หลังจากแก้ไขหรือสร้างไฟล์
- หากไม่สามารถดำเนินการใดได้ หรือ สิทธิ์ไม่ถึง
 ให้แจ้งใน PLANNER.md แล้วรอ user ตอบกลับมาใหม่


## Scope
- คุณคือ Coder Expert ด้าน NextJS , Typescript , Tailwind CSS , BunJS และ SQLite
- คุณคือ Fullstack Developer


## RTK (Rust Token Killer)

Always prefix shell commands with `rtk` to minimize token consumption. RTK filters and compresses command output before it reaches the LLM context, saving 60–90% on common operations.

### ใช้ rtk แทน bash commands เสมอ (รองรับ macosx, linux, windows)

| แทนที่ (Bash) | แทนที่ (Cmd/PowerShell) | ใช้ |
|---------------|------------------------|-----|
| `ls` | `dir` | `rtk ls` |
| `tree` | `tree` | `rtk tree` |
| `cat`, `head`, `tail` | `type`, `more` | `rtk read` |
| `grep`, `rg`, `ag` | `findstr`, `Select-String` | `rtk grep` |
| `find` | `dir /s /b`, `Get-ChildItem` | `rtk find` |
| `diff` | `fc`, `Compare-Object` | `rtk diff` |
| `wc` | `Measure-Object` | `rtk wc` |
| `wget`, `curl` | `Invoke-WebRequest`, `curl.exe` | `rtk wget`, `rtk curl` |
| `git ...` | `git ...` | `rtk git ...` |
| `gh ...` | `gh ...` | `rtk gh ...` |
| `aws ...` | `aws ...` | `rtk aws ...` |
| `docker ...` | `docker ...` | `rtk docker ...` |
| `kubectl ...` | `kubectl ...` | `rtk kubectl ...` |
| `npm run ...` | `npm run ...` | `rtk npm ...` |
| `npx ...` | `npx ...` | `rtk npx ...` |
| `pnpm ...` | `pnpm ...` | `rtk pnpm ...` |
| `jest ...` | `jest ...` | `rtk jest ...` |
| `vitest ...` | `vitest ...` | `rtk vitest ...` |
| `playwright ...` | `playwright ...` | `rtk playwright ...` |
| `tsc ...` | `tsc ...` | `rtk tsc ...` |
| `eslint ...` | `eslint ...` | `rtk lint` |
| `prettier ...` | `prettier ...` | `rtk prettier` |
| `prisma ...` | `prisma ...` | `rtk prisma ...` |
| `next ...` | `next ...` | `rtk next ...` |
| `dotnet ...` | `dotnet ...` | `rtk dotnet ...` |
| `cargo ...` | `cargo ...` | `rtk cargo ...` |
| `go ...` | `go ...` | `rtk go ...` |
| `pytest ...` | `pytest ...` | `rtk pytest ...` |
| `mypy ...` | `mypy ...` | `rtk mypy ...` |
| `ruff ...` | `ruff ...` | `rtk ruff ...` |
| `pip ...` | `pip ...` | `rtk pip ...` |
| `rubocop ...` | `rubocop ...` | `rtk rubocop ...` |
| `rspec ...` | `rspec ...` | `rtk rspec ...` |

**ห้ามใช้ commands เหล่านี้โดยตรง ห้าม fallback เป็น native commands แม้ rtk จะ fail:**

- **Bash:** `ls`, `tree`, `cat`, `head`, `tail`, `grep`, `find`, `diff`, `wc`, `wget`, `curl`, `git`, `gh`, `aws`, `docker`, `kubectl`, `npm`, `npx`, `pnpm`, `jest`, `vitest`, `tsc`, `eslint`, `prettier`, `cargo`, `go`, `pytest`, `mypy`, `ruff`, `pip`
- **Cmd:** `dir`, `type`, `more`, `findstr`, `fc`, `curl.exe`
- **PowerShell:** `Get-ChildItem`, `Get-Content`, `Select-String`, `Invoke-WebRequest`, `Compare-Object`, `Measure-Object`
- **ห้ามใช้ `&&` หรือ `||` เพื่อ chain native commands เป็น fallback ต่อจาก rtk**
- **ถ้า rtk ใช้ไม่ได้หรือ fail ให้แจ้ง user ทันที ห้าม fallback เองโดยเด็ดขาด**

### Utility commands

```bash
rtk smart <file>      # 2-line technical summary ของไฟล์
rtk summary <cmd>     # heuristic summary ของ output
rtk err <cmd>         # แสดงเฉพาะ errors/warnings
rtk test <cmd>        # รัน test แสดงเฉพาะ failures
rtk json <file>       # แสดง JSON แบบ compact
rtk deps              # สรุป project dependencies
rtk env               # แสดง environment variables (mask sensitive)
rtk log <cmd>         # filter และ deduplicate log output
rtk format <file>     # universal format checker
rtk pipe              # รับ stdin แล้ว filter output (Unix pipe mode)
rtk run <cmd>         # รัน shell command แบบ raw (ไม่ filter)
```

### Meta commands (รันตรง ไม่ผ่าน hook)

```bash
rtk gain              # Show token savings analytics
rtk gain --history    # Command history with savings
rtk discover          # Find missed RTK opportunities
rtk session           # RTK adoption across sessions
rtk cc-economics      # Claude Code spending vs RTK savings analysis
rtk proxy <cmd>       # Raw execution without filtering (for debugging)
rtk config            # Show/create configuration file
rtk verify            # Verify hook integrity and TOML filter tests
rtk rewrite <cmd>     # แสดง RTK equivalent ของคำสั่ง
```

## Repository Layout

This repo stores AI agent configuration only — no application source code.

- `.agents/rules/` — agent rule files (e.g. RTK usage rules)
- `.claude/` — Claude Code project settings

## Planner Workflow

เมื่อได้รับ context ว่า `PLANNER.md` มีการเปลี่ยนแปลง หรือ user สั่ง `/project:planner` ให้ทำตามลำดับนี้เสมอ:

1. อ่าน `.claude/planner/PLANNER.md` ทั้งหมด
2. แยก tasks ออกมาทีละรายการ
3. ดำเนินการแต่ละ task ตามลำดับที่ระบุ
4. รายงานผลเมื่อแต่ละ task เสร็จ
5. เขียน report สรุปลงไฟล์ `.claude/planner/report/REPORT-[date].md`
6. แจ้ง user ว่า report อยู่ที่ไหน

### รูปแบบ PLANNER.md ที่รองรับ
- Markdown checklist `- [ ] task`
- Numbered list `1. task`
- Header sections `## Task: ...`

### Report format
| Field | รายละเอียด |
|-------|-----------|
| Summary | สถานะภาพรวมของทุก task |
| Tasks | ตารางแสดง task, status (✅ ❌ ⚠️), และ notes |
| Errors | error ที่พบระหว่างดำเนินการ |
| Next Steps | task ที่ค้างอยู่หรือต้องทำต่อ |

### ข้อห้ามใน Planner Workflow
- ห้ามข้าม task ใดๆ โดยไม่มีเหตุผล
- ห้ามเขียน report ก่อนดำเนินการ tasks ครบทุกข้อ
- ถ้า task ใด fail ให้บันทึกใน report แล้วทำ task ถัดไปต่อ อย่าหยุด


## RTK (Rust Token Killer)
Always prefix shell commands with `rtk`. Pattern: `<cmd> → rtk <cmd>` (e.g. `rtk ls`, `rtk git status`, `rtk grep`).
**PROHIBITED (never run raw):** `grep`, `cat`, `ls`, `find`, `tree`, `git`, `gh`, `npm`, `npx`, `pnpm`, `bun`, `docker`, `curl`, `wget`, `tsc`, `eslint`, `prettier`, `prisma`, `next`
Never fallback to native commands. If rtk fails, notify user immediately — do not run the raw command.

## File Operations
- **Reading:** Use `rtk smart <file>` to understand a file — avoid `Read` (full dump) unless full content is needed.
- **Editing:** Use `Edit` (line-based) for partial edits — avoid `Write` (full rewrite). Use `Write` only for new files.

## Repository Layout
- `.agents/rules/` — agent rule files
- `.claude/` — Claude Code project settings

## Planner Workflow
When PLANNER.md changes or user runs `/project:planner`:
1. Read `.claude/planner/PLANNER.md` in full
2. Execute each task in order
3. Write report to `.claude/planner/report/REPORT-[date].md`
4. Notify user of report location

Report fields: Summary, Tasks (status ✅❌⚠️ + notes), Errors, Next Steps.
Rules: Never skip tasks. If a task fails, log it and continue — never stop.


# RTK PRIORITY

- Use rtk <command> whenever available (preferred default)
- If not available:
  - use MCP tools first
  - then fallback to bash/system commands
- Use built-in skills ONLY for complex edits requiring exact positions

## Proxies
ls, tree, read, git, gh, aws, psql, pnpm, env, find, diff, log, dotnet, docker, kubectl, grep, wget, wc, vitest, prisma, tsc, next, lint, prettier, format, playwright, cargo, npm, npx, curl, pytest, mypy, rake, rubocop, rspec, pip, go, golangci-lint

## Mappings
- view_file → rtk read
- list_dir → rtk ls
- grep_search → rtk grep


# CRITICAL DISCIPLINE
- **NO OVER-GENERALIZATION**: Use rtk <command> ONLY if the command is in the Proxies/Mappings list.
- **SYSTEM FALLBACK**: For commands NOT in the list (e.g., `mkdir`, `cp`, `python`, `bash`, `mv`, `rm`), run them as **PURE system commands** WITHOUT rtk or `rtk run`.
- **NO CAT/LS/GREP**: Never use pure `cat`, `ls`, or grep in any command block; always use `rtk read`, `rtk ls`, or rtk grep instead.