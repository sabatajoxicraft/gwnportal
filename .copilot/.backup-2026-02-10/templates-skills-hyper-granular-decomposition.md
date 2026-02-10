# Hyper-Granular Domain Decomposition Skill

> **Purpose:** Break tasks into 10-20 atomic, single-file, single-function subtasks that execute in parallel across 10+ specialized agents simultaneously.

---

## The 10+ Agent Strategy

**User Expectation:** "I expect it to deploy over 10 agents by finely decomposing the tasks"

**Key Principle:** Most tasks can be atomized into 15-30 operations with ZERO dependencies = 15-30 agents running in parallel.

### Comparison

| Approach | Task Breakdown | Agents | Parallelism | Time |
|----------|----------------|--------|-------------|------|
| **Traditional** | 5 coarse tasks | 3-5 | Low | 50s sequential |
| **Hyper-Granular** | 20 atomic tasks | 15-20 | Maximum | 12s parallel |

---

## Atomization Rules

### Rule 1: One File = One Agent
```yaml
❌ WRONG: "Create service files for family feature"
✅ RIGHT:
  agent_1: "Create empty file: src/services/family.service.ts"
  agent_2: "Create empty file: src/services/familyMember.service.ts"
  agent_3: "Create empty file: src/services/familyInvite.service.ts"
```

### Rule 2: One Function = One Agent
```yaml
❌ WRONG: "Implement family CRUD operations"
✅ RIGHT:
  agent_1: "Add createFamily() function to family.service.ts"
  agent_2: "Add getFamily() function to family.service.ts"
  agent_3: "Add updateFamily() function to family.service.ts"
  agent_4: "Add deleteFamily() function to family.service.ts"
  agent_5: "Add listFamilies() function to family.service.ts"
```

### Rule 3: One Type = One Agent
```yaml
❌ WRONG: "Define types for family domain"
✅ RIGHT:
  agent_1: "Define Family interface in types/family.ts"
  agent_2: "Define FamilyMember interface in types/familyMember.ts"
  agent_3: "Define FamilyInvite interface in types/familyInvite.ts"
  agent_4: "Define FamilySettings interface in types/familySettings.ts"
```

### Rule 4: One Component = One Agent
```yaml
❌ WRONG: "Build family management UI"
✅ RIGHT:
  agent_1: "Create FamilyScreen.tsx skeleton"
  agent_2: "Create FamilyCard.tsx component"
  agent_3: "Create FamilyList.tsx component"
  agent_4: "Create FamilyForm.tsx component"
  agent_5: "Create FamilyMemberItem.tsx component"
  agent_6: "Create AddMemberButton.tsx component"
```

### Rule 5: One Test File = One Agent
```yaml
❌ WRONG: "Write tests for family feature"
✅ RIGHT:
  agent_1: "Create family.service.test.ts with 5 test cases"
  agent_2: "Create FamilyScreen.test.tsx with 3 test cases"
  agent_3: "Create familyStore.test.ts with 4 test cases"
  agent_4: "Create FamilyForm.test.tsx with validation tests"
```

### Rule 6: One Mock = One Agent
```yaml
❌ WRONG: "Create mocks for Firebase"
✅ RIGHT:
  agent_1: "Create __mocks__/@react-native-firebase/firestore.ts"
  agent_2: "Create __mocks__/@react-native-firebase/auth.ts"
  agent_3: "Create __mocks__/firebase-test-data.ts with seed data"
```

---

## Example: "Implement Family Management Feature"

### Traditional Decomposition (5 agents)
```yaml
phase_1:
  - agent_1: "Create all types"          # 1 agent, 30s
phase_2:
  - agent_2: "Create service layer"     # 1 agent, 40s
phase_3:
  - agent_3: "Create UI components"     # 1 agent, 50s
phase_4:
  - agent_4: "Create tests"             # 1 agent, 30s
phase_5:
  - agent_5: "Run tests and fix"        # 1 agent, 20s

Total: 170 seconds (sequential)
```

### Hyper-Granular Decomposition (20 agents)

