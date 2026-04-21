# Plan — 2026-04-21 — Memory/Harness PR4 (Strict Graduation Registry + Curation)

**Spec:** `docs/superpowers/specs/2026-04-21-memory-harness-pr4.md`
**Branch:** `claude/view-plan-progress-ddWZc`
**Phase:** v0 directo

---

## Parallelism analysis

File conflict matrix:

| Task | Files touched |
|------|---------------|
| 1a _graduations.yaml | `docs/knowledge/_graduations.yaml` (new) |
| 1b graduate.sh + test | `scripts/graduate.sh` (new), `scripts/test-graduate.sh` (new) |
| 1c validator test | `scripts/test-graduations-validator.sh` (new) |
| 2a pattern-audit refactor | `.claude/hooks/pattern-audit.sh` (edit), `.claude/hooks/test-pattern-audit.sh` (edit) |
| 2b suggest-tags refactor | `scripts/suggest-tags.sh` (edit), `scripts/test-suggest-tags.sh` (edit) |
| 3a ui-frontend.md curation | `docs/knowledge/ui-frontend.md` |
| 3b superpowers-skills.md curation | `docs/knowledge/superpowers-skills.md` |
| 3c domain-model.md curation | `docs/knowledge/domain-model.md` |
| 3d api-surface.md curation | `docs/knowledge/api-surface.md` |

Wave 1 (1a, 1b, 1c): all disjoint files → **parallel**.
Wave 2 (2a, 2b): disjoint files; needs Wave 1 for YAML schema → **parallel** after Wave 1.
Wave 3 (3a-d): all disjoint knowledge files → **parallel** after Wave 2 (curation consumes the new tooling).

---

## Phase 1: v0

### [parallel] Wave 1: Registry foundation

- **1a: `docs/knowledge/_graduations.yaml`**
  - Create file with 3 sections: `tags:` (13 entries), `patterns:` (2 entries),
    `keyword_mappings:` (40 entries, migrated from `suggest-tags.sh`)
  - Entries for Grupo 1 (5 pointer-only) use existing section names
  - Entries for Grupo 2 (8 new sections) use the section names that Wave 3 will create
    — initially these references are "pending"; validator runs in Wave 4
  - → produces: registry file consumable by subsequent tasks

- **1b: `scripts/graduate.sh` + `scripts/test-graduate.sh`**
  - TDD: write test first
  - Script accepts: `<tag> --module=<file> --section=<heading> [--force] [--pattern]`
  - Validates: module exists, section is heading or `*`, tag has ≥3 occurrences
  - Writes entry to YAML (idempotent, under `tags:` or `patterns:`)
  - Exit codes: 0 (added), 1 (already present), 2 (error)
  - Env override: `GRADUATE_REGISTRY` for test isolation
  - → produces: atomic graduation helper

- **1c: `scripts/test-graduations-validator.sh`**
  - Fixture: YAML with valid entries, YAML with broken references
  - Assert: valid passes (exit 0), broken fails (exit 1)
  - Validates: each `tags.*.module` exists, each `section` is a heading (or `*`),
    each `keyword_mappings[kw]` value exists as key in `tags:`
  - Runs also in Wave 4 as part of regression suite
  - → produces: drift-detection test

**Commit 1:** `feat: add _graduations.yaml registry + graduate.sh helper`
- Files: 4 new (~300 lines)

### [parallel] Wave 2: Script refactors (needs Wave 1)

- **2a: `pattern-audit.sh` + `test-pattern-audit.sh`**
  - Replace substring grep with YAML registry lookup
  - Registry parsing: awk pattern to extract `tags:` and `patterns:` keys
  - Output enhancement: append `→ graduate.sh ...` command per candidate
    with heuristic default (substring match in knowledge docs)
  - Env override: `PATTERN_AUDIT_REGISTRY` (kept `PATTERN_AUDIT_KNOWLEDGE_DIR`
    for fallback lookup of suggestions)
  - Update test fixtures: registry-based instead of knowledge-doc-based
  - → produces: accurate audit with actionable output

- **2b: `suggest-tags.sh` + `test-suggest-tags.sh`**
  - Remove `declare -A KEYWORD_TAGS=(...)` inline
  - Load from YAML registry (`keyword_mappings:` section)
  - Env override: `SUGGEST_TAGS_REGISTRY`
  - Update test fixtures: point to test YAML instead of expecting inline table
  - → produces: single source of truth for keyword→tag mappings

