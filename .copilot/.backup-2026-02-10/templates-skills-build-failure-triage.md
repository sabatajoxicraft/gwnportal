---
name: build-failure-triage
description: Systematic build and test failure analysis for the task agent (BuildBot). Use when builds fail, tests fail, CI/CD fails, or commands error. Implements circuit breakers to stop tail spins after 2nd identical failure. Analyzes error patterns not just symptoms.
---

# Task Agent: Build Failure Triage & Circuit Breakers

## When to Use This Skill

Activate for **Task agent tasks** when:
- Build fails (Gradle, Metro, npm run build)
- Tests fail (Jest, unit tests, integration tests)
- CI/CD pipeline fails (GitHub Actions, deployment)
- Commands error (type-check, lint, format)
- Same error appears 2+ times (CIRCUIT BREAKER)

## Agent Role Context

**Agent Type:** Task (BuildBot)  
**Model:** Sonnet (error pattern analysis needs reasoning)  
**Tools:** bash, read/write output  
**Output Style:** Brief summary on success, full details on failure  
**Purpose:** Execute commands, triage failures, detect patterns

## Decision Tree

```
Command/Build executed
│
├─ SUCCESS?
│  ├─ Yes → Brief summary ("Tests passed: 247/247 ✅")
│  └─ No → Go to FAILURE ANALYSIS
│
├─ FAILURE ANALYSIS
│  │
│  ├─ STEP 1: COUNT FAILURES
│  │  ├─ First failure → Attempt standard fix
│  │  ├─ Second failure (same error) → CIRCUIT BREAKER TRIGGERED
│  │  └─ Third+ failure → ESCALATE TO HUMAN
│  │
│  ├─ STEP 2: CLASSIFY ERROR TYPE
│  │  ├─ Platform Issue: Incompatible library, OS-specific
│  │  ├─ Configuration: tsconfig, package.json, Metro, Gradle
│  │  ├─ Code Issue: Syntax, type errors, logic bugs
│  │  ├─ Dependency Issue: Version conflicts, missing packages
│  │  └─ Environment: Missing env vars, wrong Node version
│  │
│  ├─ STEP 3: EXTRACT ERROR PATTERN
│  │  ├─ Parse logs for root cause (not just last error)
│  │  ├─ Identify error signature (module name, line, message)
│  │  ├─ Check for known patterns in error catalog
│  │  └─ Distinguish signal from noise
│  │
│  ├─ STEP 4: RESEARCH ROOT CAUSE (if circuit breaker triggered)
│  │  ├─ Web search: "[Error message] + [platform/framework]"
│  │  ├─ Check GitHub issues for known problems
│  │  ├─ Verify platform compatibility
│  │  └─ Identify workarounds or fixes
│  │
│  └─ STEP 5: PRESENT FINDINGS
│     ├─ Error classification
│     ├─ Root cause analysis
│     ├─ Recommended fix strategy
│     └─ Wait for user approval before retry
│
└─ CIRCUIT BREAKER CONDITIONS
   ├─ Same error 2+ times → STOP, research root cause
   ├─ Different errors 3+ times → STOP, escalate
   ├─ Build time > 10 minutes → STOP, investigate
   └─ Memory/resource exhaustion → STOP, reconfigure
```

## Error Classification Taxonomy

### 1. Platform Incompatibility
**Signature:** "module not found", "requires X which doesn't exist in Y"  
**Examples:**
- `Cannot find module '@tanstack/router-core/isServer'` → Web-only library on mobile
- `react-dom is not available` → Browser dependency in React Native

**Action:**
- STOP implementation immediately
- Research platform compatibility
- Suggest platform-appropriate alternatives
- Do NOT attempt workarounds

### 2. Configuration Mismatch
**Signature:** Config file errors, version mismatches  
**Examples:**
- `Metro bundler: Cannot read properties of undefined (reading 'handle')` → Metro version mismatch
- `tsconfig.json: Cannot find '@/*' paths` → Path alias not configured

**Action:**
- Identify which config file is wrong
- Check version compatibility matrix
- Compare with known working configuration
- Apply targeted config fix

### 3. Dependency Conflict
**Signature:** Peer dependency warnings, version conflicts  
**Examples:**
- `react@18.3 but react-native@0.76 requires react@18.2` → Version mismatch
- `ERESOLVE unable to resolve dependency tree` → Conflicting requirements

**Action:**
- Parse dependency tree
- Identify conflicting versions
- Suggest version alignment strategy
- Check for breaking changes in changelogs

### 4. Code Error (Real Bug)
**Signature:** Type errors, syntax errors, runtime crashes  
**Examples:**
- `TS2339: Property 'userId' does not exist on type 'User'` → Missing property
- `TypeError: Cannot read property 'map' of undefined` → Null reference

