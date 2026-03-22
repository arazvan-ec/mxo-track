# Map Stop Selection Highlight — Implementation Plan

**Spec:** `docs/superpowers/specs/2026-03-22-map-stop-selection-highlight-design.md`
**Complexity:** S (prop threading + CSS styling)

## Tasks

- [ ] 1. Add `isSelected` prop to StopMarker with visual highlight
- [ ] 2. Add `selectedSequence` prop to StopMarkersLayer, forward to StopMarker
- [ ] 3. Wire `selectedSequence` in RouteDetailPage (admin)
- [ ] 4. Wire `selectedSequence` in CustomerRouteDetailPage
- [ ] 5. Wire `selectedSequence` in DriverRoutePage
- [ ] 6. Add stop selection to FleetMap (props + click handlers)
- [ ] 7. Add stop selection state to FleetMapPage
- [ ] 8. Build verification (TypeScript compile)
