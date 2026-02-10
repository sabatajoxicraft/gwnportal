---
name: implement-with-validation
description: Systematic implementation workflow for the general-purpose agent (Architect). Use when implementing features, fixing bugs, refactoring code, or making architectural changes. Enforces checkpoints, local validation before push, and compatibility research before migrations.
---

# General-Purpose Agent: Implementation with Validation

## When to Use This Skill

Activate for **General-Purpose (Architect) agent tasks** when:
- Implementing new features
- Fixing bugs
- Refactoring code
- Making architectural changes
- Migrating frameworks/libraries
- Modifying core services
- Adding dependencies

## Agent Role Context

**Agent Type:** General-Purpose (Architect)  
**Model:** Sonnet (default), Opus (for complex/critical work)  
**Tools:** All tools available (read, edit, create, bash, grep, etc.)  
**Output:** Complete implementation with validation  
**Responsibility:** Owns code quality from start to finish

## Decision Tree

```
Implementation request received
│
├─ STEP 1: CLASSIFY COMPLEXITY
│  ├─ Simple: Bug fix, minor change → Sonnet model
│  ├─ Standard: Feature implementation, refactor → Sonnet model
│  ├─ Complex: Architecture, security, migration → Opus model
│  └─ Critical: Production hotfix, data migration → Opus model + human review
│
├─ STEP 2: PRE-IMPLEMENTATION VALIDATION
│  │
│  ├─ Is this a migration/new dependency?
│  │  ├─ Yes → DELEGATE to `pre-migration-compatibility-check` skill
│  │  │   ├─ Wait for GO/NO-GO decision
│  │  │   ├─ If NO-GO → STOP, suggest alternatives
│  │  │   └─ If GO → Proceed with implementation
│  │  └─ No → Continue to planning
│  │
│  ├─ Do we understand requirements?
│  │  ├─ Yes → Proceed
│  │  └─ No → ASK USER for clarification (don't assume)
│  │
│  └─ Is scope clear?
│     ├─ Yes → Proceed
│     └─ No → Define scope boundaries with user
│
├─ STEP 3: PLAN IMPLEMENTATION
│  ├─ Identify files to modify
│  ├─ Identify patterns to follow (service layer, state management)
│  ├─ Define success criteria (what "done" looks like)
│  ├─ Plan validation steps
│  └─ Get user approval on plan
│
├─ STEP 4: IMPLEMENT IN PHASES
│  │
│  ├─ Phase 1: Core logic
│  │  ├─ Write/modify code
│  │  ├─ Follow project patterns (service layer, types, error handling)
│  │  ├─ Keep changes surgical (minimal modifications)
│  │  └─ Checkpoint: Does core logic compile?
│  │
│  ├─ Phase 2: Integration
│  │  ├─ Connect to services/stores
│  │  ├─ Update types as needed
│  │  ├─ Add error handling
│  │  └─ Checkpoint: Does integration work?
│  │
│  ├─ Phase 3: UI (if applicable)
│  │  ├─ Update components/screens
│  │  ├─ Follow design system (Colors.ts, spacing)
│  │  ├─ Add loading/error states
│  │  └─ Checkpoint: Does UI render?
│  │
│  └─ Phase 4: Testing
│     ├─ Write/update tests
│     ├─ Run existing tests (ensure no regression)
│     ├─ Manual testing scenarios
│     └─ Checkpoint: Do all tests pass?
│
├─ STEP 5: LOCAL VALIDATION (MANDATORY)
│  │
│  ├─ Run quality gates sequentially:
│  │  ├─ 1. `npm run type-check` → Must pass
│  │  ├─ 2. `npm run lint` → Must pass (or auto-fix)
│  │  ├─ 3. `npm run test` → Must pass (no regressions)
│  │  ├─ 4. `npm start --reset-cache` → Metro must bundle
│  │  └─ If ANY fail → Fix before proceeding
│  │
│  ├─ Platform-specific validation:
│  │  ├─ React Native: Metro bundler success
│  │  ├─ Web: Build completes
│  │  ├─ Node: All imports resolve
│  │  └─ Mobile: Native modules link correctly
│  │
│  └─ Only after ALL pass → Ready to commit
│
├─ STEP 6: COMMIT & DOCUMENT
│  ├─ Write clear commit message:
│  │  ├─ What: [Brief description]
│  │  ├─ Why: [Reason for change]
│  │  ├─ How: [Approach taken]
│  │  └─ Context: [Related issues, decisions]
│  │
│  ├─ Update documentation if needed:
│  │  ├─ README.md for usage changes
│  │  ├─ API docs for interface changes
│  │  ├─ CONTRIBUTING.md for pattern changes
│  │  └─ Inline comments for complex logic
│  │
│  └─ Commit with meaningful message
│
└─ STEP 7: PUSH & MONITOR
   ├─ Push to feature branch (not main)
   ├─ Monitor CI/CD build
   ├─ If CI fails → Analyze with build-failure-triage skill
   └─ If CI passes → Ready for review
```

