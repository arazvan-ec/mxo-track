# Execution Log — 2026-03-24 — Fix fixtures load endpoint

**Type:** bugfix
**Branch:** `claude/fix-fixtures-load-endpoint-SImP1`

---

### Phase: Brainstorming
- **Alternatives evaluated:**
  1. Fix session_replication_role permissions on Railway — fragile, platform-dependent
  2. Replace with proper ordered DELETEs respecting FK dependencies — portable, reliable
  3. Use TRUNCATE CASCADE — too destructive, affects non-demo data
- **Chosen approach:** Ordered DELETEs — portable, no special privileges needed
- **Past decisions consulted:** docs/decisions/log.md — nothing relevant found
- **Complexity estimate:** S
- **Confidence:** high

### Phase: Planning
- **Task count:** 2 (fix controller + fix command)
- **Files affected:** 2 — DemoFixtureController.php, DemoSetupCommand.php
- **Time estimate:** 15 minutes
- **Risk assessment:** low — only affects demo data purge, no production data flows

### Phase: Implementation
- **Actual time:** ~20 minutes
- **Blockers hit:** none
- **Plan deviations:** none
- **Debugging episodes:** none

### Phase: Verification
- **Tests:** 600 run, 11 pre-existing failures (0 new), 0 new errors
- **Lint:** clean
- **Coverage delta:** not measured

### Phase: Retrospective
- **Estimate accuracy:** accurate
- **What worked:**
  1. Systematic root cause investigation identified 3 compounding issues (session_replication_role, wrong table name, missing child DELETEs)
  2. Pattern-wide search caught the same bug in DemoSetupCommand
- **What didn't:**
  1. Could not reproduce locally (no DB), but root cause analysis was thorough
- **Lessons for future:**
  1. Avoid session_replication_role for FK bypass — it requires superuser and is non-portable
  2. When using raw SQL table names, verify against Doctrine mappings (Route maps to route_plan, not route)
  3. When purging data, always consider the full FK dependency tree
- **Business context tags:** demo, fixtures, infrastructure
- **Decision log entry needed?** no — straightforward bug fix
