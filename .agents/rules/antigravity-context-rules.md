---
trigger: model_decision
description: Apply at the start of a session.
---

# CONTEXT RULES

- Use context from [AGENT_CONTEXT_START] block as source of truth.
- If [AGENT_CONTEXT_START] is missing at session start, you MUST run /awake workflow immediately to inject it.

- DO NOT:
  - read .agents/memories (unless via /awake)
  - reload or re-verify context
  - search for additional context unnecessarily