# Implementation Plan — Add API Keys to Hamburger Menu

**Date:** 2026-03-23
**Spec:** `docs/superpowers/specs/2026-03-23-add-api-keys-menu-design.md`
**Complexity:** S (Small)
**Files affected:** 4

---

## File Structure

| File | Action | Purpose |
|------|--------|---------|
| `frontend/src/components/layout/NavigationSidebar.tsx` | Edit | Add `apiKey` icon SVG |
| `backend/src/Controller/Api/NavigationController.php` | Edit | Add API Keys menu item |
| `backend/translations/messages.es.yaml` | Edit | Add `nav.api_keys` translation |
| `backend/translations/messages.en.yaml` | Edit | Add `nav.api_keys` translation |

---

## Tasks

### Task 1: Add `apiKey` icon to NavigationSidebar

- [ ] Edit `frontend/src/components/layout/NavigationSidebar.tsx`
- Add `apiKey` entry to the `icons` object (after `billing`), using the key SVG path from the old Twig sidebar:
  ```tsx
  apiKey: (
    <svg className="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
      <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
    </svg>
  ),
  ```

### Task 2: Add API Keys item to NavigationController

- [ ] Edit `backend/src/Controller/Api/NavigationController.php`
- In `getAdminSections()`, add to the Administration section (after `ai_assistant`):
  ```php
  $this->item('nav.api_keys', '/admin/api-keys', 'apiKey'),
  ```

### Task 3: Add translations

- [ ] Edit `backend/translations/messages.es.yaml` — add `nav.api_keys: API Keys`
- [ ] Edit `backend/translations/messages.en.yaml` — add `nav.api_keys: API Keys`

### Task 4: Verify and push

- [ ] Run `make lint` to verify PHP syntax
- [ ] Run `npm run build` in frontend/ to verify TypeScript compiles
- [ ] Push branch
