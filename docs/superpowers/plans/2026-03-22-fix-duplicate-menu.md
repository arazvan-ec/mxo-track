# Plan: Fix Duplicate Menu in SPA Pages

## Goal
Unify navigation menu behavior: SPA pages should use overlay NavigationSidebar (same as Twig pages), not inline.

## Spec
`docs/superpowers/specs/2026-03-22-fix-duplicate-menu-design.md`

## Tasks

- [ ] **Task 1:** Edit `frontend/src/components/layout/DualMenuShell.tsx` — change NavigationSidebar from `mode="inline"` to `mode="overlay"`, move hamburger button into data sidebar header, add floating hamburger for collapsed state
- [ ] **Task 2:** Build frontend to verify no compile errors
- [ ] **Task 3:** Commit and push
