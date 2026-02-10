---
name: pre-migration-compatibility-check
description: Research and verify platform compatibility BEFORE migrating frameworks, libraries, or dependencies. Use when user requests to migrate, upgrade, switch from X to Y, add new framework, replace library, or change tech stack. Prevents incompatible technology choices.
---

# Pre-Migration Compatibility Check

## When to Use This Skill

Activate this skill when user mentions:
- "migrate from X to Y"
- "upgrade to version X"
- "switch to library X"
- "replace X with Y"
- "add framework X"
- "use X instead of Y"
- ANY technology stack change

## Critical Rule

**STOP ALL IMPLEMENTATION** until compatibility is verified!
- Do NOT install packages
- Do NOT modify code
- Do NOT change configuration
- RESEARCH FIRST, IMPLEMENT SECOND

## Decision Tree

```
User wants to change technology
│
├─ STEP 1: STOP IMPLEMENTATION
│  └─ Do NOT proceed with any code changes yet
│
├─ STEP 2: GATHER CONTEXT
│  ├─ Current platform: Web? Mobile (React Native/Flutter)? Server? Desktop?
│  ├─ Current stack: Framework versions, language versions
│  ├─ Target technology: What does user want to add/migrate to?
│  └─ Purpose: Why is this change needed?
│
├─ STEP 3: RESEARCH COMPATIBILITY
│  ├─ Use web_search tool for each query:
│  │  ├─ "[Target Technology] platform compatibility"
│  │  ├─ "[Target Technology] + [Current Platform] compatibility"
│  │  ├─ "[Target Technology] requirements"
│  │  ├─ "[Target Technology] known issues"
│  │  └─ "[Target Technology] + [Current Framework Version]"
│  │
│  ├─ Check official documentation:
│  │  ├─ Does it support current platform? (web vs mobile vs server)
│  │  ├─ Does it require dependencies not available on platform?
│  │  ├─ Are there version constraints or conflicts?
│  │  └─ Are there documented limitations?
│  │
│  └─ Check community evidence:
│     ├─ Are people successfully using it on this platform?
│     ├─ Are there open issues about compatibility?
│     ├─ What do recent discussions say (2024-2026)?
│     └─ Any workarounds required?
│
├─ STEP 4: CLASSIFY RESULT
│  ├─ ✅ GO: Strong evidence of compatibility
│  │  ├─ Official docs confirm platform support
│  │  ├─ Community successfully using it
│  │  ├─ No showstopper issues found
│  │  └─ Version requirements are met
│  │
│  ├─ ⚠️ CAUTION: Partial compatibility
│  │  ├─ Works but requires workarounds
│  │  ├─ Experimental support only
│  │  ├─ Some features not available
│  │  └─ Community reports mixed results
│  │
│  └─ ❌ NO-GO: Incompatible
│     ├─ Official docs state NOT supported
│     ├─ Requires dependencies not available on platform
│     ├─ Community confirms it doesn't work
│     └─ Fundamental platform mismatch (e.g., web-only library on mobile)
│
├─ STEP 5: PRESENT FINDINGS TO USER
│  ├─ Summary of research (2-3 sentences)
│  ├─ Evidence (links to docs, discussions, issues)
│  ├─ Recommendation: GO / CAUTION / NO-GO
│  ├─ If NO-GO: Suggest alternatives
│  ├─ If CAUTION: List required workarounds
│  └─ ASK: "Should we proceed with this migration?"
│
└─ STEP 6: WAIT FOR USER APPROVAL
   ├─ If user approves → Proceed with implementation
   ├─ If user rejects → Suggest alternatives
   └─ If user wants more info → Do additional research
```

## Workflow

### Phase 1: Stop and Gather Context (No Code Changes!)

1. **Identify current environment:**
   ```
   - What platform? (Web, React Native, Node.js, etc.)
   - What versions? (React 18, RN 0.75, Node 20, etc.)
   - What's the current tech stack?
   ```

2. **Identify target technology:**
   ```
   - What library/framework does user want?
   - What version?
   - What's the stated purpose?
   ```

### Phase 2: Research Compatibility

1. **Official Documentation Check:**
   - Use web_search: "[Technology Name] official documentation"
   - Look for: "Platforms supported", "Requirements", "Compatibility"
   - RED FLAG keywords: "web-only", "browser-only", "requires react-dom", "Node.js only"

2. **Platform-Specific Search:**
   - Use web_search: "[Technology] + [Platform] compatibility"
   - Example: "TanStack Router React Native compatibility"
   - Example: "Library X Flutter support"

3. **Version Requirements:**
   - Check if target technology has version constraints
   - Verify current platform meets minimum requirements
   - Check for known version conflicts

4. **Community Validation:**
   - Use web_search: "[Technology] + [Platform] issues"
   - Check GitHub issues, discussions, Stack Overflow
   - Look for: Working examples, unsolved problems, workarounds

### Phase 3: Evidence-Based Decision

**✅ GREEN LIGHT (GO) if:**
- Official docs explicitly list platform as supported
- Multiple community examples of successful usage
- No showstopper issues in recent discussions (2024-2026)
- Version requirements are compatible

