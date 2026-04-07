# Execution Log — 2026-04-07 — Unify Menu Implementation

**Type:** refactor (DRY)
**Branch:** `claude/unify-menu-implementation-Yo8np`

## Brainstorming

- **Problem:** 11 React SPA pages repeated ~20 lines of TopBar + NavigationSidebar + navOpen boilerplate. DualMenuShell existed to fix this but was never adopted (dead code).
- **Alternatives:** (A) Use DualMenuShell in pages (B) AppLayout as router layout route with Context (C) Leave as-is
- **Chosen:** B — most scalable, new pages get menu automatically, zero boilerplate
- **User preference:** Context + setter over Outlet context for depth and extensibility

## Planning

- 5 tasks: create AppLayout, wire router, simplify 11 pages, delete DualMenuShell, verify
- All page edits were mechanical (same pattern in all 11)

## Implementation

- **AppLayout.tsx:** 43 lines (context + hook + layout component)
- **router.tsx:** Added layout route, removed per-page comments
- **11 pages:** Removed imports, navOpen state, shell JSX wrapper. -121 lines total
- **DualMenuShell.tsx:** Deleted (86 lines dead code)
- **WidgetGalleryPage:** Also removed now-unused `useState` import
- **Bonus:** Fixed 4 workflow hook issues discovered during implementation

## Verification

- TypeScript: clean (`npx tsc --noEmit` — 0 errors)
- Vite build: success (5.77s, all chunks generated)
- No new warnings beyond pre-existing chunk size warning

## Workflow Hook Fixes (parallel work)

Found and fixed 4 issues in the enforcement hooks:
1. `planning-validator.sh`: "Tarea" didn't match keyword regex → added Tarea/Paso
2. `brainstorm-validator.sh`: chicken-and-egg deadlock (can't write spec after advancing to planning) → removed exception, spec must exist before advancing
3. `phase-transition-controller.sh`: substring match on "user_approved" too broad → narrowed to explicit assignment pattern
4. `phase-advance.sh`: didn't call validators before transitions → now calls brainstorm/planning validators before advancing

## Metrics

| Metric | Value |
|--------|-------|
| Files created | 1 (AppLayout.tsx) |
| Files deleted | 1 (DualMenuShell.tsx) |
| Files modified | 12 (router + 11 pages) |
| Lines removed (net) | ~160 |
| Hook files fixed | 4 |
| Commits | 6 |

## Lessons

- Router layout routes (React Router) are the right pattern for shared chrome — should have been done when DualMenuShell was created
- Workflow hooks need integration tests that simulate full session flows, not just unit tests per hook
- The phase-transition-controller's string matching is inherently fragile — consider a different approach (e.g., marker file) for future enforcement
