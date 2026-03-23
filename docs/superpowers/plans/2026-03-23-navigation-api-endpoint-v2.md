# Plan: Navigation API Endpoint (v2)

**Spec:** `docs/superpowers/specs/2026-03-23-navigation-api-endpoint-design-v2.md`
**Goal:** SSoT navigation menu via `GET /api/navigation` with all routes from route inventory
**Complexity:** M (6 tasks, TDD)

---

## File Structure

| File | Action | Purpose |
|------|--------|---------|
| `backend/tests/Controller/Api/NavigationControllerTest.php` | Create | Tests for endpoint per role |
| `backend/src/Controller/Api/NavigationController.php` | Create | API endpoint |
| `backend/translations/messages.es.yaml` | Edit | Add ~12 missing translation keys |
| `backend/translations/messages.en.yaml` | Edit | Add ~12 missing translation keys |
| `frontend/src/api/types.ts` | Edit | Add NavigationResponse types |
| `frontend/src/api/hooks/useNavigation.ts` | Create | React hook |
| `frontend/src/components/layout/NavigationSidebar.tsx` | Edit | Consume hook, remove hardcoded items, add active state, add 5 icons |
| `backend/templates/_sidebar_content.html.twig` | Delete | Deprecated template |

Inventory reference: all items from spec's Route Registry Inventory and Existing Functionality Inventory are addressed.

---

## Tasks

### Task 1: Write failing tests for NavigationController

- [ ] Create `backend/tests/Controller/Api/NavigationControllerTest.php`
  - Test: unauthenticated request returns redirect/401
  - Test: admin user gets response with sections array containing expected titles
  - Test: admin response contains all expected hrefs from route inventory
  - Test: response has correct Cache-Control header
  - Test: response items have label, href, icon structure
- [ ] Run tests, verify they FAIL (RED)
- [ ] Commit: `test: add failing tests for NavigationController`

### Task 2: Implement NavigationController + translations (GREEN)

- [ ] Create `backend/src/Controller/Api/NavigationController.php`
  - Admin: 5 sections (Principal, Operaciones, Administracion, Seguimiento, Dev Tools)
  - Customer: 3 sections (Principal, Mis Entregas, Seguimiento)
  - Driver: 1 section (Conductor)
  - All routes from spec inventory included
- [ ] Add missing translation keys to messages.es.yaml and messages.en.yaml
- [ ] Run tests, verify they PASS (GREEN)
- [ ] Commit: `feat: add GET /api/navigation with complete route inventory`

### Task 3: Frontend types + useNavigation hook

- [ ] Add NavItem, NavSection, NavigationResponse to `frontend/src/api/types.ts`
- [ ] Create `frontend/src/api/hooks/useNavigation.ts` (same pattern as useMe)
- [ ] Commit: `feat: add NavigationResponse types and useNavigation hook`

### Task 4: Refactor NavigationSidebar to consume API

- [ ] Remove hardcoded nav functions from NavigationSidebar.tsx
- [ ] Import and use useNavigation() hook
- [ ] Add resolveIcon() mapping string keys to SVG
- [ ] Add active state (location.pathname === item.href)
- [ ] Add 5 new icons: notifications, search, import, optimization, ai
- [ ] Verify: `npm run build` passes
- [ ] Commit: `refactor: NavigationSidebar consumes /api/navigation`

### Task 5: Cleanup

- [ ] Delete `backend/templates/_sidebar_content.html.twig`
- [ ] Verify no references remain in templates
- [ ] Commit: `chore: delete deprecated _sidebar_content.html.twig`

### Task 6: Verification

- [ ] PHP lint all modified files
- [ ] `npm run build` passes
- [ ] Backend tests pass
