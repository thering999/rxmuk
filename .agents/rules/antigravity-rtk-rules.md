---
trigger: always_on
description: Apply at the start of a session.
---

# RTK PRIORITY

- Use `rtk <command>` whenever available (preferred default)
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
- read_grep → rtk run bash .agents/hooks/read_grep.sh

# TOKEN EFFICIENCY
- **PRIORITIZE TARGETED READS**: Always use `read_grep` to find specific line ranges instead of reading entire files.
- **MINIMAL CONTEXT**: Only ingest the minimum code necessary.

# CRITICAL DISCIPLINE
- **NO OVER-GENERALIZATION**: Use `rtk <command>` ONLY if the command is in the Proxies/Mappings list.
- **SYSTEM FALLBACK**: For commands NOT in the list (e.g., `mkdir`, `cp`, `python`, `bash`, `mv`, `rm`), run them as **PURE system commands** WITHOUT `rtk` or `rtk run`.
- **NO CAT/LS/GREP**: Never use pure `cat`, `ls`, or `grep` in any command block; always use `rtk read`, `rtk ls`, or `rtk grep` instead.