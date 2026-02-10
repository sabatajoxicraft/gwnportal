---
name: domain-decomposition
description: Transform compound tasks into atomic single-domain subtasks. Mandatory before ANY delegation. Enforces "One AI, One Domain" principle.
---

# Domain Decomposition

## Invoke: OVERSEER calls before ANY delegation

## Domain Taxonomy

| ID | Domain | File Patterns |
|----|--------|---------------|
| CFG | Configuration | `*.json`, `.env*`, `*.config.*` |
| SRC | Source Code | `src/**/*.ts`, `src/**/*.tsx` |
| TST | Testing | `__tests__/**`, `*.test.*`, `*.spec.*` |
| BLD | Build/CI | `.github/**`, build scripts |
| DOC | Documentation | `*.md`, inline docs |
| DEP | Dependencies | package.json, lock files |
| MON | Monitoring | Logger setup, analytics |
| SEC | Security/Auth | Auth services, security config |
| UI | UI/UX | Style files, UI components |
| DAT | Data/Schema | Schemas, migrations, types |

## Decomposition Algorithm

1. **Parse** — Extract verbs + nouns from task description
2. **Map** — Assign each verb-noun pair to a domain
3. **Detect** — If task maps to 2+ domains → SPLIT REQUIRED
4. **Atomize** — Each subtask = ONE domain + ONE action + ONE file
5. **Graph** — Build dependency graph (parallel where independent)
6. **Assign** — Map agent types: `explore`=research, `general-purpose`=code, `task`=builds, `code-review`=validation

## Granularity Rules

| Service Complexity | Decomposition Level |
|--------------------|---------------------|
| Simple (≤3 methods) | 1 agent per service |
| Standard (4-6 methods) | 1 agent per method group |
| Complex (7+ methods) | 1 agent per method |

**Agent count scaling:**
- Simple feature: 5-8 agents
- Standard feature: 10-15 agents
- Complex feature: 15-30 agents

## Output Format

```yaml
original_task: "description"
domains_detected: [SRC, TST, BLD]
decomposition:
  phase_1_parallel: [{id, domain, agent, task, files}]
  phase_2_sequential: [{id, domain, agent, task, files, depends_on}]
```

## Validation

✅ Every subtask touches ONE domain only
✅ No subtask contains cross-domain "AND"
✅ Dependency graph is acyclic
✅ Each subtask has clear success criteria
✅ Parallel phases have NO inter-dependencies

## OVERSEER Workflow

```
Request → Decompose → FOR EACH phase:
  IF parallel: SPAWN ALL agents in ONE response
  ELSE: SPAWN sequentially
  WAIT → LOG to session-state.md → AGGREGATE results
```

## Circuit Breaker

- Subtask fails → Check if same domain failed before
- 2nd failure same domain → Escalate to user