**⚠️ YELLOW LIGHT (CAUTION) if:**
- Experimental/beta support only
- Requires non-trivial workarounds or configuration
- Limited community adoption on this platform
- Some features won't work

**❌ RED LIGHT (NO-GO) if:**
- Official docs state platform NOT supported
- Requires dependencies fundamentally incompatible with platform (e.g., react-dom on React Native)
- Community consensus is "it doesn't work"
- Recent issues show it's broken and unmaintained

### Phase 4: Present Findings

Use this format:

```markdown
## Compatibility Research: [Technology Name]

**Platform:** [Current Platform/Framework]
**Target:** [Technology and Version]

### Findings:
[2-3 sentence summary]

### Evidence:
1. [Link 1]: [What it says]
2. [Link 2]: [What it says]
3. [Link 3]: [What it says]

### Recommendation: ✅ GO / ⚠️ CAUTION / ❌ NO-GO

[If NO-GO, list alternatives]
[If CAUTION, list required workarounds]

### Next Steps:
Would you like to proceed with this migration?
```

### Phase 5: Wait for Approval

**Do NOT proceed with implementation until user explicitly approves.**

## Circuit Breakers

**NEVER bypass research, even if:**
- User seems confident about compatibility
- Technology looks similar to one that works
- Package installs without errors

**The TanStack Router example:**
- Would have been caught in 2 minutes of research
- Official docs clearly state "web-only"
- Could have saved 4 failed builds and hours of debugging

## Examples

### Example 1: Successful Use (Prevented Disaster)

```
User: "Let's migrate from React Navigation to TanStack Router"

Skill Activated: pre-migration-compatibility-check

Research:
- Searched: "TanStack Router platform compatibility"
- Found: Official docs state "only compatible with React (with ReactDOM)"
- Found: Community discussion confirms NOT React Native compatible
- Classification: ❌ NO-GO

Presented Findings:
"TanStack Router is web-only and requires react-dom, which doesn't exist in 
React Native. It is fundamentally incompatible with mobile apps. 

Alternative options:
1. Keep React Navigation (proven, stable)
2. Try Expo Router (RN-compatible file-based routing)
3. Improve current React Navigation setup

Recommendation: Do NOT proceed with TanStack Router for React Native."

Result: User avoided wasting hours on incompatible technology ✅
```

### Example 2: Green Light Given

```
User: "Let's upgrade React Native from 0.74 to 0.76"

Skill Activated: pre-migration-compatibility-check

Research:
- Searched: "React Native 0.76 release notes"
- Found: Official release, stable version
- Found: Community reports mostly positive
- Checked: Breaking changes documented
- Classification: ⚠️ CAUTION (breaking changes exist)

Presented Findings:
"React Native 0.76 is officially released and stable. Breaking changes:
1. Metro bundler config changes
2. Some deprecated APIs removed
3. New architecture enabled by default (can be disabled)

Community reports it's generally stable but requires migration steps.

Recommendation: ⚠️ PROCEED WITH CAUTION
Follow official migration guide: [link]

Would you like to proceed?"

User: "Yes, let's do it carefully"

Result: Proceeded with migration, aware of breaking changes ✅
```

### Example 3: Alternative Suggested

```
User: "Add Prisma to React Native app for local database"

Skill Activated: pre-migration-compatibility-check

Research:
- Searched: "Prisma React Native compatibility"
- Found: Prisma requires Node.js runtime
- Found: React Native doesn't have Node.js runtime
- Found: Community uses WatermelonDB or Realm instead
- Classification: ❌ NO-GO

Presented Findings:
"Prisma requires Node.js runtime and file system access, neither of which 
exist in React Native mobile environment. Prisma is designed for server-side 
or Node.js applications, not mobile apps.

Alternative options for React Native:
1. WatermelonDB (optimized for mobile)
2. Realm (cross-platform mobile DB)
3. SQLite (via expo-sqlite or react-native-sqlite-storage)

Recommendation: Use WatermelonDB or Realm instead.

Would you like to explore these alternatives?"

Result: User saved from attempting impossible integration ✅
```

## Red Flags in Documentation

**STOP immediately if you see:**
- "Web-only"
- "Browser-only"
- "Requires react-dom"
- "Node.js only" (when target is mobile/browser)
- "Server-side only" (when target is client)
- "Not compatible with [your platform]"
- "Experimental support for [your platform]"

## Success Criteria

This skill succeeds when:
1. ✅ Compatibility researched BEFORE any code changes
2. ✅ Evidence-based decision made (not assumptions)
3. ✅ User presented with clear GO/CAUTION/NO-GO recommendation
4. ✅ If NO-GO: Alternatives suggested
5. ✅ User makes informed decision

## Failure Prevention

This skill prevents:
- Wasted time on incompatible technologies
- Multiple failed CI/CD builds
- Debugging symptoms instead of root causes
- "Tail spin" debugging cycles
- Project abandonment due to impossible technology choices

---

**Remember:** 2 minutes of research prevents hours of wasted debugging!
