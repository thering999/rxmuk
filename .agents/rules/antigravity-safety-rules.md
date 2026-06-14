---
trigger: model_decision
description: Apply when create files or folders, use unsafe shell expansion or generate ambiguous file paths
---

# SAFETY RULES

- DO NOT:
  - create files or folders with wildcard (*, ?, [])
  - use unsafe shell expansion
  - generate ambiguous file paths

- MUST:
  - use explicit filenames
  - avoid destructive commands unless required