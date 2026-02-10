# Project Mandate

> ⚠️ AI AGENTS: Read this ENTIRE document before ANY action.
> This project follows MDDF v2.0. Deviations not permitted.

===

## Version
| Field | Value |
|-------|-------|
| Created | 2026-02-07 |
| Updated | 2026-02-10 |
| Framework | MDDF v2.3.0 |

===

## Project Identity
| Field | Value |
|-------|-------|
| Name | GWN WiFi Portal |
| Description | PHP web application for managing student accommodation WiFi access. Handles multi-accommodation management, RBAC, student onboarding, and voucher tracking. |
| Tech Stack | PHP 8.2 + MySQL 8.0 + Bootstrap 5 |
| Platform | Web (Standalone) |
| Location | C:\apps\gwn-portal |

**Note:** This app is completely independent from gwn-python-cli (located at C:\apps\gwn-python-cli). They do not communicate with each other.

===

## Auto-Derived (M0 Phase)
- Problem statement
- Target users  
- Core features (MVP)
- Out of scope
- Success criteria
- Milestones

===

## 4-LAYER HIERARCHY

| Layer | Agent | Type | Responsibility |
|-------|-------|------|----------------|
| 1 | **OVERSEER** | You | Observe, delegate, enforce |
| 2 | **Architect** | general-purpose | Write code, architecture |
| 2 | **BuildBot** | task | Run builds, tests |
| 3 | **CodeScout** | explore | Research, file search |
| 3 | **Reviewer** | code-review | Security, quality |
| 4 | **shrimp-tasks** | MCP Tool | Task tracking |

===

## HUMAN TOUCHPOINTS (Only 3)

| # | When | Action |
|---|------|--------|
| 1 | Initial Input | `mddf mandate` ✓ |
| 2 | PRD Approval | After M0-T5 |
| 3 | Escalation | Circuit breaker maxed |

**Everything else is autonomous.**

===

## AUTO-TRIGGERS (Mandatory)

| IF | THEN |
|----|------|
| Build fails | `skill(build-failure-triage)` |
| New dependency | `skill(pre-migration-compatibility-check)` |
| Code change | `skill(implement-with-validation)` |
| 2nd identical error | **CIRCUIT BREAKER** |

===

## CODING STANDARDS (FIXED)

| Standard | Value |
|----------|-------|
| Design | Atomic Design (atoms→molecules→organisms→templates→pages) |
| Styling | Tailwind CSS + shadcn/ui |
| Icons | Material Icons (primary), Lucide (fallback) |
| Types | TypeScript strict mode (no `any`) |

### File Structure
```
src/
├── components/{atoms,molecules,organisms,templates}
├── pages/
├── hooks/, utils/, services/, stores/, theme/, types/, lib/
```

===

## PHASES

| Phase | Gate | Description |
|-------|------|-------------|
| M0 | PRD approved | Define requirements |
| M0.5 | **CI GREEN** | Validate scaffold |
| M1+ | Feature complete | Implementation |

### M0.5 is MANDATORY
Do NOT skip scaffold validation.

===

## AI Instructions

1. Read mandate.md, then m0-tasks.md
2. Use shrimp-tasks for all work tracking
3. Stop at USER APPROVAL tasks
4. Invoke skills on triggers
5. Escalate after circuit breaker
