---
trigger: model_decision
description: Apply at the start of a session.
---

# SKILL PROTOCOL

- DO NOT load skills by default
- ONLY load when:
  - user specifies [skill:...]
  - task clearly requires a known skill
- If unsure → DO NOT load

- Use only specific skill files (no directory scan)
- Never mix parent/child skills
- Skip for generic tasks or [no-skill]