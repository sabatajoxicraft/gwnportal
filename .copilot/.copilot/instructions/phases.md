# DEVELOPMENT PHASES

## Phase Overview

| Phase | Gate | Description |
|-------|------|-------------|
| M0 | PRD approved | Define requirements |
| M0.5 | **CI build GREEN** | Validate scaffold works |
| M1+ | Feature complete | Implementation |

## M0: PRD Phase

| Task | Description |
|------|-------------|
| M0-T1 | Generate PRD from mandate |
| M0-T2 | Component Architecture (Atomic Design) |
| M0-T3 | Folder Structure + Tooling |
| M0-T4 | API/Data Strategy |
| M0-T5 | **USER APPROVAL** ← Stop here |

## M0.5: Scaffold Validation (MANDATORY)

> **DO NOT SKIP** - Validates tech stack works in CI BEFORE implementation.

| Task | Description |
|------|-------------|
| M0.5-T1 | Create minimal scaffold (hello world) |
| M0.5-T2 | Configure CI/CD, push to GitHub |
| M0.5-T3 | **GATE: CI build must be GREEN** |
| M0.5-T4 | Lock config files |

### M0.5-T3 Rules

- IF BUILD FAILS → Invoke `skill(build-failure-triage)`
- Fix config/dependency issues ONLY (not code)
- After 2nd identical failure → CIRCUIT BREAKER
- Only proceed to M1 when CI is GREEN

### Config Files to Lock After M0.5

After successful build, DO NOT MODIFY:
- `babel.config.js`
- `metro.config.js`
- `tailwind.config.js`
- `tsconfig.json`
- `package.json` (dependencies section)

## Human Touchpoints (Only 3)

| # | When | Action |
|---|------|--------|
| 1 | Initial input | `mddf mandate` - project setup |
| 2 | PRD approval | After M0-T5 |
| 3 | Escalation | Circuit breaker maxed |

**Everything else is autonomous.**
