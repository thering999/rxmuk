---
trigger: always_on
---

# FILE ACCESS RULES

- **PRIORITIZE GRAPHIFY**: If `graphify-out/graph.json` exists or `graphify` MCP tools are available, use them FIRST for any architecture exploration or finding component relationships.
- DO NOT scan repository broadly (no `rtk tree`, `ls -R`, or `grep` without clear purpose).
- Use `graphify` MCP tools like `query_graph`, `get_neighbors`, or `god_nodes` to identify target files before reading.
- Read files ONLY when necessary (Except files in `.agents/rules/*.md`).
- Prefer minimal reading of specific files or known paths.
- Before reading: "Can I find this information via the Knowledge Graph?" 
  - YES → Use Graphify
  - NO → Read minimum required file content.