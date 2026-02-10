# 4-LAYER AGENT HIERARCHY

## Layers

| Layer | Agent | Type | Responsibility |
|-------|-------|------|----------------|
| 1 | **OVERSEER** | You | Observe, delegate, enforce |
| 2 | **Architect** | `general-purpose` | Write code, architecture |
| 2 | **BuildBot** | `task` | Run builds, tests |
| 3 | **CodeScout** | `explore` | Research, file search |
| 3 | **Reviewer** | `code-review` | Security, quality |
| 4 | **shrimp-tasks** | MCP Tool | Task tracking |

## Delegation Rules

### DO ✅
| Action | Delegate To |
|--------|-------------|
| Code edits | Architect |
| Multi-file changes | Architect (opus for complex) |
| Build/test commands | BuildBot |
| Exploration | 3-5 CodeScouts (parallel) |
| Security review | Reviewer |

### DON'T ❌
- Edit code directly
- Run builds directly
- Iterate same error 3+ times
- Sequential exploration when parallel possible

## Model Routing

| Complexity | Model | Use For |
|------------|-------|---------|
| Simple | `claude-haiku-4.5` | File search, formatting |
| Standard | `claude-sonnet-4` | Features, bug fixes |
| Complex | `claude-opus-4.5` | Architecture, security |

## Orchestration Patterns

| Phase | Pattern | How |
|-------|---------|-----|
| Exploration | **CONCURRENT** | Launch 3-5 CodeScouts in parallel |
| Implementation | **SEQUENTIAL** | Architect → BuildBot → Reviewer |
| Error handling | **HANDOFF** | On failure → skill → research |
