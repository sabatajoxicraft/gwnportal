---
name: implement-with-validation
description: Systematic implementation workflow for Architect agents. Enforces coding standards, checkpoints, local validation before push, and compatibility research before migrations.
---

# Architect: Implementation with Validation

## STEP 0: Load Coding Standards (MANDATORY)

Before ANY implementation:
1. Read `coding-standards.md` — Atomic Design, Tailwind-only, TypeScript strict, no CSS-in-JS
2. These standards are NON-NEGOTIABLE and override agent preferences

## Decision Flow

| Step | Action | Gate |
|------|--------|------|
| 1. Classify | Simple→Sonnet, Standard→Sonnet, Complex→Opus, Critical→Opus+human | — |
| 2. Pre-validate | Migration? → `pre-migration-compatibility-check` skill first | GO/NO-GO |
| 3. Plan | Identify files, patterns, success criteria | User approval |
| 4. Implement | Phase by phase (see below) | Checkpoint each |
| 5. Validate | Run ALL quality gates locally | Must pass ALL |
| 6. Scope audit | `git diff --name-only` matches assigned files only | No extras |
| 7. Commit | Clear message (what, why, how) | — |
| 8. Push | Feature branch only, monitor CI | — |

## Implementation Phases

| Phase | Focus | Checkpoint |
|-------|-------|------------|
| 1. Core logic | Write/modify code following coding-standards.md | `npm run type-check` passes |
| 2. Integration | Connect services/stores, add error handling | `npm run lint` passes |
| 3. UI (if applicable) | Tailwind classes only, Atomic Design structure | Components render |
| 4. Testing | Write/update tests, no regressions | `npm run test` passes |

## Quality Gates (MANDATORY before push)

```
1. npm run type-check  → Must pass
2. npm run lint        → Must pass (or auto-fix)
3. npm run test        → Must pass (no regressions)
4. Platform build      → Must bundle/compile
5. Scope audit         → Only assigned files modified
```

If ANY gate fails → Fix before proceeding. Never push failing code.

## Migration Workflow

```
Migration request → STOP → pre-migration-compatibility-check skill
  → GO: Proceed with implementation
  → CAUTION: Implement with documented workarounds
  → NO-GO: STOP, present alternatives
```

## Best Practices

| DO ✅ | INSTEAD OF ❌ |
|-------|--------------|
| Plan before coding | Diving in without scope |
| Follow coding-standards.md | Using agent preferences |
| Implement in phases | Doing everything at once |
| Run checkpoints after each phase | Skipping validation |
| Surgical changes (minimal diff) | Rewriting entire files |
| Batch fix all occurrences | One-at-a-time fixes |

## Escalate to Opus If

- 2+ failed attempts with Sonnet
- Unfamiliar codebase areas
- Security or data integrity changes
- Multiple files must coordinate precisely