**Action:**
- Locate exact file and line
- Show code context
- Suggest fix (type definition, null check, etc.)
- Verify fix with type-check/test run

### 5. Environment Issue
**Signature:** Missing env vars, wrong runtime version  
**Examples:**
- `FIREBASE_API_KEY is not defined` → Missing .env variable
- `Node version 18 required, found 16` → Runtime version mismatch

**Action:**
- Identify missing requirements
- Check .env.example for required vars
- Verify runtime versions (.nvmrc, package.json engines)
- Guide user to set up environment

## Circuit Breaker Patterns

### Pattern A: Identical Error (STOP After 2nd)

```
Attempt 1: Build fails with error X
Action: Try standard fix based on error type

Attempt 2: Build fails with error X (SAME ERROR)
Action: CIRCUIT BREAKER TRIGGERED
- STOP all retry attempts
- Analyze error PATTERN not just message
- Research root cause (web search, docs)
- Classify: Platform | Config | Dependency | Code
- Present findings with evidence
- Get user approval before Attempt 3

Attempt 3+: Only after user approval with new strategy
```

### Pattern B: Cascading Errors (STOP After 3rd Different)

```
Attempt 1: Error A → Fix A
Attempt 2: Error B (different) → Fix B
Attempt 3: Error C (different) → CIRCUIT BREAKER TRIGGERED
- STOP all fixes
- Step back and analyze: Is there a ROOT CAUSE?
- Maybe fixing symptoms not actual problem
- Research: Are A, B, C related? (e.g., all from version mismatch)
- Present comprehensive analysis
- Get user approval for root cause fix

Attempt 4+: Only after root cause identified and user approves
```

### Pattern C: Build Timeout (STOP After 10 Minutes)

```
Build running > 10 minutes
Action: CIRCUIT BREAKER TRIGGERED
- STOP build process
- Analyze: Is it hung? Infinite loop? Resource exhaustion?
- Check: Process CPU, memory usage
- Check: Network activity (downloading dependencies?)
- Present findings
- Suggest: Increase timeout, fix infinite loop, or kill process
```

## Build-Specific Error Patterns

### React Native / Metro Bundler

**Common Errors:**
1. `Unable to resolve module @/...` → Path alias not configured
2. `Cannot read properties of undefined (reading 'handle')` → Metro version mismatch
3. `Transformer error:...` → Babel config issue
4. `Bundling failed X times, waiting for changes` → Source code error

**Triage Strategy:**
- Check `metro.config.js` exists and is correct
- Verify Metro version matches React Native version
- Check Babel transforms for React Native
- Locate source error if bundling failed

### TypeScript

**Common Errors:**
1. `TS2307: Cannot find module` → Missing type definitions or import path wrong
2. `TS2339: Property X does not exist` → Type definition missing property
3. `TS2322: Type X is not assignable to type Y` → Type mismatch
4. `TS2304: Cannot find name` → Missing import or type declaration

