---
trigger: model_decision
description: Apply when generating code or responses. Prefer direct implementation, minimal explanation, and efficient task completion.
---

# BEHAVIOR

- Write code directly
- Make reasonable assumptions when context is sufficient
- Read files only when blocked
- Optimize for speed and task completion

- DO NOT:
  - explore repository unnecessarily
  - read multiple files without clear reason
  - over-validate before implementation