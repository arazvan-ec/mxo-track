# Plan — Full Dashboard Polish (Specs A + B + C)

**Specs:** A (hardcoded colors), B (customer dashboard), C (visual guide)
**Branch:** `claude/innovative-dashboard-design-ddekk`
**Strategy:** Sequential specs with checkpoint push after each

## Spec C: Visual Testing Guide

### Wave C1: Write guide
- **Task C1:** Create visual testing guide — File: `docs/superpowers/visual-testing-guide.md`
  Checklist for 10 combinations (5 presets x 2 modes), per-page verification steps
  → produces: testing documentation
  → CHECKPOINT: commit + push

## Spec A: Hardcoded Colors Migration

### [parallel] Wave A1: Fleet components (7 files)
- **Task A1a:** FleetSidebar.tsx — Replace ~10 slate classes with CSS vars
- **Task A1b:** RouteList.tsx — Replace ~8 slate text colors
- **Task A1c:** VehicleList.tsx — Replace ~8 slate text colors + offline indicator
- **Task A1d:** RouteProgressBar.tsx — Replace ~4 slate classes
- **Task A1e:** VehiclePopup.tsx — Replace ~5 slate classes
- **Task A1f:** StopPopup.tsx — Replace ~3 slate classes
- **Task A1g:** HeaderBar.tsx — Replace ~1 slate class

### [parallel] Wave A2: Admin pages + map layers (6 files)
- **Task A2a:** ExceptionMapPage.tsx — Replace ~15 slate classes (forms, cards, toggles)
- **Task A2b:** TestRoutingPage.tsx — Replace ~5 slate classes (map overlay buttons)
- **Task A2c:** RoutePlannerPage.tsx — Replace ~20 slate classes (forms, lists)
- **Task A2d:** PageLayoutEditorPage.tsx — Replace ~20 slate classes (entire editor)
- **Task A2e:** ExceptionLayer.tsx — Replace ~5 slate classes (popup text)
- **Task A2f:** RouteMapLayers.tsx — Replace ~4 slate classes (toggle buttons)

### [parallel] Wave A3: Panels + layout (6 files)
- **Task A3a:** StopActionPanel.tsx — Replace ~3 slate classes
- **Task A3b:** VehicleActionPanel.tsx — Replace ~2 slate classes
- **Task A3c:** UserDropdown.tsx — Replace ~6 gray classes
- **Task A3d:** SearchBar.tsx — Replace ~8 gray classes
- **Task A3e:** LanguageSwitcher.tsx — Replace ~3 gray classes
- **Task A3f:** NotificationBell.tsx — Replace ~2 gray classes

### Wave A4: Verify Spec A
- **Task A4:** TypeScript + lint — verify zero new errors
  → CHECKPOINT: commit + push

## Spec B: Customer Dashboard Migration

### [parallel] Wave B1: Backend + types
- **Task B1a:** Add `CUSTOMER_DASHBOARD` to PageKey enum — File: `backend/src/Enum/PageKey.php`
- **Task B1b:** Add `'customer_dashboard'` to frontend PageKey type — File: `frontend/src/types/layout.ts`
- **Task B1c:** Create useCustomerDashboard hook — File: `frontend/src/api/hooks/useCustomerDashboard.ts`

### [parallel] Wave B2: New widgets
- **Task B2a:** Create CustomerKpisWidget — File: `frontend/src/widgets/CustomerKpisWidget.tsx`
- **Task B2b:** Create CustomerOptimizationWidget — File: `frontend/src/widgets/CustomerOptimizationWidget.tsx`
- **Task B2c:** Register widgets in registry — File: `frontend/src/widgets/registry.ts`

### Wave B3: Page + router (needs B1 + B2)
- **Task B3a:** Create CustomerDashboardPage — File: `frontend/src/pages/customer/CustomerDashboardPage.tsx`
- **Task B3b:** Add route to router — File: `frontend/src/router.tsx`

### Wave B4: Verify Spec B
- **Task B4:** TypeScript + lint + tests — verify all passing
  → CHECKPOINT: commit + push
