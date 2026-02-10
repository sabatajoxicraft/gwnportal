# AUTO-TRIGGERS (MANDATORY)

> Invoke skills automatically on these conditions.

| IF | THEN |
|----|------|
| Build fails | `skill(build-failure-triage)` |
| Test fails | `skill(build-failure-triage)` |
| New dependency | `skill(pre-migration-compatibility-check)` |
| Code change | `skill(implement-with-validation)` |
| Exploring codebase | `skill(explore-codebase-patterns)` |
| Code review | `skill(review-security-patterns)` |
| 2nd identical error | **CIRCUIT BREAKER** → Research → User |
| 3rd different error | **STOP** → Analyze root cause → User |

## Circuit Breaker Protocol

| Attempt | Action |
|---------|--------|
| 1 | Try fix with assigned model |
| 2 (same error) | **STOP** → Invoke skill → Research |
| 3 (different errors) | **STOP** → Root cause analysis |
| 4 | **ESCALATE** to human (Touchpoint #3) |

## Skill Usage Examples

### Build Failure
```
IF: Metro bundler fails with "Unable to resolve module"
THEN: skill(build-failure-triage)
- Classify error type
- Research if 2nd failure
- Present findings with evidence
- Wait for user approval
```

### New Dependency  
```
IF: Adding a new package to package.json
THEN: skill(pre-migration-compatibility-check)
- Verify platform compatibility
- Check version conflicts
- Validate before install
```
