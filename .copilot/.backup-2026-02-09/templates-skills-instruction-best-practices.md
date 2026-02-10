---
name: instruction-best-practices
description: Validates and guides on instruction writing best practices. Use when creating or reviewing custom instructions, copilot-instructions.md, or similar configuration files. Enforces GitHub/Anthropic recommendations for optimal AI agent behavior.
---

# Instruction Best Practices Skill

## When to Use This Skill

Activate when:
- Creating new custom instructions files
- Reviewing existing instruction documents
- User asks about instruction format/quality
- Instructions are too long (>100 lines for main file)
- Instructions are in prose instead of table format

## Best Practice Sources

| Source | Key Recommendation |
|--------|-------------------|
| **GitHub Blog** | 5 sections max, tables > prose, under 2 pages |
| **GitHub Docs** | Max 1000 lines, distinct headings, reference other files |
| **VS Code Docs** | Reuse files via references, keep clean and focused |
| **Datablist** | Use separators, IF-THEN rules, positive directives |
| **Microsoft Azure** | Define orchestration patterns explicitly |
| **Anthropic/OpenAI** | Structured tool use, explicit role definition |

## Validation Checklist

### Length Validation
| Check | Pass | Fail |
|-------|------|------|
| Main file under 100 lines | ✅ | ❌ Split into modules |
| Total under 1000 lines | ✅ | ❌ Reduce redundancy |
| Each section < 20 lines | ✅ | ❌ Extract to reference |

### Format Validation
| Check | Good | Bad |
|-------|------|-----|
| Tables for lists | `\| IF \| THEN \|` | "If X happens, then do Y" |
| Section separators | `---` or `===` | No visual breaks |
| IF-THEN rules | `\| Condition \| Action \|` | Prose paragraphs |
| Role definition | Single line + table | Multi-paragraph explanation |

### Structure Validation
| Section | Required? | Format |
|---------|-----------|--------|
| Role | Yes | One-liner |
| Hierarchy | Yes | Table |
| Rules | Yes | DO/DON'T lists |
| Triggers | Yes | IF-THEN table |
| Standards | Yes | Table |

## Recommended File Structure

```
.github/
├── copilot-instructions.md    # Main file (<100 lines)
└── instructions/              # Reference files
    ├── auto-triggers.md       # Skill triggers
    ├── hierarchy.md           # Agent hierarchy
    ├── coding-standards.md    # Code rules
    └── phases.md              # Development phases
```

Or for .copilot:

```
.copilot/
├── mandate.md                 # Project requirements
├── m0-tasks.md               # Phase tasks
└── instructions/
    ├── auto-triggers.md
    ├── hierarchy.md
    └── ...
```

## File Reference Syntax

GitHub Copilot supports file references:

```markdown
# Using Markdown links
For details: [hierarchy.md](.copilot/instructions/hierarchy.md)

# Using #file syntax
See #file:.copilot/instructions/hierarchy.md for details
```

## Common Anti-Patterns

### ❌ Prose Format (Bad)
```markdown
You are an AI assistant that helps with coding. When you encounter 
a problem, you should think carefully about the solution and then
implement it step by step. Make sure to follow best practices...
```

### ✅ Table Format (Good)
```markdown
# ROLE
**OVERSEER** | Coordination only | Never code directly

# RULES
| IF | THEN |
|----|------|
| Problem found | Analyze first |
| Solution ready | Implement step-by-step |
```

### ❌ Too Long (Bad)
```markdown
# Main file: 250 lines of everything
- All rules inline
- All standards inline
- All examples inline
```

### ✅ Modular (Good)
```markdown
# Main file: 94 lines with references
For rules: [rules.md](./instructions/rules.md)
For standards: [standards.md](./instructions/standards.md)
```

## Validation Output Format

When this skill validates instructions:

```
📋 INSTRUCTION VALIDATION REPORT

✅ PASS:
- Main file: 94 lines (under 100)
- Format: Tables used
- Role: Clearly defined
- References: Modular structure

⚠️ WARNINGS:
- Consider adding more IF-THEN triggers
- Section X could use separators

❌ FAIL:
- [none]

RECOMMENDATION: File is compliant with best practices.
```

## Quick Fixes

| Issue | Fix |
|-------|-----|
| Too many lines | Extract to reference files |
| Prose format | Convert to tables |
| No separators | Add `---` between sections |
| No triggers | Add IF-THEN table |
| Vague role | Single-line role definition |

## Integration with MDDF

This skill is automatically invoked when:
1. User creates/modifies copilot-instructions.md
2. Running `mddf init` or `mddf mandate`
3. Instruction files exceed thresholds

## Best Practice Resources

- [GitHub Blog: 5 Tips for Custom Instructions](https://github.blog/ai-and-ml/github-copilot/5-tips-for-writing-better-custom-instructions-for-copilot/)
- [GitHub Docs: Custom Instructions](https://docs.github.com/copilot/customizing-copilot/adding-custom-instructions-for-github-copilot)
- [VS Code Docs: Custom Instructions](https://code.visualstudio.com/docs/copilot/customization/custom-instructions)
- [awesome-copilot](https://github.com/github/awesome-copilot) - Community examples
