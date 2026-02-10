---
name: review-security-patterns
description: High signal-to-noise code review for the code-review agent (Reviewer). Use when reviewing code changes, PRs, or security audits. Only surfaces critical issues (bugs, security, logic errors). Never comments on style, formatting, or trivial matters. Focused on preventing production incidents.
---

# Code-Review Agent: Security & Critical Issues Only

## When to Use This Skill

Activate for **Code-Review agent tasks** when:
- Reviewing pull requests
- Security audits
- Pre-merge validation
- Code quality checks
- Finding critical bugs

## Agent Role Context

**Agent Type:** Code-Review (Reviewer)  
**Model:** Sonnet (default), Opus (security-critical reviews)  
**Tools:** Read, grep, glob (investigation only - NO modifications)  
**Output:** High signal-to-noise findings (bugs, security, logic)  
**Will NOT:** Comment on style, formatting, naming, trivial issues

## Decision Tree

```
Code review requested
│
├─ STEP 1: UNDERSTAND SCOPE
│  ├─ What changed? (diff, files modified)
│  ├─ Why changed? (PR description, commit messages)
│  ├─ Impact area? (services, UI, config, security)
│  └─ Risk level? (low, medium, high, critical)
│
├─ STEP 2: CLASSIFY REVIEW TYPE
│  │
│  ├─ Security-Critical?
│  │  ├─ Authentication logic → DEEP REVIEW (Opus model)
│  │  ├─ Authorization/permissions → DEEP REVIEW (Opus model)
│  │  ├─ Data exposure → DEEP REVIEW (Opus model)
│  │  ├─ Payment/financial → DEEP REVIEW (Opus model)
│  │  └─ User data handling → DEEP REVIEW (Opus model)
│  │
│  ├─ High-Impact?
│  │  ├─ Database migrations → CAREFUL REVIEW
│  │  ├─ API contract changes → CAREFUL REVIEW
│  │  ├─ State management core → CAREFUL REVIEW
│  │  └─ Error handling → CAREFUL REVIEW
│  │
│  ├─ Standard Change?
│  │  ├─ Feature implementation → NORMAL REVIEW
│  │  ├─ Bug fix → NORMAL REVIEW
│  │  └─ Refactoring → NORMAL REVIEW
│  │
│  └─ Low-Risk?
│     ├─ UI tweaks → LIGHT REVIEW
│     ├─ Documentation → LIGHT REVIEW
│     └─ Tests only → LIGHT REVIEW
│
├─ STEP 3: CRITICAL ISSUE SCAN
│  │
│  ├─ Security Vulnerabilities (P0)
│  │  ├─ Authentication bypass
│  │  ├─ Authorization missing/broken
│  │  ├─ SQL injection / NoSQL injection
│  │  ├─ XSS / code injection
│  │  ├─ Secret exposure (API keys, passwords)
│  │  ├─ Sensitive data in logs
│  │  └─ Insecure crypto/hashing
│  │
│  ├─ Logic Errors (P0)
│  │  ├─ Null pointer dereference
│  │  ├─ Race conditions
│  │  ├─ Infinite loops
│  │  ├─ Resource leaks (memory, file handles)
│  │  ├─ Off-by-one errors
│  │  └─ Incorrect business logic
│  │
│  ├─ Data Integrity (P0)
│  │  ├─ Data loss scenarios
│  │  ├─ Incorrect calculations (financial)
│  │  ├─ State corruption
│  │  └─ Missing validation
│  │
│  └─ Platform Compatibility (P1)
│     ├─ Breaking changes to API
│     ├─ Incompatible dependencies
│     ├─ Missing error handling
│     └─ Performance regressions
│
├─ STEP 4: PATTERN COMPLIANCE (KidCoin)
│  │
│  ├─ Service Layer Pattern
│  │  ├─ All Firebase calls wrapped with error handlers?
│  │  ├─ Subscriptions return cleanup functions?
│  │  ├─ No direct Firestore in components?
│  │  └─ Service exports match pattern?
│  │
│  ├─ State Management Pattern
│  │  ├─ Zustand stores follow pattern?
│  │  ├─ No direct state mutation?
│  │  ├─ Actions use set() correctly?
│  │  └─ Loading/error states included?
│  │
│  ├─ Error Handling Pattern
│  │  ├─ wrapServiceCall used for async ops?
│  │  ├─ wrapSubscription used for listeners?
│  │  ├─ UI shows error states?
│  │  └─ Cleanup functions in useEffect?
│  │
│  └─ Type Safety Pattern
│     ├─ Types defined in types/ directory?
│     ├─ No 'any' type (unless documented)?
│     ├─ Props interfaces for components?
│     └─ Return types on functions?
│
└─ STEP 5: REPORT FINDINGS
   ├─ Only report critical/high issues
   ├─ Ignore style, formatting, naming
   ├─ Provide evidence (line numbers, code snippets)
   ├─ Suggest fix (not just complaint)
   └─ Prioritize (P0 blocking, P1 high, P2 nice-to-have)
```

