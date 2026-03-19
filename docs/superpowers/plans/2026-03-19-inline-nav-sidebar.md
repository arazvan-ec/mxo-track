# Implementation Plan: Inline Navigation Sidebar

**Goal:** Change NavigationSidebar from overlay to inline rendering in DualMenuShell, with responsive behavior (inline on desktop, overlay on mobile).
**Spec:** `docs/superpowers/specs/2026-03-19-inline-nav-sidebar-design.md`
**Architecture:** React SPA frontend (pragmatic context)
**Complexity:** S

## File Structure

```
frontend/src/
  hooks/
    useIsDesktop.ts          # NEW — matchMedia hook for lg breakpoint
  components/layout/
    NavigationSidebar.tsx     # MODIFY — add mode prop
    DualMenuShell.tsx         # MODIFY — render nav inline on desktop
```

## Tasks

### Task 1: Create `useIsDesktop` hook
- [ ] Create `frontend/src/hooks/useIsDesktop.ts`
- [ ] Hook uses `window.matchMedia('(min-width: 1024px)')` with `useState` + `useEffect`
- [ ] Returns `boolean` — `true` when viewport >= 1024px
- [ ] Verify: `npm run build` passes

```typescript
// frontend/src/hooks/useIsDesktop.ts
import { useState, useEffect } from 'react';

const DESKTOP_QUERY = '(min-width: 1024px)';

export function useIsDesktop(): boolean {
  const [isDesktop, setIsDesktop] = useState(
    () => typeof window !== 'undefined' && window.matchMedia(DESKTOP_QUERY).matches
  );

  useEffect(() => {
    const mql = window.matchMedia(DESKTOP_QUERY);
    const handler = (e: MediaQueryListEvent) => setIsDesktop(e.matches);
    mql.addEventListener('change', handler);
    return () => mql.removeEventListener('change', handler);
  }, []);

  return isDesktop;
}
```

### Task 2: Add `mode` prop to NavigationSidebar
- [ ] Add `mode?: 'overlay' | 'inline'` to `Props` interface (default `'overlay'`)
- [ ] When `mode === 'inline'`:
  - No backdrop div rendered
  - Aside classes: `w-64 flex-shrink-0 bg-slate-800 flex flex-col border-r border-slate-700 h-full`
  - No close button in header (hamburger toggle in DualMenuShell handles it)
- [ ] When `mode === 'overlay'`: current behavior unchanged
- [ ] Verify: `npm run build` passes

### Task 3: Update DualMenuShell to use inline nav on desktop
- [ ] Import `useIsDesktop` hook
- [ ] Call `const isDesktop = useIsDesktop()`
- [ ] Render `NavigationSidebar` with `mode={isDesktop ? 'inline' : 'overlay'}` as first flex child inside the `flex h-screen` container
- [ ] Verify: `npm run build` passes

### Task 4: Build and verify
- [ ] Run `npm run build` — 0 TypeScript errors, successful Vite build
- [ ] Commit all changes

## Verification

1. `npm run build` passes (TypeScript + Vite)
2. Manual test: open `/app/admin/test-routing`, toggle nav hamburger — nav appears inline left of data sidebar on desktop
3. Resize to mobile width — nav appears as overlay
