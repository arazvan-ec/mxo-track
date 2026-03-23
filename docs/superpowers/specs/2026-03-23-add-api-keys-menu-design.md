# Design Spec — Add API Keys to Hamburger Menu

**Date:** 2026-03-23
**Type:** enhancement
**Bounded Context:** Pragmatic (UI/Frontend + Controller)
**Approach:** #1 — Add API Keys to existing menu structure

---

## Problem

During the unified React sidebar migration ([2026-03-22]), the API Keys menu item (`/admin/api-keys`) was not migrated from the old Twig `_sidebar_content.html.twig` to the new `NavigationController`. All other admin items were migrated.

## Solution

Add the missing API Keys entry to the NavigationController admin sections, with corresponding icon in NavigationSidebar.tsx and translations.

## Changes

1. **NavigationSidebar.tsx** — Add `apiKey` SVG icon (key icon from old Twig sidebar)
2. **NavigationController.php** — Add `$this->item('nav.api_keys', '/admin/api-keys', 'apiKey')` in Administration section
3. **messages.es.yaml** — Add `nav.api_keys: API Keys`
4. **messages.en.yaml** — Add `nav.api_keys: API Keys`

## Brainstorming Summary

### Alternatives Evaluated

1. **Approach 1 (chosen):** Add API Keys to existing menu — 3 files, ~5 lines. Minimal, focused.
2. **Approach 2:** Reorganize sections + add API Keys — Over-engineering for 1 item (YAGNI).
3. **Approach 3:** Do nothing (cache issue only) — Doesn't fix the genuinely missing API Keys item.

### User Decision

User approved Approach 1.

## Existing Functionality Inventory

| Element | Decision | Justification |
|---------|----------|---------------|
| NavigationController 5 sections | Include | No changes to structure |
| NavigationSidebar icon resolver | Include | Add new icon key |
| All existing 25 menu items | Include | No modifications |
| API Keys page (`/admin/api-keys`) | **Add to menu** | Was in old Twig sidebar, lost in migration |

## Omission Decisions

No omissions — all inventory items addressed.