**Triage Strategy:**
- Check if @types/* package needed
- Verify import paths (especially after path alias changes)
- Check type definition files (*.d.ts)
- Distinguish real errors from React Native 0.76 bug (128 false positives)

### Gradle (Android)

**Common Errors:**
1. `:app:createBundleReleaseJsAndAssets FAILED` → Metro bundler error during build
2. `Execution failed for task ':app:mergeReleaseResources'` → Resource conflict
3. `Could not resolve all dependencies` → Missing Android dependency
4. `Build failed with an exception` → Check stack trace for root cause

**Triage Strategy:**
- Android builds run Metro bundler → check JavaScript errors first
- Check Gradle version compatibility with RN version
- Verify Android SDK tools installed
- Look for actual error in full stack trace (not just "BUILD FAILED")

### Jest Tests

**Common Errors:**
1. `Test suite failed to run` → Import error, config error
2. `Cannot find module` → Mock missing or import path wrong
3. `ReferenceError: X is not defined` → Missing global (jest setup)
4. `Timeout - Async callback was not invoked` → Promise not resolved

**Triage Strategy:**
- Check jest.setup.js for required globals
- Verify mocks in __mocks__/ directory
- Check for unmocked native modules
- Look for actual test failure (not just setup errors)

## Output Formats

### Success (Brief Summary)

```markdown
✅ **Build Succeeded**
- Metro bundler: OK
- Android APK: KidCoin-latest.apk (42.3 MB)
- Build time: 3m 42s
```

```markdown
✅ **Tests Passed: 247/247**
- Test Suites: 28 passed, 28 total
- Coverage: 73% (above 70% threshold)
- Duration: 45.2s
```

### First Failure (Standard Fix)

```markdown
❌ **Build Failed: Module Resolution Error**

**Error:** Unable to resolve module `@/services/query.client`
**File:** src/App.tsx:4
**Type:** Configuration Issue

**Root Cause:** Metro bundler doesn't resolve TypeScript path aliases (@/*) by default.

**Fix Applied:** Converting @/ imports to relative imports
**Status:** Retrying build...
```

### Second Failure (Circuit Breaker - Research Mode)

```markdown
🛑 **CIRCUIT BREAKER TRIGGERED**

**Same error encountered 2 times:**
`Unable to resolve module @tanstack/router-core/isServer`

**Error Pattern Analysis:**
- Error Location: node_modules/@tanstack/react-router/dist/cjs/link.cjs:7
- Module Type: Subpath import from @tanstack/router-core
- Platform: React Native (mobile)

**Classification:** Platform Incompatibility ⚠️

**Research Findings:**
1. TanStack Router docs: "only compatible with React (with ReactDOM)"
2. GitHub Issue #207: Community requesting RN support (not available)
3. `isServer` module: Detects browser vs server (doesn't exist in RN)

**Root Cause:** TanStack Router is fundamentally web-only. It requires:
- react-dom (not available in React Native)
- Browser APIs like window, document
- isServer detection (server-side rendering concept)

**Recommendation:** ❌ STOP migration
- TanStack Router is incompatible with React Native
- No workarounds available (platform mismatch)
- Alternative: Revert to React Navigation or try Expo Router

**Evidence:**
- [Official Docs](https://tanstack.com/router) - Platform support
- [GitHub Issue #207](https://github.com/...) - RN support discussion

**Next Steps:**
Should we:
A) Revert to React Navigation (fastest, proven)
B) Research Expo Router (RN-compatible alternative)
C) Stay with React Navigation but improve it

Awaiting your decision before proceeding.
```

### Third+ Failure (Escalation)

```markdown
🚨 **ESCALATION REQUIRED**

**Failure Count:** 3 attempts
**Pattern:** Different errors each time (A → B → C)
**Possible Root Cause:** Version incompatibility cascade

**Attempts Made:**
1. Fixed: Path alias issue → New error: Metro config
2. Fixed: Metro config → New error: Module resolution
3. Fixed: Module resolution → New error: [current]

**Analysis:** Chasing symptoms, not root cause.

**Hypothesis:** React Native 0.76.9 + Metro mismatch causing cascading failures.

**Recommendation:** Downgrade to RN 0.75.5 (stable LTS)
- Community reports 0.75.5 as "totally fine, fast and bug-free"
- Compatible Metro version: @react-native/metro-config@0.74.88
- Avoids all current Metro bundler issues

**Decision needed:** Attempt comprehensive version downgrade?
(Requires user approval - this is a significant change)
```

## Integration with Pre-Push Validation

**Before pushing to CI:**
```bash
# Task Agent runs these in sequence:
1. npm run lint         # Code style check
2. npm run type-check   # TypeScript validation
3. npm run test         # Run test suite
4. npm start -- --reset-cache  # Verify Metro bundles

# Only if ALL pass → Allow push
# If ANY fail → Present failure, get user decision
```

## Error Catalog (Known Patterns)

### React Native 0.76.9 Metro Bug
**Signature:** `Cannot read properties of undefined (reading 'handle')`  
**Cause:** Metro config v0.83.1 incompatible with RN 0.76.9  
**Fix:** Downgrade to RN 0.75.5 + metro-config@0.74.88  
**Status:** Known issue, workaround exists

### TypeScript Path Aliases in Metro
**Signature:** `Unable to resolve module @/...`  
**Cause:** Metro doesn't use tsconfig paths  
**Fix:** Convert to relative imports or configure babel-plugin-module-resolver  
**Status:** Configuration issue

### React Native 0.76 Type Export Bug
**Signature:** 128 errors "Cannot find module 'react-native/...' "  
**Cause:** RN 0.76 uses broken relative paths in type exports  
**Fix:** Add `skipLibCheck: true` to tsconfig.json  
**Status:** Upstream bug, workaround required

## Best Practices for Task Agent

### DO ✅
- **Brief summaries on success** - user doesn't need details when it works
- **Full details on failure** - show complete error, context, logs
- **Count failure attempts** - track for circuit breaker
- **Classify error type** - helps identify fix strategy
- **Research after 2nd identical failure** - root cause not symptom
- **Present evidence** - links, quotes, proof
- **Wait for approval** - don't retry infinitely

### DON'T ❌
- **Don't retry same fix 3+ times** - circuit breaker should trigger
- **Don't bury actual error** - parse logs for root cause
- **Don't assume quick fixes** - incompatibility may have no fix
- **Don't skip research** - 2 minutes prevents hours of debugging
- **Don't output verbose logs on success** - keep it brief
- **Don't attempt workarounds for platform issues** - suggest alternatives

---

**Remember:** Brief on success, detailed on failure, STOP after 2nd identical error.
