# Execution Log — 2026-04-07 — Fix Dashboard Display (Empty Page)

**Type:** bug fix
**Branch:** `claude/fix-dashboard-display-wDb6G`

## Root Cause

The `dashboard-widget.js` is an ES module (Vite builds with `import` statements), but
the Twig template loaded it as a regular script:

```html
<script src="{{ asset('app/assets/dashboard-widget.js') }}" defer></script>
```

Without `type="module"`, the browser fails silently on the `import` statement, preventing
React from mounting. The `sidebar-widget.js` and `topbar-widget.js` already used
`type="module"` correctly — the dashboard widget was the inconsistent one.

## Fix

Changed the script tag in `backend/templates/admin/dashboard.html.twig`:

```html
<script type="module" src="{{ asset('app/assets/dashboard-widget.js') }}"></script>
```

## Pattern-Wide Search

Searched all templates for similar `<script src=...widget` patterns without `type="module"`.
No other instances found — only the dashboard widget had this bug.

## Lesson

When adding new Vite entry points that produce ES modules, always use `type="module"`
in the script tag. The `defer` attribute is unnecessary with modules (they defer by default).
