---
type: bugfix
tags: [audit-fixes, ddd, repository-pattern, tz, validator, test-harness, parallel]
files_touched: [backend/src/Domain/Route/Repository/RouteStopRepositoryInterface.php, backend/src/Infrastructure/Route/Doctrine/DoctrineRouteStopRepository.php, backend/src/Controller/Api/Admin/RouteListApiController.php, backend/tests/Unit/Controller/Admin/RouteListApiControllerTest.php, frontend/src/widgets/DashboardKpisWidget.tsx, .claude/hooks/validators/brainstorm-validator.sh, .claude/hooks/test-brainstorm-validator.sh, .claude/hooks/lib/test-harness.sh]
patterns: [parallel-agent-dispatch, ddd-repository-extraction]
outcome: success
outcome_verified_at: 2026-04-24
regressions_later: []
pr_number: null
estimated_lines: 250
actual_lines: 302
duration_minutes: null
consulted_in_future: []
---

# Execution Log — 2026-04-24 — Routes Widget Audit Fixes (4 parallel problems)

**Type:** bundle of fixes for issues surfaced by socratic audit of prior work
**Branch:** `claude/enhance-routes-widget-8UzuC`
**Triggering analysis:** user requested "socratic analysis" of every commit on branch; audit surfaced 9 issues (1 critical, 3 medium, 5 low).

## Problems Addressed

### Problem A — Backend DDD + perf + TZ (issues 1, 2, 4)

**Issue 1 (critical): DDD boundary violation.**
`RouteListApiController` had three private methods using `EntityManagerInterface` + raw `QueryBuilder` against `Route`/`RouteStop` — Route being a critical DDD context per `backend/CLAUDE.md`, which forbids adding ORM coupling there.

**Fix:** Added three methods to `RouteStopRepositoryInterface`:
- `countsByRoutes(array $routes)` — extracts pre-existing `stopCounts` pattern
- `findNextPendingStopsByRoutes(array $routes)` — single-query fetch with OR-of-pairs WHERE
- `findDeliveryHistogramsByRoutes(array $routes, DateTimeZone $tz, DateTimeImmutable $day)` — TZ-explicit binning

Implemented in `DoctrineRouteStopRepository`. Controller now injects the interface; no direct `EntityManager` usage for stop aggregations.

**Issue 2 (medium): performance.**
Previous `fetchNextPendingStops` had a 2-step pattern: Step 1 fetched min-sequence per route (~5 rows), Step 2 hydrated ALL pending stops (potentially hundreds) and filtered in PHP. New implementation builds an `OR(route=:rN AND sequence=:sN)` composite WHERE with exactly the pairs from Step 1 — hydrates only the N needed rows.

**Issue 4 (medium): TZ boundary.**
Previous `fetchTodaysDeliveryHistograms` called `new DateTimeImmutable('today')` which implicitly used PHP's `date_default_timezone_get()`. Now the repository takes an explicit `DateTimeZone` parameter, and the controller passes `new DateTimeZone(date_default_timezone_get())` + `new DateTimeImmutable('now', $tz)` — making the TZ decision visible at the call site.

### Problem B — Frontend formatters (issues 3 partial, 7, 8)

**Issue 7 (low): `formatParcels(0)` returned "0 bultos".** One-line fix: treat `n === 0` as null.

**Issue 8 (low): apparent locale inconsistency.** On inspection, `formatKm` and `formatWeight` are actually consistent for their respective value ranges (km branches on 10 for decimals; weight branches on 1000 for tonnes, so kg never exceeds 3 digits). Documented as-is.

**Issue 3 (medium): TZ asymmetry** between backend histogram (server TZ) and frontend `formatTimeWindow` (client TZ). Full fix requires a product decision (ship raw timestamps vs add `histogramTimezone` field). Documented with a source comment pointing to this log.

### Problem C — `brainstorm-validator` edge cases (issues 5, 6)

**Issue 5: bare filenames filtered.** `grep -E '/|\.'` rejected `Makefile`, `Dockerfile`, etc. Added a sentinel OR branch: `^(Makefile|Dockerfile|Rakefile|Gemfile|Procfile|Caddyfile)$`.

**Issue 6: legitimate parenthesized lists skipped.** The 2026-04-22 fix unconditionally skipped any `^\s*\(...\)\s*$` payload. New approach: strip a single enclosing pair of parens, THEN run the path-like filter. `(no file writes)` → `"no file writes"` → three tokens all rejected by the filter → no false positive. `(a.ts, b.ts)` → `"a.ts, b.ts"` → two valid path tokens → conflict detection works.

Test suite extended from 4 to 6 cases.

### Problem D — `test-harness.sh` trap (issue 9)