## Security Checklist (P0 - Blocking)

### 1. Authentication & Authorization

**MUST CHECK:**
```typescript
// ❌ BAD: No auth check
export const deleteUser = async (userId: string) => {
  await deleteDoc(doc(db, 'users', userId));
};

// ✅ GOOD: Auth verified
export const deleteUser = async (userId: string) => {
  const currentUser = auth.currentUser;
  if (!currentUser) throw new Error('Not authenticated');
  if (currentUser.uid !== userId) throw new Error('Not authorized');
  
  await deleteDoc(doc(db, 'users', userId));
};
```

**Issues to flag:**
- Missing authentication checks before sensitive operations
- Authorization bypassed (e.g., user can delete others' data)
- Token validation missing
- Session management weak

### 2. Data Exposure

**MUST CHECK:**
```typescript
// ❌ BAD: Exposes sensitive data
console.log('User data:', userData);  // Contains email, payment info

// ❌ BAD: Sends sensitive data to client
return {
  user,
  apiKey: process.env.SECRET_KEY,  // NEVER!
};

// ✅ GOOD: Sanitized output
console.log('User ID:', userData.id);

// ✅ GOOD: No secrets
return {
  user: {
    id: user.id,
    name: user.name,
    // Omit sensitive fields
  },
};
```

**Issues to flag:**
- API keys, secrets in client code
- Passwords in logs
- Personal data (email, phone) unnecessarily exposed
- Firebase config with private keys visible

### 3. Input Validation

**MUST CHECK:**
```typescript
// ❌ BAD: No validation
export const createTask = async (taskData: any) => {
  await addDoc(collection(db, 'tasks'), taskData);  // Direct insert!
};

// ✅ GOOD: Validated input
export const createTask = async (taskData: CreateTaskInput) => {
  // Validate required fields
  if (!taskData.title?.trim()) throw new Error('Title required');
  if (!taskData.points || taskData.points < 0) throw new Error('Invalid points');
  if (!taskData.familyId) throw new Error('Family ID required');
  
  // Sanitize
  const sanitized = {
    title: taskData.title.trim(),
    points: Math.floor(taskData.points),
    familyId: taskData.familyId,
    createdAt: serverTimestamp(),
  };
  
  await addDoc(collection(db, 'tasks'), sanitized);
};
```

**Issues to flag:**
- No input validation on user data
- Direct insertion without sanitization
- Missing bounds checks (negative points, huge strings)
- SQL/NoSQL injection vectors

### 4. Resource Cleanup

**MUST CHECK:**
```typescript
// ❌ BAD: Subscription leak
useEffect(() => {
  const unsubscribe = onSnapshot(query, (snapshot) => {
    // Handle data
  });
  // Missing return! Memory leak!
}, []);

// ✅ GOOD: Cleanup
useEffect(() => {
  const unsubscribe = onSnapshot(query, (snapshot) => {
    // Handle data
  });
  
  return unsubscribe;  // Cleanup on unmount
}, []);
```

**Issues to flag:**
- Firestore subscriptions without cleanup
- Event listeners not removed
- Timers/intervals not cleared
- File handles not closed

### 5. Error Handling

**MUST CHECK:**
```typescript
// ❌ BAD: Silent failure
try {
  await riskyOperation();
} catch (e) {
  // Swallowed! User never knows!
}

// ❌ BAD: Exposes internals
catch (e) {
  console.error(e);
  throw e;  // Raw error to user (stack trace, paths)
}

// ✅ GOOD: Wrapped error
try {
  await riskyOperation();
} catch (e) {
  console.error('Operation failed:', e);
  throw new Error('Failed to complete operation. Please try again.');
}

// ✅ BETTER: Using error wrapper
const result = await wrapServiceCall(
  () => riskyOperation(),
  'contextName'
);
```

**Issues to flag:**
- Silent error swallowing
- Raw errors exposed to users
- No error boundaries in React components
- Service calls not wrapped

## Logic Error Patterns (P0 - Blocking)

### 1. Null/Undefined Handling

```typescript
// ❌ BAD: Null dereference
const total = tasks.reduce((sum, task) => sum + task.points, 0);
// If tasks is null/undefined → CRASH

// ✅ GOOD: Safe access
const total = (tasks || []).reduce((sum, task) => sum + task.points, 0);

// ✅ BETTER: Early return
if (!tasks || tasks.length === 0) return 0;
const total = tasks.reduce((sum, task) => sum + task.points, 0);
```

**Issues to flag:**
- Missing null checks on data from API/database
- Array operations without length check
- Object property access without existence check
- Optional chaining not used where needed

### 2. Race Conditions

```typescript
// ❌ BAD: Race condition
let isProcessing = false;

async function processTask() {
  if (isProcessing) return;  // Too late! Multiple calls can pass this
  isProcessing = true;
  
  await slowOperation();
  isProcessing = false;
}

// ✅ GOOD: Proper locking
let processingPromise: Promise<void> | null = null;

async function processTask() {
  if (processingPromise) return processingPromise;
  
  processingPromise = slowOperation().finally(() => {
    processingPromise = null;
  });
  
  return processingPromise;
}
```

**Issues to flag:**
- Multiple simultaneous async operations on same resource
- State updates not atomic
- Double-click handlers without debounce
- Firestore writes racing with reads

### 3. Off-by-One Errors

```typescript
// ❌ BAD: Off-by-one (misses last item)
for (let i = 0; i < array.length - 1; i++) {
  // Process array[i]
}

// ✅ GOOD: Correct bounds
for (let i = 0; i < array.length; i++) {
  // Process array[i]
}

// ❌ BAD: Points calculation error
const pointsNeeded = targetPoints - currentPoints;  // Should be +1?
```

**Issues to flag:**
- Loop bounds incorrect (< vs <=, - 1 errors)
- Pagination math wrong
- Financial calculations off by one
- Array slicing at wrong index

## Pattern Compliance (P1 - High Priority)

### 1. Service Layer Pattern

```typescript
// ❌ BAD: Direct Firestore in component
const TaskList = () => {
  useEffect(() => {
    const q = query(collection(db, 'tasks'));
    const unsubscribe = onSnapshot(q, (snapshot) => {
      // Direct coupling!
    });
    return unsubscribe;
  }, []);
};

// ✅ GOOD: Service layer
const TaskList = () => {
  const { tasks } = useTaskStore();
  
  useEffect(() => {
    const unsubscribe = taskService.subscribeToFamilyTasks(
      familyId,
      (tasks) => useTaskStore.getState().setTasks(tasks),
      (error) => useTaskStore.getState().setError(error)
    );
    return unsubscribe;
  }, [familyId]);
};
```

**Flag if:**
- Direct Firebase imports in components/screens
- No error wrapping on service calls
- Subscriptions don't return cleanup
- Business logic in UI components

### 2. State Management Pattern

```typescript
// ❌ BAD: Direct mutation
useTaskStore.getState().tasks.push(newTask);  // Mutates state!

// ❌ BAD: Missing error state
interface State {
  tasks: Task[];
  // Where's isLoading? error?
}

// ✅ GOOD: Immutable update
useTaskStore.getState().setTasks([...tasks, newTask]);

// ✅ GOOD: Complete state
interface State {
  tasks: Task[];
  isLoading: boolean;
  error: string | null;
  setTasks: (tasks: Task[]) => void;
  setLoading: (loading: boolean) => void;
  setError: (error: string | null) => void;
}
```

**Flag if:**
- State mutations (array.push, object property assignment)
- Missing loading/error states
- Actions don't use set() function
- Store not typed

### 3. Error Handling Pattern

```typescript
// ❌ BAD: No error wrapper
export const getTask = async (id: string) => {
  const doc = await getDoc(doc(db, 'tasks', id));
  return doc.data();  // Can throw!
};

// ✅ GOOD: Wrapped with error handling
export const getTask = async (id: string): Promise<Task> => {
  return wrapServiceCall(
    async () => {
      const docSnap = await getDoc(doc(db, 'tasks', id));
      if (!docSnap.exists()) throw new Error('Task not found');
      return { id: docSnap.id, ...docSnap.data() } as Task;
    },
    'taskService.getTask'
  );
};
```

**Flag if:**
- Service methods not wrapped with wrapServiceCall
- Subscriptions not wrapped with wrapSubscription
- UI doesn't show error states
- Try-catch without logging or handling

## KidCoin-Specific Security Issues

### 1. Points/Payout Manipulation

```typescript
// ❌ CRITICAL: Client can set points arbitrarily
const approveTask = async (taskId: string, pointsEarned: number) => {
  await updateDoc(doc(db, 'tasks', taskId), {
    status: 'completed',
    pointsEarned,  // USER CONTROLLED! Can set to 9999999!
  });
};

// ✅ GOOD: Server determines points
const approveTask = async (taskId: string) => {
  const task = await getTask(taskId);
  const pointsEarned = task.points;  // From task definition, not user input
  
  await updateDoc(doc(db, 'tasks', taskId), {
    status: 'completed',
    pointsEarned,
  });
  
  await incrementUserPoints(task.assignedTo, pointsEarned);
};
```

**Flag if:**
- User can set their own points/balance
- Payout amounts user-controlled
- Financial calculations in client code
- No validation on point transfers

### 2. Family/User Isolation

```typescript
// ❌ CRITICAL: Can access other families' data
const getTasks = async (familyId: string) => {
  // No check if current user belongs to this family!
  const q = query(collection(db, 'tasks'), where('familyId', '==', familyId));
  return getDocs(q);
};

// ✅ GOOD: Verify family membership
const getTasks = async (familyId: string) => {
  const currentUser = auth.currentUser;
  if (!currentUser) throw new Error('Not authenticated');
  
  const userFamily = await getUserFamily(currentUser.uid);
  if (userFamily !== familyId) throw new Error('Not authorized');
  
  const q = query(collection(db, 'tasks'), where('familyId', '==', familyId));
  return getDocs(q);
};
```

**Flag if:**
- No family membership verification
- User can access other families' tasks/points
- Parent/child role not enforced
- Profile switching bypasses authorization

### 3. Photo Verification Security

```typescript
// ❌ BAD: Accepts any URL
const submitTaskProof = async (taskId: string, photoUrl: string) => {
  // User can provide ANY URL, including other users' private photos!
  await updateDoc(doc(db, 'tasks', taskId), { proofPhotoUrl: photoUrl });
};

// ✅ GOOD: Upload to user's storage path
const submitTaskProof = async (taskId: string, photoFile: File) => {
  const currentUser = auth.currentUser;
  if (!currentUser) throw new Error('Not authenticated');
  
  // Upload to user-specific path
  const storageRef = ref(storage, `families/${familyId}/tasks/${taskId}/proof.jpg`);
  const snapshot = await uploadBytes(storageRef, photoFile);
  const photoUrl = await getDownloadURL(snapshot.ref);
  
  await updateDoc(doc(db, 'tasks', taskId), { proofPhotoUrl: photoUrl });
};
```

**Flag if:**
- User provides storage URLs directly
- No path validation on uploads
- Photos not isolated by family
- No size/type validation on uploads

## What NOT to Flag (Low Priority / Style)

**DO NOT comment on:**
- Variable naming (unless truly confusing)
- Code formatting (Prettier handles this)
- Indentation, spacing, line breaks
- File organization (unless truly chaotic)
- Comment quality (unless security-critical code undocumented)
- Performance optimizations (unless causing real issues)
- Minor code duplication (< 5 lines)
- Test coverage percentage
- Using var vs let vs const (linter catches this)

**Focus ONLY on:**
- Security vulnerabilities
- Logic errors that cause bugs
- Data integrity issues
- Pattern violations that risk production incidents
- Missing error handling
- Resource leaks

## Output Format

### Finding Template

```markdown
## 🔴 P0 - BLOCKING: [Issue Title]

**Location:** `src/services/task.service.ts:42-48`

**Issue:**
The `approveTask` function accepts `pointsEarned` as a user-controlled parameter, allowing clients to arbitrarily set points when completing tasks.

**Evidence:**
```typescript
// Current code (line 42)
const approveTask = async (taskId: string, pointsEarned: number) => {
  await updateDoc(doc(db, 'tasks', taskId), {
    status: 'completed',
    pointsEarned,  // User can pass 9999999!
  });
};
```

**Impact:**
- Users can award themselves unlimited points
- Breaks the points-to-money conversion system
- Financial integrity compromised

**Fix:**
```typescript
// Recommended fix
const approveTask = async (taskId: string) => {
  const task = await getTask(taskId);
  const pointsEarned = task.points;  // From task definition
  
  await updateDoc(doc(db, 'tasks', taskId), {
    status: 'completed',
    pointsEarned,
  });
};
```

**Priority:** P0 - Must fix before merge
```

### Summary Format

```markdown
# Code Review: Feature X

**Files Changed:** 5  
**Lines Changed:** +247 / -189  
**Risk Level:** High (auth logic modified)

## Summary
Reviewed authentication flow changes. Found 2 critical security issues and 1 high-priority pattern violation.

## 🔴 Blocking Issues (P0): 2
1. Authorization bypass in deleteUser function
2. Points manipulation in approveTask function

## 🟠 High Priority (P1): 1
1. Missing error wrapper in taskService.getTask

## 🟢 Nice to Have (P2): 0

## Recommendation
❌ **BLOCK MERGE** - Fix P0 issues before proceeding.

---

[Detailed findings below]
```

## Best Practices for Reviewer Agent

### DO ✅
- **Focus on critical issues** - security, logic, data integrity
- **Provide evidence** - line numbers, code snippets
- **Suggest fixes** - don't just complain
- **Prioritize** - P0/P1/P2 classification
- **Explain impact** - why does this matter?
- **Check patterns** - KidCoin service/state/error patterns
- **Verify cleanup** - subscriptions, listeners, resources

### DON'T ❌
- **Don't comment on style** - Prettier handles formatting
- **Don't nitpick naming** - unless truly confusing
- **Don't demand perfection** - good enough is good enough
- **Don't flag trivial issues** - maintain high signal-to-noise
- **Don't rewrite code** - this agent doesn't modify
- **Don't suggest optimizations** - unless performance critical
- **Don't check test coverage** - tests are mandatory via quality gates

---

**Remember:** High signal-to-noise ratio. Only flag what genuinely matters for production quality and security.
