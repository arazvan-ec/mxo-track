# Compound Log: transporte-tracking

> Initialized by /workflows:discover --setup on 2026-02-19
> Updated by /workflows:compound after each feature

## Log Entries

### [2026-02-20] Feature: phase-4-crud-dashboards

#### Summary
Implemented the full admin/customer/driver web interface for the logistics tracking portal:
- 5 admin CRUDs (Vehicle, Driver, Route, Customer, User) with pagination, forms, soft-delete
- CSV shipment import with CSRF, run tracking, error categorization
- Customer dashboard with KPI metrics, route progress, real-time vehicle positions
- Customer shipment detail view with event timeline
- Enhanced fleet map with Mercure SSE real-time vehicle tracking (Leaflet + Alpine.js)
- Driver web interface with route execution, delivery POD, exception reporting, offline queue
- Comprehensive security review fixing 8 critical + 5 major issues

#### Time Investment
- Planning: 15% (Phase 4 plan + architecture)
- Implementation: 55% (4B through 4G sub-phases)
- Review: 25% (multi-agent security + template review + fix cycle)
- Compound: 5% (this capture)

#### The 70% Boundary

| Sub-phase | Easy 70% | Hard 30% |
|-----------|----------|----------|
| Admin CRUDs | Form types, index/new/edit/delete | Stop management, aggregate queries, pagination |
| CSV Import | File upload, row parsing | CSRF on uploads, run tracking, error categories |
| Customer Dashboard | Layout + KPI cards | Dual isolation (SQL filter + manual), route progress |
| Fleet Map | Leaflet + markers | Mercure cookie auth, SSE reconnection, JSON XSS |
| Driver Interface | Route/stop lists | Idempotent delivery, offline queue, IDOR prevention |
| Security Review | Adding IsGranted | IDOR chains, CSRF scoping, XSS in dynamic JS |

**Where progress slowed**: Security hardening. The initial scaffolding was fast (~70% in 3 commits), but the security review revealed 8 critical issues that required touching 28 files. The gap was authorization boundaries: the scaffolding assumed firewall rules were sufficient, but defense-in-depth required per-controller and per-endpoint ownership checks.

**What would have prevented the slowdown**:
- Security checklist in spec templates (IDOR, CSRF, XSS for every endpoint)
- IsGranted enforcement rule in project rules
- JSON-in-template XSS prevention documented as a pattern upfront

#### Patterns to Reuse
1. **Class-Level IsGranted** - Apply to all new controllers
   - File: `backend/src/Controller/Admin/RouteAdminController.php`
2. **IDOR Ownership Chain** - Use for all user-scoped endpoints
   - File: `backend/src/Controller/DriverApiController.php`
3. **CSRF Token + PublicId** - Use for all destructive POST actions
   - File: `backend/src/Controller/Admin/RouteAdminController.php`
4. **Cookie-Based Mercure Auth** - Reuse for any SSE feature
   - File: `backend/src/Controller/MercureTokenController.php`
5. **JSON_HEX Flags** - Use everywhere JSON is embedded in templates
   - File: `backend/src/Controller/FleetMapController.php`
6. **DTO Pipeline** - Use for all new JSON API endpoints
   - File: `backend/src/Dto/Driver/DeliverStopInput.php`
7. **Dual Multi-Tenant Isolation** - SQL filter + manual scoping
   - File: `backend/src/Controller/Customer/CustomerDashboardController.php`
8. **Alpine.js + Leaflet State Machine** - Map with SSE updates
   - File: `backend/templates/tracking/map.html.twig`

#### Anti-Patterns Documented
1. **Method-Level Only IsGranted** -> Added to security review checklist
2. **IDOR Without Ownership Checks** -> Added to security review checklist
3. **Raw JSON Without HEX Flags** -> Added to architecture profile
4. **CSRF Missing on Uploads** -> Added to security review checklist
5. **Internal IDs in Form Values** -> Added to architecture profile
6. **N+1 in Position Loading** -> Noted for future optimization

#### Rules Updated
- `openspec/specs/architecture-profile.yaml`: Added 8 learned_patterns, 4 learned_antipatterns
- `.ai/project/compound-memory.md`: Added agent calibration, pain points, full pattern catalog

#### Specs Updated

##### Entities
| Entity | Action | File |
|--------|--------|------|
| All 12 entities | UNCHANGED | openspec/specs/entities/*.yaml |

No new entities were created in Phase 4 — this phase implemented the web interface on top of existing Phase 3 entities.

##### API Contracts
| Contract | Action | File |
|----------|--------|------|
| admin-web | UPDATED | openspec/specs/api-contracts/admin-web.yaml |
| driver-api | UNCHANGED | openspec/specs/api-contracts/driver-api.yaml |

Admin web contract needs updating to reflect the 5 new CRUDs + CSV import routes.

##### Business Rules
| Rule | Action | File |
|------|--------|------|
| security | UPDATED | openspec/specs/business-rules/security.yaml |

Security rules need updating to reflect IsGranted, IDOR, CSRF, XSS patterns as enforced rules.

#### Impact on Future Work
- Next feature can reuse: all 8 patterns above
- Security review will be faster with known checklist
- Admin CRUD scaffold is now a proven template
- Mercure SSE integration is reusable for any real-time feature
- **Estimated time savings**: 40-50% on similar features

#### Dimensional Learnings
- **Dimensional accuracy**: Discovery diagnostic correctly classified MVC, repository pattern, manual DTO serialization. Accurate.
- **Dimensional drift**: No new dimensions. Async pattern still "none" (SSE is server-push, not async processing).
- **Constraint effectiveness**: Layer dependency constraints were useful — prevented controllers from directly accessing other controllers. Multi-tenant isolation constraint caught the Route scoping gap.

---

## Format

Each entry follows:
```
### [YYYY-MM-DD] Feature: [slug]
- **Patterns Learned**: [list]
- **Anti-Patterns Found**: [list]
- **Rules Updated**: [list]
- **Architecture Impact**: [none|minor|major]
```