`init_harness` installed its cleanup trap on EXIT unconditionally, wiping any pre-existing trap the caller had set. Now reads the existing trap via `trap -p EXIT`, extracts the command body with sed, and chains cleanup after it.

## Parallel Dispatch Strategy

- **Problem B (frontend):** dispatched as background agent. `frontend/src/**` is outside `.claude/**` so sandbox rules don't apply.
- **Problems A, C, D (backend + `.claude/**`):** done in foreground because `.claude/**` writes are blocked for background agents (per 2026-04-22 discovery documented in `AGENTS.md`).

Third deliberate application of the split-by-path pattern → **graduation threshold reached**. Should formalize in `AGENTS.md` dispatch-rules section and/or `docs/knowledge/workflow-engine.md`.

## Verification

| Check | Result |
|-------|--------|
| `phpunit tests/Unit/Controller/Admin/RouteListApiControllerTest.php` | 5/5 ✅ (refactored to mock repository instead of EM chain) |
| `phpunit` (full suite) | 677/677 ✅, no regressions (2 pre-existing warnings unchanged) |
| `make lint` | exit 0 ✅ |
| `npm run build` | ✅ `tsc -b && vite build`, 6.77s |
| `test-flow-phases.sh` | 15/15 ✅ |
| `test-brainstorm-validator.sh` | **6/6 ✅** (was 4/4) |
| `test-phase-advance-entry.sh` | 5/5 ✅ |
| `bash -n` syntax check on all modified `.sh` files | clean |

## Retrospective

### 1. Estimate accuracy

| Metric | Estimate | Actual | Delta |
|---|---|---|---|
| Files touched | 8 | 8 | ✅ |
| Net lines | ~250 | +302 | +20% |
| Parallel tasks | 4 | 4 | ✅ |
| Test regressions | 0 | 0 | ✅ |

Estimate was good — the +20% came almost entirely from docstrings on the
three new repository methods (each has `@param` + `@return` + explanation).
Essential for DDD interface clarity, not overhead.

### 2. Process gaps

- **The socratic audit caught issues that normal review did not.** 5 out of 9 issues were in code I wrote and had already approved: the DDD violation (critical), the perf issue, the TZ boundary, `formatParcels(0)`, and the validator false negatives. Lesson: **retrospectives are not socratic enough by default.** They focus on "did we ship?" not "did we ship right?". Consider making socratic review (an explicit re-read with a critical lens) part of the retrospective phase when touching critical contexts.
- **"Pragmatic" in spec doesn't excuse the rules.** The original spec justified the DDD violation as "pragmatic — mirrors existing stopCounts pattern." The backend/CLAUDE.md explicitly calls that pattern tech debt. **"Mirrors existing code" is not a valid justification when the code being mirrored is documented tech debt.** Future specs for critical-context work must check whether the pattern being mirrored is endorsed or tech-debted.
- **Test mocks masked the perf issue.** The 5 controller tests scripted the 2-step fetchNextPendingStops DQL but didn't notice it was doing work proportional to total pending stops instead of to route count. Unit tests with mocks validate shape, not algorithm efficiency. A functional test over a seeded database would have caught it — but we don't have one for this controller.

### 3. Emergent patterns

- **DDD-repository-extraction (1st occurrence documented).** Pattern: when a controller accumulates raw EM queries against a critical-context model, extract to a Repository method named by intent (not by query shape). This is not new to the codebase (RoutePlanningService, DeliveryService already follow it) but was newly applied HERE.
- **Split-by-path dispatch (3rd occurrence — graduates).** Dispatch docs/source work to background agents; keep `.claude/**` in foreground. Should be formalized in AGENTS.md next session.
- **Socratic audit as a retrospective lens.** Re-reading shipped code with adversarial questions ("Is this approach endorsed? What does the doc say? What edge case does this miss?") surfaced issues that normal review did not. Worth trying again on future complex features.

## Follow-ups

1. **Issue 3 full fix** — TZ asymmetry between histogram bucketing (server) and window rendering (client). Decision: product-level. Options:
   (a) add `histogramTimezone` field to DTO + frontend renders windows in that TZ via `Intl.DateTimeFormat`
   (b) backend ships raw ISO timestamps; frontend bins locally
   (c) accept single-TZ assumption and document
2. **Functional test for `RouteListApiController`.** Unit tests with mocks missed the perf issue. A WebTestCase hitting the endpoint against a seeded DB would catch similar issues. Worth adding when time permits.
3. **Graduation: split-by-path dispatch.** Move from "discovered pattern" to "documented convention" in `AGENTS.md` (3rd occurrence reached).
4. **Graduation candidate: `list-endpoint-subaggregation`** was at 3 occurrences before; now the extraction to a repository makes it a **different** pattern ("repository extraction for list-endpoint aggregations"). Reset the counter or split the graduation candidate into two.
