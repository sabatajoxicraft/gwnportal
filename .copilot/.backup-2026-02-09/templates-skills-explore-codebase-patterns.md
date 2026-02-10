---
name: explore-codebase-patterns
description: Systematic codebase exploration workflow for the explore agent (CodeScout). Use when asked to find patterns, trace logic, discover architecture, map dependencies, locate implementations, or understand how code works. Optimized for parallel exploration with grep/glob tools.
---

# Explore Agent: Codebase Pattern Discovery

## When to Use This Skill

Activate for **Explore agent tasks** when user asks:
- "Find all places where X is used"
- "How does [feature] work?"
- "Where is [logic] implemented?"
- "Trace the flow of [data]"
- "Map the architecture of [component]"
- "Discover patterns in [directory]"
- "What services interact with [module]?"

## Agent Role Context

**Agent Type:** Explore (CodeScout)  
**Model:** Haiku (fast, efficient for exploration)  
**Tools:** grep, glob, view (READ-ONLY, no edits)  
**Output:** Focused insights < 300 words  
**Execution:** Runs in parallel (safe to launch 3-5 simultaneously)

## Decision Tree

```
Exploration request received
│
├─ STEP 1: CLASSIFY EXPLORATION TYPE
│  ├─ Pattern Search: "Find all X"
│  ├─ Logic Trace: "How does Y work?"
│  ├─ Architecture Map: "Understand Z structure"
│  ├─ Dependency Discovery: "What depends on W?"
│  └─ Implementation Locator: "Where is V defined?"
│
├─ STEP 2: PLAN PARALLEL SEARCH STRATEGY
│  ├─ Identify search patterns (regex, globs)
│  ├─ Identify target directories
│  ├─ Prioritize: Most likely locations first
│  └─ Prepare 3-5 parallel searches
│
├─ STEP 3: EXECUTE PARALLEL SEARCHES
│  ├─ Launch grep/glob searches simultaneously
│  ├─ Use output_mode: "files_with_matches" for initial scan
│  ├─ Then use output_mode: "content" for detailed matches
│  └─ Aggregate results
│
├─ STEP 4: ANALYZE & SYNTHESIZE
│  ├─ Group results by pattern/context
│  ├─ Identify primary vs secondary matches
│  ├─ Trace relationships between files
│  └─ Extract key insights
│
└─ STEP 5: PRESENT FINDINGS (< 300 words)
   ├─ Summary: What was found
   ├─ Key locations: File paths + line numbers
   ├─ Patterns discovered
   └─ Recommendations for next exploration
```

## Exploration Patterns

### Pattern 1: Service Layer Discovery

**Goal:** Find all uses of a service (e.g., `taskService`)

**Parallel Search Strategy:**
```bash
# Search 1: Import statements
grep -n "import.*taskService" --glob="**/*.{ts,tsx}"

# Search 2: Direct usage
grep -n "taskService\." --glob="**/*.{ts,tsx}"

# Search 3: Type references
grep -n ": Task" --glob="**/*.{ts,tsx}"

# Search 4: Service definitions
glob "**/*task*.service.ts"

# Search 5: Test files
glob "**/*task*.test.{ts,tsx}"
```

**Synthesis:**
- Group by: Screens, Components, Stores, Services
- Identify: Service consumers, dependency chains
- Note: Patterns (REST vs Firestore, subscriptions vs one-time calls)

### Pattern 2: State Flow Tracing

**Goal:** Understand how state flows through app (e.g., Zustand store → components)

**Parallel Search Strategy:**
```bash
# Search 1: Store definition
grep -n "create.*Store" --glob="stores/**/*.ts"

# Search 2: Store usage (hooks)
grep -n "use.*Store" --glob="**/*.{ts,tsx}"

# Search 3: State selectors
grep -n "state\." --glob="stores/**/*.ts" -A 2

# Search 4: Action dispatches
grep -n "set\(" --glob="stores/**/*.ts"

# Search 5: Store subscribers
grep -n "subscribe" --glob="**/*.{ts,tsx}"
```

**Synthesis:**
- Map: Store → Actions → Selectors → Components
- Identify: State mutation points, side effects
- Note: Subscription patterns, re-render triggers

