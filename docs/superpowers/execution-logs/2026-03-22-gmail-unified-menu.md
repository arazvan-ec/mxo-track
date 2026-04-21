---
type: refactor
tags: []
files_touched: []
patterns: []
outcome: null
outcome_verified_at: null
regressions_later: []
pr_number: null
estimated_lines: null
actual_lines: null
duration_minutes: null
consulted_in_future: []
---

# Execution Log — 2026-03-22 — Unified React Gmail-style Menu

**Type:** refactor
**Branch:** `claude/gmail-style-menu-9w2SN`

---

### Phase: Brainstorming
- **Alternatives evaluated:**
  1. Mount React NavigationSidebar as standalone widget in Twig pages — single source of truth, minimal React changes
  2. Move all Twig pages to React SPA — too big a migration, not justified for menu unification
  3. Keep both sidebars but sync menu items via shared JSON config — adds complexity without eliminating duplication
- **Chosen approach:** Approach 1 (React widget in Twig) — reuses existing NavigationSidebar component, eliminates menu item duplication, minimal changes
- **Past decisions consulted:** [2026-03-19] DualMenuShell, [2026-03-19] NavigationSidebar inline vs overlay, [2026-03-17] React SPA coexistence
- **Complexity estimate:** M
- **Confidence:** high

### Phase: Planning
- **Task count:** 7
- **Files affected:** 5 — `sidebar-widget.html`, `sidebar-widget.tsx`, `sidebar-widget.css`, `vite.config.ts`, `base.html.twig`
- **Time estimate:** 30 minutes
- **Risk assessment:** low — no backend logic changes, only frontend/template changes

### Phase: Implementation
- **Actual time:** ~15 minutes
- **Blockers hit:**
  - Full-flow gate required registering active-spec file — resolved by writing to `.claude/active-spec`
  - `npm install` needed before build (node_modules not present) — resolved quickly
- **Plan deviations:** none
- **Debugging episodes:** none

### Phase: Verification
- **Tests:** N/A (frontend-only, no PHPUnit tests affected)
- **Lint:** clean (make lint: 0 errors)
- **Build:** `npm run build` successful — sidebar-widget.js (0.53 kB), NavigationSidebar chunk (74 kB gzip)
- **Coverage delta:** not measured (frontend-only change)

### Phase: Retrospective
- **Estimate accuracy:** accurate (estimated 30 min, actual ~15 min)
- **What worked:**
  1. Vite multi-page build with fixed entry file name worked cleanly
  2. NavigationSidebar already had overlay mode — zero changes needed to the React component
  3. `window.__mxoSidebarOpen` bridge between Twig and React is simple and reliable
  4. Reusing existing component instead of building new — zero duplication
- **What didn't:**
  1. Initial flow compliance: missed formal Skill 2 invocation and Skill 12 — caught and corrected
- **Lessons for future:**
  1. When Twig and React coexist, a widget approach (separate entry point) is cleaner than trying to embed React in Twig templates
  2. Fixed entry file names via `rollupOptions.output.entryFileNames` simplify Twig integration (no manifest.json parsing needed)
  3. If more React widgets are needed in Twig, replicate this pattern: separate HTML entry + standalone .tsx + fixed filename in rollupOptions
  4. If `window.__mxo*` globals proliferate beyond 2-3, consider a lightweight event bus instead
- **Design retrospective (Skill 12 Step 5):**
  - No patterns feel forced or over-engineered — minimal approach (50 LOC entry point, 1 global function)
  - Fixed entry filename loses cache busting on the tiny entry (0.53 kB), but chunked dependencies retain hash-based busting — acceptable trade-off
  - `_sidebar_content.html.twig` remains in repo but is no longer included — could be deleted in a follow-up cleanup commit
  - Pre-existing test failures on master (6 errors, 5 failures) — unrelated to this change, confirmed by running tests on both branches
- **Business context tags:** ui, navigation, frontend
- **Decision log entry needed?** yes — added [2026-03-22] unified React sidebar
