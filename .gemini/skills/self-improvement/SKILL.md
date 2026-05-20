---
name: self-improvement
description: Workflow for agents to reflect on tasks, document successful patterns, and update project instructions to improve future performance. Use this after completing complex tasks or fixing difficult bugs.
---
# Self-Improvement Workflow

When this skill is activated, you must perform a retrospective on the recently completed task to enhance the system's collective intelligence.

## Retrospective Process

1. **Analyze Success/Failure**:
   - What was the core challenge?
   - What specific tool or strategy finally resolved it?
   - Were there any "wrong turns" or inefficient steps?

2. **Update Project Knowledge**:
   - **Patterns**: If a new coding pattern or architectural decision was made, document it in `GEMINI.md`.
   - **Bug Fixes**: If a non-obvious bug was fixed, add a "Common Gotchas" entry to `.gemini/memory/BUG_FIXES.md`.
   - **Efficiency**: If a specific sequence of commands worked well, record it in `.gemini/memory/WORKFLOWS.md`.

3. **Refine Agents**:
   - If a subagent struggled, suggest an update to its system prompt in its `.gemini/agents/*.md` file.

## Specific Actions

- **New Learnings**: Use `replace` or `write_file` to add insights to `.gemini/memory/LEARNINGS.md`.
- **Architectural Rules**: Update `./GEMINI.md` with high-level mandates that prevent future regressions.

## Example Learning Entry
```markdown
### [Date] - Fixed Race Condition in X
- **Issue**: Async requests were overlapping due to Y.
- **Solution**: Implemented a locking mechanism using Z.
- **Rule**: Always use the `withLock` wrapper for service X. (Added to GEMINI.md)
```
