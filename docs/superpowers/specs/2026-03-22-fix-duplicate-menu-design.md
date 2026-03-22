# Fix Duplicate Menu in SPA Pages

## Problem

Users see two different menu experiences across the app:
- **Twig pages** (`/admin/*`): hamburger button opens NavigationSidebar as **overlay**
- **SPA pages** (`/app/admin/*`): DualMenuShell renders NavigationSidebar as **inline** panel

This creates visual inconsistency — the navigation menu looks and behaves differently depending on which page type you're on.

## Root Cause

`DualMenuShell.tsx` renders `NavigationSidebar` with `mode="inline"`, while Twig pages (via `sidebar-widget.tsx`) use `mode="overlay"`.

## Fix

Change `DualMenuShell` to use `mode="overlay"` for NavigationSidebar. Move the hamburger trigger into the data sidebar header (when data sidebar is open) or show it as a floating button on the map (when data sidebar is collapsed).

## Files Changed

- `frontend/src/components/layout/DualMenuShell.tsx` — switch NavigationSidebar from inline to overlay mode

## Bounded Context

Pragmatic (UI/frontend) — no domain logic involved.
