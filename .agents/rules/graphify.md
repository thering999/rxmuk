## graphify

This project has a graphify knowledge graph at graphify-out/.

Rules:
- Before answering architecture or codebase questions, read graphify-out/GRAPH_REPORT.md for god nodes and community structure
- If graphify-out/wiki/index.md exists, navigate it instead of reading raw files
- If the graphify MCP server is active, utilize tools like `query_graph`, `get_node`, and `shortest_path` for precise architecture navigation instead of falling back to `grep`
- If the MCP server is not active, the CLI equivalents are `graphify query "<question>"`, `graphify path "<A>" "<B>"`, and `graphify explain "<concept>"` — prefer these over grep for cross-module questions
- After modifying code files in this session, run `graphify update .` to keep the graph current (AST-only, no API cost)
- **MEMORY & DOCS**: For session memories or documentation files, ALWAYS use **Standard Markdown Links** `[label](path)` to define relationships to code.
- **DETERMINISTIC UPDATES**: When updating the graph with memories, prefer deterministic parsing of links over AI-based semantic guessing.
- **CONFIDENCE LABELS**: Use standard labels: `EXTRACTED` (for explicit links), `INFERRED` (for semantic deductions), and `AMBIGUOUS` (for uncertain links).
- **FEEDBACK LOOP**: Use `graphify save-result` to record important AI findings back into the knowledge graph's memory.
- **NODE ID ACCURACY**: Always query the graph or read `graph.json` to find exact Node IDs before creating relationships between documents and code.
- **SANITIZATION**: Limit node labels to 256 characters and strip control characters to ensure visualization stability.
