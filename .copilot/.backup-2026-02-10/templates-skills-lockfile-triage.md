# MDDF Skill: Lockfile Triage Protocol

**Version:** 1.0  
**Type:** Diagnostic & Recovery  
**Trigger:** Any CI failure mentioning lockfile, frozen-lockfile, or dependency mismatch  
**Learned From:** CRC Kimberley Carpool - February 2026 incident analysis

---

## 🚨 The Problem This Solves

**Symptom:** Agent loops through multiple "fixes" for lockfile issues without resolving root cause  
**Cost:** 3+ failed attempts, wasted CI cycles, user frustration  
**Root Cause:** Misdiagnosing infrastructure problems (pnpm version, workspace config) when actual issue is stale/incompatible lockfile data

---

## 📋 Pre-Flight Checklist (MANDATORY)

Before ANY lockfile-related fix, run these checks **in parallel**:

```bash
# 1. Lockfile metadata
head -5 pnpm-lock.yaml | grep "lockfileVersion"

# 2. Last modified dates
git log -1 --format="%ai" -- pnpm-lock.yaml
git log -1 --format="%ai" -- package.json

# 3. Current pnpm version
pnpm --version

# 4. Declared pnpm version
grep packageManager package.json

# 5. Recent dependency changes
git diff HEAD~3 -- package.json | grep -A2 -B2 "dependencies"
```

**Decision Matrix:**
| Finding | Action |
|---------|--------|
| Lockfile older than package.json | → REGENERATE |
| lockfileVersion ≠ pnpm major version | → VERSION SYNC |
| No lockfile exists | → GENERATE |
| Dependencies added without lockfile update | → REGENERATE |

---

## 🔧 Resolution Workflow

### Step 1: Diagnose (30 seconds)
```bash
# Single command diagnosis
pnpm install --dry-run 2>&1 | tee lockfile-diagnosis.txt
```

**Parse output for:**
- `ERR_PNPM_OUTDATED_LOCKFILE` → Stale content
- `WARN Ignoring not compatible lockfile` → Version mismatch
- `frozen-lockfile` errors → CI config issue

### Step 2: Fix Root Cause
**DO NOT fix symptoms (workflows, configs) until root cause is addressed:**

#### Scenario A: Stale Lockfile
```bash
# Delete and regenerate
rm pnpm-lock.yaml
pnpm install
git add pnpm-lock.yaml
git commit -m "chore: Regenerate lockfile to match current dependencies"
```

#### Scenario B: Version Mismatch (lockfileVersion 9.0 but pnpm 8.x)
```bash
# Option 1: Upgrade pnpm to match lockfile
corepack enable
corepack prepare pnpm@9 --activate
pnpm install  # Updates lockfile if needed

# Option 2: Downgrade and regenerate (NOT recommended)
npm install -g pnpm@8
rm pnpm-lock.yaml
pnpm install
```

#### Scenario C: CI Configuration
**ONLY after A or B is resolved:**
```yaml
# .github/workflows/*.yml
- uses: pnpm/action-setup@v4
  with:
    version: 9  # MUST match lockfileVersion
- run: pnpm install --frozen-lockfile  # Enforces no drift
```

### Step 3: Circuit Breaker
**If 2 attempts fail with SAME error:**
1. Stop attempting fixes
2. Escalate to user with diagnostic report:
   ```
   LOCKFILE TRIAGE FAILED
   Attempts: 2
   Pattern: [Same error repeated]
   Diagnostic: [Attach lockfile-diagnosis.txt]
   Recommendation: Manual intervention required
   ```

---

## 🎯 Success Criteria

- [ ] `pnpm install` runs without warnings
- [ ] CI passes with `--frozen-lockfile`
- [ ] `git status` shows no lockfile changes after fresh install
- [ ] All team members using same pnpm version

---

## 🧠 Learning Loop Integration

### Store to Memory
After resolution, store these facts:
```
Category: bootstrap_and_build
Subject: lockfile management
Fact: lockfileVersion X.Y requires pnpm major version X
Citations: [This incident + pnpm docs]
```

### Update MDDF Session State
Log to `session-state/failures.jsonl`:
```json
{
  "timestamp": "2026-02-07T16:00:00Z",
  "skill": "lockfile-triage",
  "trigger": "ERR_PNPM_OUTDATED_LOCKFILE",
  "root_cause": "stale_lockfile_content",
  "attempts": 3,
  "resolution": "regenerate_lockfile",
  "prevented_by": "missing_pre_flight_check"
}
```

### Improve Detection
If this skill is triggered >2 times in a session:
1. Add pre-emptive lockfile check to project bootstrap
2. Update CI to fail fast with clear diagnostic on lockfile mismatch
3. Add `.github/CONTRIBUTING.md` section on lockfile management

---

## 📚 References

### Research Findings
- **pnpm lockfileVersion mapping:** v6.x = pnpm 8.x, v9.0 = pnpm 9.x
- **Frozen lockfile best practice:** Always use in CI to prevent supply chain attacks
- **Error taxonomy:** Infrastructure errors ≠ Data errors (don't confuse layers)

### External Resources
- [pnpm Lockfile Best Practices](https://github.com/antfu/skills/blob/main/skills/pnpm/references/best-practices-ci.md)
- [AI Agent Error Patterns](https://arxiv.org/pdf/2509.25370) - AgentErrorBench research
- [Supply Chain Security](https://charlesjones.dev/blog/npm-supply-chain-attacks-ci-cd-locked-dependencies)

---

## 🎓 Meta-Learning Notes

**Why this skill works:**
1. **Pattern Recognition:** Identifies symptom vs root cause
2. **Parallel Diagnostics:** Gathers all context before acting
3. **Circuit Breaker:** Prevents infinite loops
4. **Feedback Loop:** Logs failures to improve future detection

**Evolution path:**
- v1.0: Manual trigger (human recognizes lockfile issue)
- v2.0: Auto-detect via CI failure message parsing
- v3.0: Preventive mode (pre-flight check before any dependency change)

**When to update this skill:**
- pnpm releases breaking lockfile format changes
- New error patterns emerge (log to failures.jsonl)
- Team feedback indicates blind spots
