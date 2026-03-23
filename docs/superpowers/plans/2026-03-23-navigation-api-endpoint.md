# Plan: Navigation API Endpoint

**Spec:** `docs/superpowers/specs/2026-03-23-navigation-api-endpoint-design.md`
**Goal:** Single Source of Truth for navigation menu via `GET /api/navigation`
**Complexity:** S (4 tasks)

---

## File Structure

| File | Action | Purpose |
|------|--------|---------|
| `backend/src/Controller/Api/NavigationController.php` | Create | API endpoint |
| `frontend/src/api/types.ts` | Edit | Add `NavigationResponse` types |
| `frontend/src/api/hooks/useNavigation.ts` | Create | React hook |
| `frontend/src/components/layout/NavigationSidebar.tsx` | Edit | Consume hook, remove hardcoded items, add active state |
| `backend/templates/_sidebar_content.html.twig` | Delete | Deprecated template |
| `backend/translations/messages.es.yaml` | Edit | Add missing translation keys |
| `backend/translations/messages.en.yaml` | Edit | Add missing translation keys |

---

## Tasks

### Task 1: Backend — NavigationController

- [ ] Create `backend/src/Controller/Api/NavigationController.php`
  - `#[Route('/api/navigation', name: 'api_navigation', methods: ['GET'])]`
  - `#[IsGranted('ROLE_USER')]`
  - Inject `TranslatorInterface`
  - Method `__invoke(Request $request): JsonResponse`
  - Build sections array based on user's primary role
  - Use existing translation keys (`nav.*`, `sidebar.*`) for labels
  - Add missing translation keys for items not yet in messages files
  - Set `Cache-Control: private, max-age=3600`
  - Return `{ sections: [{ title, items: [{ label, href, icon }] }] }`

- [ ] Add missing translation keys to `messages.es.yaml` and `messages.en.yaml`:
  - `nav.dashboard_live`: Dashboard Live / Dashboard Live
  - `nav.shipments`: Envios / Shipments
  - `nav.planner`: Planificador / Planner
  - `nav.route_templates`: Plantillas de Ruta / Route Templates
  - `nav.integrations`: Integraciones / Integrations
  - `nav.sla`: SLA & Cumplimiento / SLA & Compliance
  - `nav.exception_map`: Mapa Excepciones / Exception Map
  - `sidebar.my_reports`: Mis Reportes / My Reports

- [ ] Verify: `php bin/console router:match /api/navigation` shows the route

### Task 2: Frontend — Types + Hook

- [ ] Add types to `frontend/src/api/types.ts`:
  ```typescript
  export interface NavItem {
    label: string;
    href: string;
    icon: string;
  }

  export interface NavSection {
    title: string;
    items: NavItem[];
  }

  export interface NavigationResponse {
    sections: NavSection[];
  }
  ```

- [ ] Create `frontend/src/api/hooks/useNavigation.ts`:
  ```typescript
  import { useQuery } from '@tanstack/react-query';
  import { api } from '../client';
  import type { NavigationResponse } from '../types';

  export function useNavigation() {
    return useQuery({
      queryKey: ['navigation'],
      queryFn: () => api.get<NavigationResponse>('/api/navigation'),
      staleTime: 60 * 60 * 1000, // 1 hour (matches server cache)
    });
  }
  ```

### Task 3: Frontend — NavigationSidebar consumes API

- [ ] Edit `NavigationSidebar.tsx`:
  - Remove `getAdminNav()`, `getCustomerNav()`, `getDriverNav()`, `getNavSections()`
  - Remove local `NavItem` and `NavSection` interfaces (use from types.ts)
  - Import `useNavigation` hook
  - Replace `const sections = getNavSections(me?.role)` with `const { data } = useNavigation()`; `const sections = data?.sections ?? []`
  - Keep `icons` dict; add icon lookup: `icons[item.icon as keyof typeof icons]` with fallback
  - Add active state: compare `window.location.pathname` with `item.href`
  - Keep `useMe()` for user footer

- [ ] Verify: `npm run build` succeeds with 0 errors

### Task 4: Cleanup

- [ ] Delete `backend/templates/_sidebar_content.html.twig`
- [ ] Verify: `grep -r "_sidebar_content" backend/templates/` returns nothing (not included anywhere)

---

## Verification

- [ ] `make lint` passes
- [ ] `npm run build` passes
- [ ] No TypeScript errors