### Pattern 3: Navigation Structure Discovery

**Goal:** Map navigation routes and screens

**Parallel Search Strategy:**
```bash
# Search 1: Screen files
glob "**/*Screen.{ts,tsx}"

# Search 2: Navigation config
grep -n "createNativeStackNavigator\|createBottomTabNavigator" --glob="**/*.{ts,tsx}"

# Search 3: Navigation calls
grep -n "navigation\.navigate\|useNavigation" --glob="**/*.{ts,tsx}"

# Search 4: Route definitions
grep -n "createFileRoute\|Route" --glob="routes/**/*.{ts,tsx}"

# Search 5: Deep links
grep -n "linking.*config" --glob="**/*.{ts,tsx}"
```

**Synthesis:**
- Map: Navigation hierarchy (stacks, tabs, modals)
- Identify: Screen transitions, route parameters
- Note: Deep link patterns, auth guards

### Pattern 4: Firebase Integration Discovery

**Goal:** Find all Firebase usage patterns

**Parallel Search Strategy:**
```bash
# Search 1: Firebase imports
grep -n "from.*firebase" --glob="**/*.{ts,tsx}"

# Search 2: Firestore queries
grep -n "collection\(.*query\(" --glob="**/*.ts"

# Search 3: Real-time subscriptions
grep -n "onSnapshot" --glob="**/*.ts"

# Search 4: Firebase auth
grep -n "signIn\|signOut\|currentUser" --glob="**/*.ts"

# Search 5: Storage operations
grep -n "uploadBytes\|getDownloadURL" --glob="**/*.ts"
```

**Synthesis:**
- Group by: Auth, Firestore, Storage, Functions
- Identify: Query patterns, subscription management
- Note: Security rules implications, offline handling

### Pattern 5: API Endpoint Mapping

**Goal:** Discover all API calls and endpoints

**Parallel Search Strategy:**
```bash
# Search 1: Fetch calls
grep -n "fetch\(" --glob="**/*.{ts,tsx}"

# Search 2: Axios usage
grep -n "axios\." --glob="**/*.{ts,tsx}"

# Search 3: GraphQL queries
grep -n "useQuery\|useMutation" --glob="**/*.{ts,tsx}"

# Search 4: API service files
glob "**/*api*.{ts,tsx}" "**/services/**/*.ts"

# Search 5: Environment variables (endpoints)
grep -n "API_URL\|ENDPOINT" --glob="**/*.{ts,tsx}"
```

**Synthesis:**
- Map: API endpoints → services → components
- Identify: Request/response patterns, error handling
- Note: Authentication headers, retry logic

## Best Practices for Explore Agent

### DO ✅
- **Launch 3-5 parallel searches** - maximize speed
- **Start broad, then narrow** - files_with_matches → content
- **Use specific glob patterns** - `*.service.ts` not `*.ts`
- **Aggregate before presenting** - synthesize, don't dump raw results
- **Stay under 300 words** - be concise and actionable
- **Provide line numbers** - easy to navigate to findings
- **Group by context** - screens vs services vs stores

### DON'T ❌
- **Don't search entire codebase** - target specific directories
- **Don't return raw grep output** - synthesize findings
- **Don't explore sequentially** - use parallel searches
- **Don't exceed 300 words** - user needs quick insights
- **Don't propose changes** - Explore agent is READ-ONLY
- **Don't load huge files** - use view_range for large files

## Output Format

```markdown
## [Exploration Goal] - Findings

**Searched:** [directories/patterns]
**Found:** [N files, M matches]

### Primary Locations:
1. `path/to/file.ts:123` - [What's here]
2. `path/to/file2.tsx:45` - [What's here]
3. `path/to/file3.ts:89` - [What's here]

### Patterns Discovered:
- Pattern 1: [Description]
- Pattern 2: [Description]

### Key Insights:
[2-3 sentence summary of architecture/flow]

### Recommended Next Steps:
- [Action 1]
- [Action 2]
```

## Examples

### Example 1: Service Layer Discovery

**User:** "Find all places where taskService is used in KidCoin"

