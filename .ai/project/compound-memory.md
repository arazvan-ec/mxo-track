# Compound Memory: transporte-tracking

> Initialized by /workflows:discover --setup on 2026-02-19
> Updated by /workflows:compound after each feature

## Project Calibration

| Parameter | Value | Source |
|-----------|-------|--------|
| Avg feature complexity | Medium-High | Phase 4 data |
| Typical files per change | 8-12 | Phase 4 actual (36 files across 7 sub-phases) |
| Test strategy | TDD (planned) | Configuration |
| Review level | Strict | Setup choice |

## Agent Calibration

| Agent | Intensity | Reason |
|-------|-----------|--------|
| security-reviewer | HIGH | 8 critical IDOR/CSRF/XSS findings in Phase 4 review |
| architecture-reviewer | default | No architectural pain points |
| performance-reviewer | default + warning | N+1 query pattern found in FleetMapController |
| code-reviewer | default | Template duplication found but manageable |

## Learned Patterns

### P1: Class-Level IsGranted (1/1 features, confidence: high)
- **Where**: All admin/driver controllers
- **Why it works**: Prevents per-method omission; new methods automatically protected
- **Reference**: `backend/src/Controller/Admin/RouteAdminController.php:23`
- **Source features**: [phase-4-crud-dashboards]

### P2: IDOR Prevention via Ownership Chain Traversal (1/1 features, confidence: high)
- **Where**: `DriverApiController.php`, `DriverWebController.php`
- **Why it works**: Returns 404 for both "not found" and "not yours" (prevents enumeration); null-safe operator handles unassigned routes
- **Pattern**: `$stop->getRoute()->getDriver()?->getId() !== $driver->getId()`
- **Reference**: `backend/src/Controller/DriverApiController.php:148`
- **Source features**: [phase-4-crud-dashboards]

### P3: CSRF Token Names Bound to Entity Identity (1/1 features, confidence: high)
- **Where**: All admin delete actions
- **Why it works**: `'delete-route-' . $publicId` prevents cross-entity token reuse
- **Reference**: `backend/src/Controller/Admin/RouteAdminController.php:180`
- **Source features**: [phase-4-crud-dashboards]

### P4: Cookie-Based Mercure Auth with Scoped Topics (1/1 features, confidence: medium)
- **Where**: `MercureTokenController.php`, `TopicResolver.php`, `MercureJwtFactory.php`
- **Why it works**: Cookie with restricted path, per-role topic scoping, intersection filtering for defense-in-depth
- **Reference**: `backend/src/Controller/MercureTokenController.php`
- **Source features**: [phase-4-crud-dashboards]

### P5: findOneByPublicId Repository Gateway (1/1 features, confidence: high)
- **Where**: All repositories, all controllers
- **Why it works**: Single gateway between public ULID space and internal entities; consistent null-check pattern
- **Reference**: `backend/src/Repository/VehicleRepository.php`
- **Source features**: [phase-4-crud-dashboards]

### P6: DTO + Validator + ApiErrorResponder Pipeline (1/1 features, confidence: high)
- **Where**: `DriverApiController.php`, `Dto/Driver/*.php`, `ApiErrorResponder.php`
- **Why it works**: Consistent error envelope, field-level validation, snake_case/camelCase mapping in fromArray()
- **Reference**: `backend/src/Dto/Driver/DeliverStopInput.php`
- **Source features**: [phase-4-crud-dashboards]

### P7: Dual Isolation for Multi-Tenant (1/1 features, confidence: medium)
- **Where**: `CustomerTenantFilter.php` + manual scoping in `CustomerDashboardController.php`
- **Why it works**: SQL filter auto-scopes `CustomerScopedEntityInterface` entities; manual WHERE for non-scoped entities like Route
- **Reference**: `backend/src/Controller/Customer/CustomerDashboardController.php:51`
- **Source features**: [phase-4-crud-dashboards]

### P8: XSS Prevention in JSON-in-Template (1/1 features, confidence: high)
- **Where**: `FleetMapController.php`, all map templates
- **Why it works**: `JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP` prevents `</script>` injection; `esc()` JS function for Leaflet popups
- **Reference**: `backend/src/Controller/FleetMapController.php:113`
- **Source features**: [phase-4-crud-dashboards]

## Learned Anti-Patterns

### AP1: Missing IsGranted on Controller Class (frequency: 1, severity: critical)
- **What happened**: Method-level IsGranted omitted on some actions; new methods publicly accessible
- **Prevention**: Always use class-level `#[IsGranted]`, never method-level only
- **Cost**: 6 controllers needed fixing in security review

### AP2: IDOR in Nested Resource Endpoints (frequency: 1, severity: critical)
- **What happened**: DriverApiController had no ownership checks on any of 7 endpoints
- **Prevention**: Every driver/customer endpoint must verify entity ownership via chain traversal
- **Cost**: 7 ownership checks added; full review pass required

### AP3: Raw JSON in Templates Without Server-Side HEX Flags (frequency: 1, severity: high)
- **What happened**: `{{ data|raw }}` used to embed JSON in `<script>` tags; without `JSON_HEX_TAG` flags, `</script>` injection possible
- **Prevention**: Always pair `|raw` with `JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP` in json_encode()
- **Cost**: 3 controllers + 3 templates needed fixing

### AP4: CSRF Missing on File Upload Forms (frequency: 1, severity: high)
- **What happened**: CSV import form lacked CSRF token; POST without validation
- **Prevention**: All POST forms (including file uploads) must include `csrf_token()` and validate server-side
- **Cost**: 1 template + 1 controller fixed

### AP5: Internal IDs Exposed in Form Values (frequency: 1, severity: medium)
- **What happened**: Customer/Vehicle select options used `entity.id` instead of `entity.publicIdString`
- **Prevention**: Form values, URLs, and API payloads must only use publicId
- **Cost**: 2 templates + 1 controller fixed

### AP6: N+1 Queries in Vehicle Position Loading (frequency: 1, severity: medium)
- **What happened**: FleetMapController loads each vehicle's last position in a loop
- **Prevention**: Use `findBy(['vehicle' => $vehicles])` or JOIN in a single query
- **Where**: `backend/src/Controller/FleetMapController.php:47-59`

## Known Pain Points

| Pain Point | Frequency | Severity | Related Agent |
|------------|-----------|----------|---------------|
| Security gaps in scaffolded code | 1/1 features | Critical | security-reviewer |
| XSS in dynamic JS templates | 1/1 features | High | security-reviewer |
| N+1 queries in collection loading | 1/1 features | Medium | performance-reviewer |
| Template markup duplication | 1/1 features | Low | code-reviewer |

## Feature History

### [2026-02-20] phase-4-crud-dashboards
- **Scope**: Admin CRUDs (5), Customer Dashboard, Fleet Map (SSE), Driver Web Interface, CSV Import, Security Review
- **Commits**: 5 (bd16488, 8dfc736, cc54059, 5236795, e671b9a)
- **Files changed**: 36 (Phase 4 implementation + review fixes)
- **Patterns captured**: 8
- **Anti-patterns found**: 6
- **Security issues fixed**: 8 critical, 5 major
- **Estimated future savings**: 40-50% on similar CRUD+dashboard+realtime features
