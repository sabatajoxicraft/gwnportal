# CODING STANDARDS (FIXED)

> These standards are NON-NEGOTIABLE. Cannot be overridden.

## Architecture

| Category | Standard |
|----------|----------|
| Design | Atomic Design (atoms→molecules→organisms→templates→pages) |
| Styling | Tailwind CSS + shadcn/ui |
| Icons | Material Icons (primary), Lucide (fallback) |
| Types | TypeScript strict mode (no `any`) |

## File Structure

```
src/
├── components/
│   ├── atoms/       # Button, Input, Icon, Badge
│   ├── molecules/   # SearchBar, FormField, Card
│   ├── organisms/   # Header, Sidebar, DataTable
│   └── templates/   # PageLayout, AuthLayout
├── pages/           # Web routes (or screens/ for mobile)
├── hooks/           # Custom React hooks
├── utils/           # Pure utility functions
├── services/        # API calls and external services
├── stores/          # State management (Zustand/Redux)
├── theme/           # Design tokens and theme config
├── types/           # TypeScript type definitions
└── lib/             # Third-party library configurations
```

## Quality Gates

| Tier | Blocks Shipping? | Examples |
|------|------------------|----------|
| 🔴 CRITICAL | YES | Security, crashes, build failures |
| 🟡 WARNING | NO (track) | Type errors, test failures |
| 🟢 INFO | NO | Formatting, style |

**Milestone Complete = Feature Works** — not perfect code.

## Styling Rules

- NO inline styles - Tailwind classes only
- NO arbitrary values - Extend theme config
- NO CSS-in-JS - Tailwind utility-first
- Centralized theming in `theme/` directory
