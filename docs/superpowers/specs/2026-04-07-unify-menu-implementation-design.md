# Spec — Unify Menu Implementation via AppLayout

**Date:** 2026-04-07
**Type:** Refactor (DRY)
**Branch:** `claude/unify-menu-implementation-Yo8np`

## Problem

11 React SPA pages each repeat identical boilerplate (~20 lines) for TopBar + NavigationSidebar + navOpen state. `DualMenuShell.tsx` was created to solve this but was never adopted (dead code). Adding a new navigation feature requires touching 11 files.

## Decision

Create `AppLayout` as a React Router layout route with Context. Opcion B (router-level layout) chosen over Opcion A (manual DualMenuShell usage) for scalability — new pages automatically get menu without any boilerplate.

## Existing Functionality Inventory

| Element | Decision | Justification |
|---------|----------|---------------|
| `DualMenuShell.tsx` (86 lines, unused) | **Delete** | Replaced by `AppLayout` |
| `NavigationSidebar.tsx` (249 lines) | **Keep** | Used by AppLayout, unchanged |
| `TopBar.tsx` (79 lines) | **Keep** | Used by AppLayout, unchanged |
| `sidebar-widget.tsx` (Twig bridge) | **Keep** | Still needed for Twig pages |
| `topbar-widget.tsx` (Twig bridge) | **Keep** | Still needed for Twig pages |
| `useState(navOpen)` in 11 pages | **Remove** | Managed by AppLayout |
| `<NavigationSidebar>` in 11 pages | **Remove** | Mounted by AppLayout |
| `<TopBar>` in 11 pages | **Remove** | Mounted by AppLayout |

## Omission Decisions

| Element | Decision | Justification |
|---------|----------|---------------|
| `_sidebar_content.html.twig` | Not touched | Already dead code, separate cleanup |
| Twig widget entry points | Not touched | Still serve Twig pages |

## Alternativa A — Use DualMenuShell in all SPA pages

Each page wraps content in `<DualMenuShell>`. Trade-off: still requires import + wrapper in every page. Does not scale — new pages must remember to add the wrapper.

## Alternativa B — AppLayout as router layout route (CHOSEN)

Router wraps all `/app` children with `<AppLayout>`. Trade-off: pages cannot opt out of the shell (acceptable — all SPA pages need it). Ventaja: zero boilerplate in pages, new pages get menu automatically.

## Alternativa C — Leave as-is

No change. Trade-off: 11 files to touch for any menu feature. Desventaja: DualMenuShell remains dead code.

## Architecture

```
AppLayoutProvider (Context: setExtraControls)
  └── AppLayout
        ├── NavigationSidebar (overlay, controlled internally)
        ├── TopBar (hamburger + extraControls from context)
        └── <Outlet /> ← page components
```

### Context API

```typescript
interface AppLayoutContext {
  setExtraControls: (node: ReactNode) => void;
}

function useAppLayout(): AppLayoutContext;
```

## Files Affected

- **New:** `frontend/src/components/layout/AppLayout.tsx` (~50 lines)
- **Delete:** `frontend/src/components/layout/DualMenuShell.tsx`
- **Modify:** `frontend/src/router.tsx` (layout route)
- **Simplify:** 11 page files (remove shell boilerplate)
