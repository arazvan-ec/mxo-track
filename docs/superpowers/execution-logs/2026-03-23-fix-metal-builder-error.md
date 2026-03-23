# Execution Log — 2026-03-23 — Fix Metal Builder TS2322 Error

**Type:** bug fix
**Branch:** `claude/fix-metal-builder-error-TtPVP`

---

## Root Cause

`BottomSheet` component typed its `title` prop as `string`, but `FleetMapPage.tsx:146` passes a JSX `<span>` element (containing a connection status indicator dot). TypeScript correctly rejects `Element` as not assignable to `string`.

## Pattern-Wide Search

Searched all 9 `<BottomSheet` usages across the frontend. Only `FleetMapPage` passes JSX — all others pass string literals or string variables. No other instances of this defect.

## Fix

Changed `title: string` to `title: ReactNode` in `BottomSheet.tsx`. This is backward-compatible — all existing string usages work with `ReactNode`.

## Verification

- TypeScript compilation: 0 errors (exit 0)
- Backend tests: 6 errors, 5 failures — all pre-existing on main branch (driver routes HTTP 500, unrelated to this change)

## Retrospective

- **Estimate accuracy:** S (small) — accurate, single-line fix
- **What worked:** Pattern-wide search confirmed the fix was isolated
- **Lesson:** When migrating components to shared usage, prop types should be as flexible as the intended use cases (ReactNode for display props)