**Commit 2:** `refactor: pattern-audit + suggest-tags read from YAML registry`
- Files: 4 edit (~150 lines changed)

### [parallel] Wave 3: Curation (needs Wave 2)

Each task writes new section(s) and validates against the YAML entries added in Wave 1.

- **3a: `docs/knowledge/ui-frontend.md`**
  - Add section `Navigation Menu` (~8 lines: what it is, file pointers to
    NavigationSidebar components, log pointers)
  - Add section `Sidebar System` (~8 lines: similar format)
  - → produces: 2 tags graduated (menu, sidebar)

- **3b: `docs/knowledge/superpowers-skills.md`**
  - Add section `Workflow Phases Overview` (~10 lines: the 8-phase flow,
    pointers to `.claude/hooks/phase-advance.sh` and CLAUDE.md section)
  - Add section `Workflow Hooks` (~10 lines: SessionStart, UserPromptSubmit, etc.)
  - Add section `Harness as Memory` (~12 lines: the concept, Compaction Contract
    pointer, PR1-PR4 series reference)
  - → produces: 3 tags + 1 pattern graduated (workflow, hook, memory, harness,
    harness-memory-separation)

- **3c: `docs/knowledge/domain-model.md`**
  - Add section `Stops and Delivery Points` (~8 lines: Stop entity, RouteStep
    pointer)
  - → produces: 1 tag graduated (stop)

- **3d: `docs/knowledge/api-surface.md`**
  - Add section `List Filters` (~8 lines: ListFilterApplier, advanced filters
    pattern)
  - → produces: 1 tag graduated (filter)

**Commit 3:** `docs: graduate 13 tags + 1 pattern to knowledge modules`
- Files: 4 edit (~70 lines added total)

### Wave 4: Verification

- **4a: Run `test-graduations-validator.sh`** — must pass (YAML entries reference real sections)
- **4b: Run all existing tests** — regression check:
  - test-consult.sh (39)
  - test-pattern-audit.sh (updated, 4+)
  - test-suggest-tags.sh (updated, 14+)
  - test-mark-verified.sh (9)
  - test-link-regression.sh (10)
  - test-phase-advance.sh (14)
  - test-graduate.sh (new, ~10)
  - test-graduations-validator.sh (new, ~5)
  - test-workflow-engine.sh (regression check, 6 pre-existing failures tolerated)
- **4c: Run `pattern-audit.sh`** manually — should report 0 candidates or only
  genuinely un-graduated ones (sanity check)
- **4d: Run `suggest-tags.sh --dry-run`** over corpus — should produce same output
  as PR3 (backwards compat: reading from YAML shouldn't change behavior)

**No commit here** — verification only.

### Wave 5: Capture + finalize

- **5a: make manifest** (captures new files + updated modules)
- **5b: Write execution log** `2026-04-21-memory-harness-pr4.md` with frontmatter
  (tags: workflow, memory, harness, graduation, curation, registry)
- **5c: Advance phases: verification → capture → retrospective → finalize**
- **5d: Push**

**Commit 4:** `docs: add execution log for memory-harness PR4` + `chore: update codebase manifest`

---

## Task count: 13 tareas, 5 waves, 4 commits

## Files affected

- **New:** `docs/knowledge/_graduations.yaml`, `scripts/graduate.sh`,
  `scripts/test-graduate.sh`, `scripts/test-graduations-validator.sh`
- **Modified:** `.claude/hooks/pattern-audit.sh`, `.claude/hooks/test-pattern-audit.sh`,
  `scripts/suggest-tags.sh`, `scripts/test-suggest-tags.sh`,
  `docs/knowledge/ui-frontend.md`, `docs/knowledge/superpowers-skills.md`,
  `docs/knowledge/domain-model.md`, `docs/knowledge/api-surface.md`

## Time estimate: 60-90 min

## Risk: Low-Medium

- **Medium:** YAML parsing en bash awk/grep tiene edge cases (quotes, spaces en
  section names con `"..."`). Mitigación: el YAML tiene shape predecible (sin
  nested arrays complejos), tests cubren paths principales.
- **Low:** Curación es doc writing trivial; rollback = `git revert` del commit 3.
- **Low:** Refactors de pattern-audit y suggest-tags son contained con tests.