**Explore Agent Execution:**
```
Parallel searches launched:
1. grep "import.*taskService" *.tsx → 12 files
2. grep "taskService\." *.tsx → 28 matches
3. glob "**/task*.service.ts" → 3 services
4. grep "subscribeToFamilyTasks" → 5 subscriptions
5. glob "**/*Task*.tsx" → 8 components

Synthesis:
- Services: task.service.ts, prisma.task.service.ts, hybrid.service.ts
- Consumers: TasksScreen (3 calls), TaskCard (2), TaskDetailScreen (5)
- Patterns: Real-time subscriptions (5), one-time queries (3)
- Store integration: taskStore.ts uses subscriptions
```

**Output:**
```markdown
## Task Service Usage - Findings

**Searched:** services/, screens/, components/, stores/
**Found:** 12 files, 28 usage points

### Primary Locations:
1. `screens/tabs/TasksScreen.tsx:45` - Subscribes to family tasks
2. `components/TaskCard.tsx:23` - Marks task complete
3. `screens/TaskDetailScreen.tsx:67` - Updates task photo
4. `stores/taskStore.ts:34` - Real-time task synchronization

### Patterns Discovered:
- Real-time subscriptions: 5 screens use `subscribeToFamilyTasks`
- Service layer: All Firebase calls go through `task.service.ts`
- Error handling: Uses `wrapServiceCall` utility (5/8 locations)

### Key Insights:
Task service follows service layer pattern with Firebase backend. All screens subscribe to real-time updates via Firestore `onSnapshot`. Hybrid service routes to Prisma alternative when configured.

### Recommended Next Steps:
- Check error handling in 3 locations missing `wrapServiceCall`
- Verify subscription cleanup in useEffect returns
```

### Example 2: State Flow Tracing

**User:** "How does task data flow from Firestore to the UI?"

**Explore Agent Execution:**
```
Parallel searches:
1. grep "taskService\." stores/ → taskStore.ts found
2. grep "useTaskStore" screens/ → 5 screens found
3. grep "onSnapshot" services/ → subscription pattern found
4. grep "setState.*tasks" stores/ → state mutations found
5. glob "**/Task*.tsx" → task UI components found

Synthesis:
Flow: Firestore → taskService.subscribeToFamilyTasks() → onSnapshot callback → 
      taskStore.setTasks() → React components via useTaskStore()
```

**Output:**
```markdown
## Task Data Flow - Findings

**Searched:** services/, stores/, screens/, components/
**Found:** Data flow across 4 layers

### Flow Diagram:
1. `services/task.service.ts:67` - Firestore `onSnapshot` subscription
2. `stores/taskStore.ts:34` - Callback updates Zustand state
3. `screens/tabs/TasksScreen.tsx:23` - Consumes via `useTaskStore()`
4. `components/TaskCard.tsx:15` - Renders individual tasks

### Patterns Discovered:
- Real-time: Firestore subscription keeps state synced automatically
- State: Zustand store holds single source of truth
- Selectors: Components use `state => state.tasks` for reactivity

### Key Insights:
Task data flows through service layer → Zustand store → React components. Firestore subscriptions enable real-time updates without polling. Store provides derived state (filtered/sorted tasks) via selectors.

### Recommended Next Steps:
- Verify subscription cleanup in unmount
- Check for unnecessary re-renders (React DevTools)
```

## Circuit Breakers

**STOP if:**
- No matches found after 5 searches → Widen search scope or ask user for clarification
- Too many matches (> 100) → Narrow search with more specific patterns
- Search taking > 30 seconds → Kill searches, report timeout

**When stopping:**
1. Explain what was searched
2. Show partial results found
3. Suggest more specific search criteria
4. Offer alternative exploration strategies

## Integration with Other Agents

**After Explore findings:**
- User wants to modify code → Delegate to **Architect** (general-purpose agent)
- User wants to run tests → Delegate to **BuildBot** (task agent)
- User wants security review → Delegate to **Reviewer** (code-review agent)

**Explore agent does NOT:**
- Make code changes (read-only)
- Run builds or tests
- Provide implementation details

**Explore agent DOES:**
- Find patterns quickly
- Map architecture
- Trace dependencies
- Provide navigation insights

---

**Remember:** Explore fast, synthesize well, stay concise (< 300 words).
