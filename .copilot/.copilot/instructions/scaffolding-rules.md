# Scaffolding Rules

## MANDATORY: Use CLI Tools

| Stack | Scaffold Command | NEVER Manually Create |
|-------|-----------------|----------------------|
| React Native | `npx react-native init AppName --template react-native-template-typescript` | android/, ios/, package.json |
| Next.js | `npx create-next-app@latest` | next.config.js, package.json |
| Vite | `npm create vite@latest` | vite.config.ts |
| Remix | `npx create-remix@latest` | remix.config.js |

---

## Config Error Response

IF config/build error from scaffold THEN:
1. **DELETE** the broken scaffold folder
2. **RE-SCAFFOLD** using the CLI command above
3. **DO NOT** manually fix config files
4. **DO NOT** chase cascading errors

---

## Why This Matters

Manual config file creation leads to:
- Version mismatches
- Missing peer dependencies
- Incorrect native configurations
- Cascading build failures

CLI scaffolding ensures:
- Correct dependency versions
- Proper native setup (android/, ios/)
- Working build configurations
- Tested project templates

---

## Migration Revert Checklist

When reverting a migration (e.g., TanStack Router → React Navigation):

1. **Find ALL imports** of the old package:
   ```bash
   grep -r "@old-package" src/ --include="*.tsx" --include="*.ts"
   ```

2. **Fix ALL occurrences in ONE commit** - not one-at-a-time

3. **Verify zero matches** before pushing:
   ```bash
   grep -r "@old-package" src/ && echo "STILL HAS IMPORTS" || echo "CLEAN"
   ```

4. **Run bundle check** (React Native):
   ```bash
   timeout 60 npx react-native bundle --platform android --dev false \
     --entry-file index.js --bundle-output /tmp/test.js
   ```

---

## Pre-Push Validation (React Native)

Before pushing, verify Metro can resolve all imports:

```bash
# Quick bundle check (catches import errors)
timeout 60 npx react-native bundle --platform android --dev false \
  --entry-file index.js --bundle-output /tmp/test.js --reset-cache

# If this fails, DO NOT push - fix locally first
```

This takes 60 seconds vs 10+ minutes in CI.

---

## Batch Fix Pattern

When fixing import errors:

1. **NEVER** fix one file and push
2. **ALWAYS** search for pattern across codebase first:
   ```bash
   grep -r "broken/path" src/
   ```
3. Fix ALL occurrences in ONE commit
4. Verify with bundle check
5. THEN push
