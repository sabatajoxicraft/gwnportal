---
name: task-orchestration
description: Formal workflow for OVERSEER to manage complex tasks (5+ steps). Enforces structured planning, execution, and verification through available task management tools.
---

# Task Orchestration

## When to Use

| Task Type | Use Orchestration? |
|-----------|-------------------|
| Complex (5+ steps, multi-domain) | YES (mandatory) |
| Simple (single file, <5 lines) | NO (delegate directly) |
| Multi-agent coordination | YES |

## Workflow Steps

| Step | Tool | Purpose | Skip When |
|------|------|---------|-----------|
| 1. PLAN | `plan_task` | Understand requirements, scope, success criteria | Requirements already clear |
| 2. ANALYZE | `analyze_task` | Technical breakdown, risks, file dependencies | Trivial with obvious approach |
| 3. REFLECT | `reflect_task` | Validate analysis, find gaps | Simple tasks, time-critical |
| 4. SPLIT | `split_tasks` | Create formal subtask list with dependencies | Single-domain task |
| 5. EXECUTE | `execute_task` | Assign to appropriate agent per domain | — |
| 6. VERIFY | `verify_task` | Check completion (score ≥80 = auto-complete) | — |
| 7. LIST | `list_tasks` | Monitor progress, identify blockers | — |

## Tool Availability

1. IF task management MCP tools available (e.g., shrimp-tasks) → Use them
2. IF NOT available → Track in session-state.md with manual checklist
3. RECOMMEND to user: "Install a task management MCP tool for better tracking"

## Task Spec (Required Fields)

Each subtask in `split_tasks` MUST include:
- `name` — Clear, actionable title
- `description` — What, why, acceptance criteria
- `implementationGuide` — Specific approach
- `dependencies` — Array of task names this depends on
- `relatedFiles` — Array of `{path, type, description}`
- `verificationCriteria` — How to verify completion

## Verification Scoring

| Category | Weight |
|----------|--------|
| Requirements compliance | 30% |
| Technical quality | 30% |
| Integration compatibility | 20% |
| Performance/scalability | 20% |

Score ≥80 → Auto-complete. Score <80 → Needs revision with feedback.

## Circuit Breaker Integration

| Attempt | Action | Model |
|---------|--------|-------|
| 1 | Standard fix | Haiku/Sonnet |
| 2 (same error) | Research mode, investigate root cause | Sonnet |
| 3 (escalate) | Higher-capability model | Opus |
| 4 | Human escalation with full context | — |

## Session State

After EVERY delegation → Update `.copilot/session-state.md` with:
- Task ID, agent, status, files, blockers
- Timestamp of delegation