```yaml
wave_1_parallel: # 15 agents, 12s total
  # TYPES (5 agents)
  - agent_01: "Define Family type (types/family.ts)"
  - agent_02: "Define FamilyMember type (types/familyMember.ts)"
  - agent_03: "Define FamilyInvite type (types/familyInvite.ts)"
  - agent_04: "Define FamilySettings type (types/familySettings.ts)"
  - agent_05: "Define FamilyRole enum (types/familyRole.ts)"
  
  # SERVICE LAYER (8 agents)
  - agent_06: "Add createFamily() to family.service.ts"
  - agent_07: "Add getFamily() to family.service.ts"
  - agent_08: "Add updateFamily() to family.service.ts"
  - agent_09: "Add deleteFamily() to family.service.ts"
  - agent_10: "Add listFamilies() to family.service.ts"
  - agent_11: "Add addMember() to family.service.ts"
  - agent_12: "Add removeMember() to family.service.ts"
  - agent_13: "Add inviteMember() to family.service.ts"
  
  # COMPONENTS (2 agents)
  - agent_14: "Create FamilyScreen.tsx skeleton with navigation"
  - agent_15: "Create FamilyCard.tsx presentational component"

wave_2_parallel: # 10 agents, 10s total
  # MORE COMPONENTS (6 agents)
  - agent_16: "Create FamilyList.tsx with FlatList"
  - agent_17: "Create FamilyForm.tsx with Formik"
  - agent_18: "Create FamilyMemberItem.tsx"
  - agent_19: "Create AddMemberButton.tsx"
  - agent_20: "Create FamilyHeader.tsx"
  - agent_21: "Create FamilySettings.tsx"
  
  # HOOKS (3 agents)
  - agent_22: "Create useFamily() hook"
  - agent_23: "Create useFamilyMembers() hook"
  - agent_24: "Create useFamilyForm() hook"
  
  # STORE (1 agent)
  - agent_25: "Create familyStore.ts Zustand slice"

wave_3_parallel: # 12 agents, 15s total
  # TESTS (8 agents)
  - agent_26: "Create family.service.test.ts"
  - agent_27: "Create FamilyScreen.test.tsx"
  - agent_28: "Create FamilyList.test.tsx"
  - agent_29: "Create FamilyForm.test.tsx"
  - agent_30: "Create familyStore.test.ts"
  - agent_31: "Create useFamily.test.ts"
  - agent_32: "Create integration test for family CRUD flow"
  - agent_33: "Create E2E test for family feature"
  
  # MOCKS (3 agents)
  - agent_34: "Create Firebase Firestore mock"
  - agent_35: "Create Firebase Auth mock"
  - agent_36: "Create test data fixtures"
  
  # VALIDATION (1 agent)
  - agent_37: "Create family validation utils"

wave_4_sequential: # 1 agent, 10s total
  - agent_38: "Run full test suite and generate report"

Total: 36 agents, 47 seconds (parallel)
```

**Result:** 170s → 47s = **72% time savings**

---

## Decomposition Algorithm

### Input
```
Task: "Implement Family Management"
Context: React Native + Firebase + Zustand + Jest
```

### Step 1: Identify File Structure
```
Types:
  - types/family.ts
  - types/familyMember.ts
  - types/familyInvite.ts
  - types/familySettings.ts
  - types/familyRole.ts

Services:
  - services/family.service.ts

Screens:
  - screens/FamilyScreen.tsx
  - screens/FamilyDetailScreen.tsx

Components:
  - components/FamilyCard.tsx
  - components/FamilyList.tsx
  - components/FamilyForm.tsx
  - components/FamilyMemberItem.tsx
  - components/AddMemberButton.tsx

Hooks:
  - hooks/useFamily.ts
  - hooks/useFamilyMembers.ts

Store:
  - stores/familyStore.ts

Tests:
  - __tests__/services/family.service.test.ts
  - __tests__/screens/FamilyScreen.test.tsx
  - __tests__/components/FamilyList.test.tsx
  - __tests__/stores/familyStore.test.ts

Mocks:
  - __mocks__/@react-native-firebase/firestore.ts

Total: ~20 files
```

### Step 2: Identify Functions per File
```
family.service.ts:
  - createFamily()
  - getFamily()
  - updateFamily()
  - deleteFamily()
  - listFamilies()
  - addMember()
  - removeMember()
  - inviteMember()

familyStore.ts:
  - setFamilies()
  - setCurrentFamily()
  - addFamily()
  - updateFamily()
  - removeFamily()

Total: ~15 functions
```

### Step 3: Calculate Agent Count
```
Files: 20
Functions: 15
Types: 5
Components: 7
Tests: 8

Total atomic tasks: ~35-40
Maximum parallel agents: 15-20 (API limits)
Recommended waves: 3-4
```

### Step 4: Build Dependency Graph
```yaml
wave_1: # No dependencies, all parallel
  - All type definitions
  - All service function skeletons
  
wave_2: # Depends on types existing
  - Service function implementations
  - Component skeletons
  - Hook skeletons
  
wave_3: # Depends on services/components existing
  - Wire components together
  - Implement hooks
  - Create tests
  
wave_4: # Depends on everything existing
  - Run test suite
  - Generate documentation
```

### Step 5: Generate OVERSEER Command
```
OVERSEER executes:

1. Launch wave_1 (15 agents in ONE response using task tool)
2. Wait for all to complete (use list_agents + read_agent)
3. Aggregate results
4. Launch wave_2 (10 agents in ONE response)
5. Wait, aggregate
6. Launch wave_3 (12 agents in ONE response)
7. Wait, aggregate
8. Launch wave_4 (1 agent)
9. Report final status
```

---

## OVERSEER Integration

### Command Pattern
```javascript
// Wave 1: Launch 15 agents at once
<function_calls>
<invoke name="task">
  <parameter name="agent_type">general-purpose