## Implementation Patterns (KidCoin-Specific)

### Pattern 1: Service Layer (Firebase/Prisma)

**Always follow service layer pattern:**
```typescript
// services/example.service.ts
import { wrapServiceCall, wrapSubscription } from '@/utils/error.utils';

export const exampleService = {
  // One-time call
  async getData(id: string): Promise<Data> {
    return wrapServiceCall(
      async () => {
        // Firebase or Prisma logic here
        return result;
      },
      'exampleService.getData'
    );
  },

  // Real-time subscription
  subscribeToData(
    id: string,
    onUpdate: (data: Data[]) => void,
    onError?: (error: string) => void
  ) {
    return wrapSubscription(
      () => {
        // Firestore onSnapshot or similar
        return unsubscribe;
      },
      'exampleService.subscribeToData'
    );
  },
};
```

**Validation:**
- ✅ All Firebase calls wrapped with error handlers
- ✅ Service exports specific functions (not classes)
- ✅ Subscriptions return cleanup function
- ✅ Types defined in `types/` directory

### Pattern 2: Zustand State Management

**Always follow store pattern:**
```typescript
// stores/exampleStore.ts
import { create } from 'zustand';

interface ExampleState {
  data: Data[];
  isLoading: boolean;
  error: string | null;
  
  // Actions
  setData: (data: Data[]) => void;
  setLoading: (loading: boolean) => void;
  setError: (error: string | null) => void;
}

export const useExampleStore = create<ExampleState>((set) => ({
  data: [],
  isLoading: false,
  error: null,
  
  setData: (data) => set({ data, error: null }),
  setLoading: (isLoading) => set({ isLoading }),
  setError: (error) => set({ error, isLoading: false }),
}));
```

**Validation:**
- ✅ State and actions in same interface
- ✅ Loading and error states included
- ✅ Actions use `set()` not mutation
- ✅ Type-safe with TypeScript

### Pattern 3: React Native Components

**Always follow component pattern:**
```typescript
// components/Example.tsx
import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { BrandColors } from '@/constants/Colors';

interface ExampleProps {
  data: Data;
  onAction: () => void;
}

export const Example: React.FC<ExampleProps> = ({ data, onAction }) => {
  return (
    <View style={styles.container}>
      <Text style={styles.title}>{data.title}</Text>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    padding: 16,
    backgroundColor: BrandColors.surface.light,
  },
  title: {
    fontSize: 18,
    color: BrandColors.text.primary,
  },
});
```

**Validation:**
- ✅ TypeScript interface for props
- ✅ Functional component with React.FC
- ✅ StyleSheet at bottom of file
- ✅ Colors from BrandColors constant
- ✅ Spacing follows 8px grid

### Pattern 4: Type Definitions

**Always centralize types:**
```typescript
// types/example.types.ts
export interface Example {
  id: string;
  name: string;
  value: number;
  createdAt: Date;
}

export type ExampleStatus = 'pending' | 'active' | 'completed';

export interface CreateExampleInput {
  name: string;
  value: number;
}
```

**Validation:**
- ✅ Types in `types/` directory
- ✅ One file per domain (user, task, payout)
- ✅ Exported interfaces (not type aliases unless needed)
- ✅ Input types separate from entity types

## Checkpoint System

### Checkpoint 1: After Core Logic
```bash
# Verify TypeScript compiles
npm run type-check

# Expected: No errors in modified files
# If errors: Fix before proceeding to integration
```

### Checkpoint 2: After Integration
```bash
# Verify no linting issues
npm run lint

# Expected: Clean or auto-fixable warnings
# If errors: Fix patterns, imports, formatting
```

### Checkpoint 3: After Testing
```bash
# Run test suite
npm run test

# Expected: All tests pass, no regressions
# If failures: Fix broken tests or update expectations
```

### Checkpoint 4: Before Commit
```bash
# Verify Metro bundles (React Native)
npm start --reset-cache

# Expected: Metro starts, no bundle errors
# If fails: Fix import paths, dependencies
```

**Only proceed to commit if ALL checkpoints pass!**

## Pre-Push Quality Gates (Automated)

```bash
# This runs automatically via pre-push hook
.copilot/quality-gates.sh

# Executes:
# 1. Type checking (allows RN 0.76 false positives)
# 2. Linting (enforces style)
# 3. Test suite (prevents regressions)
# 4. Metro bundle check (validates builds)

# Blocks push if ANY gate fails
```

**Never bypass quality gates** - they prevent CI failures.

## Migration Workflow (Special Case)

```
User wants to migrate from X to Y
│
├─ 1. STOP IMPLEMENTATION
│  └─ Do NOT install packages yet
│
├─ 2. DELEGATE to `pre-migration-compatibility-check` skill
│  ├─ Skill researches compatibility
│  ├─ Presents GO/CAUTION/NO-GO decision
│  └─ Wait for user approval
│
├─ 3. If GO → Proceed with implementation
│  ├─ Install dependencies
│  ├─ Update configuration
│  ├─ Modify code
│  └─ Run checkpoints at each phase
│
├─ 4. If CAUTION → Implement with documented workarounds
│  ├─ Note workarounds in comments
│  ├─ Add to README.md known issues
│  └─ Plan for future cleanup
│
└─ 5. If NO-GO → STOP
   ├─ Present alternatives
   ├─ Discuss with user
   └─ Do NOT proceed with original plan
```

**TanStack Router Example:**
- Step 2 would have returned NO-GO
- Would have stopped before any code changes
- Saved 4 failed builds and hours of debugging

## Error Handling (Mandatory)

### Service Calls
```typescript
// ALWAYS wrap service calls
const result = await wrapServiceCall(
  async () => {
    // Service logic
  },
  'contextName'  // For debugging
);
```

### Subscriptions
```typescript
// ALWAYS wrap subscriptions
useEffect(() => {
  const unsubscribe = wrapSubscription(
    () => {
      // Subscription logic
      return cleanupFunction;
    },
    'contextName'
  );
  
  return unsubscribe;  // CRITICAL: Cleanup on unmount
}, [dependencies]);
```

### UI Error States
```typescript
// ALWAYS show error states
{isLoading && <LoadingSpinner />}
{error && <ErrorMessage message={error} />}
{!isLoading && !error && data && <DataDisplay data={data} />}
```

## Model Selection for Implementation

| Task Type | Model | Rationale |
|-----------|-------|-----------|
| Bug fix (small scope) | Sonnet | Fast, accurate for focused changes |
| Feature implementation | Sonnet | Balanced reasoning and speed |
| Refactoring | Sonnet | Pattern recognition |
| Architecture change | Opus | Deep reasoning needed |
| Security-critical | Opus | Cannot afford mistakes |
| Migration (after GO) | Opus | Complex, multi-step, high stakes |
| Production hotfix | Opus + human review | Zero tolerance for errors |

**Escalate to Opus if:**
- Sonnet struggles with task (2+ failed attempts)
- Task involves unfamiliar codebase areas
- Changes affect security or data integrity
- Multiple files must be coordinated precisely

## Best Practices for Architect Agent

### DO ✅
- **Plan before coding** - understand full scope
- **Follow project patterns** - service layer, types, state
- **Implement in phases** - core → integration → UI → tests
- **Run checkpoints** - validate after each phase
- **Write tests** - prevent regressions
- **Local validation** - all quality gates before push
- **Clear commits** - what, why, how
- **Surgical changes** - minimal modifications to achieve goal

### DON'T ❌
- **Don't skip compatibility research** - migrations need validation
- **Don't bypass quality gates** - they catch real issues
- **Don't implement without plan** - leads to scope creep
- **Don't push failing code** - Metro/lint/tests must pass locally
- **Don't assume patterns** - check existing code first
- **Don't leave TODOs** - finish what you start
- **Don't mix concerns** - one PR per logical change
- **Don't ignore types** - TypeScript exists for a reason

## Circuit Breakers for Architect

**STOP if:**
- Implementation blocked by platform incompatibility → Research alternatives
- Changes breaking tests → Fix tests or revert changes
- Quality gates failing repeatedly → Analyze root cause
- Scope expanding beyond original request → Confirm with user
- Approach not working after 2 attempts → Try different strategy

## Integration with Other Skills

**Before implementation:**
- Migration? → Use `pre-migration-compatibility-check`
- Exploring codebase? → Delegate to Explore agent
- Need architecture understanding? → Delegate to Explore agent

**During implementation:**
- Tests fail? → Use `build-failure-triage` skill
- Metro errors? → Use `build-failure-triage` skill

**After implementation:**
- Security review? → Delegate to Code-Review agent
- CI/CD failing? → Use `build-failure-triage` skill

---

**Remember:** Plan, implement in phases, checkpoint each step, validate locally, only then push.
