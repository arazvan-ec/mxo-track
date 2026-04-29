---
type: feature
tags: [harness, vocabulary, ubiquitous-language, ddd, spdd, registry]
files_touched:
  - docs/knowledge/_vocabulary.yaml
  - scripts/bootstrap-vocabulary.sh
  - scripts/render-vocabulary.sh
  - .claude/hooks/consult.sh
  - .claude/hooks/validators/sync-validator.sh
  - Makefile
  - CLAUDE.md
  - docs/superpowers/specs/2026-04-29-ubiquitous-language-system-phase-a-design.md
  - docs/superpowers/plans/2026-04-29-ubiquitous-language-system-phase-a.md
patterns: [registry-pattern, idempotent-bootstrap, source-of-truth-yaml-with-rendered-md]
outcome: success
outcome_verified_at: 2026-04-29
regressions_later: []
pr_number: null
estimated_lines: 840
actual_lines: 1100
duration_minutes: null
consulted_in_future: []
---

# Execution Log — 2026-04-29 — Hito 3 Phase A: Ubiquitous Language System Foundation

**Type:** feature
**Branch:** `claude/review-workflow-improvements-x78Zp`
**Spec:** `docs/superpowers/specs/2026-04-29-ubiquitous-language-system-phase-a-design.md`
**Plan:** `docs/superpowers/plans/2026-04-29-ubiquitous-language-system-phase-a.md`

## Summary

Foundation of the SPDD-derived Ubiquitous Language System (Phase A of 3):

1. **`docs/knowledge/_vocabulary.yaml`** — 84 entries (5 hand-curated
   anchors + 79 auto-bootstrapped from backend Domain/Entity/Enum,
   frontend types, and workflow validators; ~32 of those auto entries
   subsequently curated with aliases/definitions/bounded_context for
   high-authority concepts).
2. **`scripts/bootstrap-vocabulary.sh`** — idempotent extractor.
   Re-runs preserve curated fields (aliases, definition, related,
   cross_references); only canonical, authoritative_path, and layer
   are auto-managed.
3. **`scripts/render-vocabulary.sh`** — emits
   `docs/knowledge/ubiquitous-language.md` grouped by `bounded_context`.
   Read-only artifact; YAML is source of truth.
4. **`consult.sh vocab` subcommand** — case-insensitive lookup against
   canonical or alias.term; never falls back to substring match
   against definition prose. `--all` lists all canonicals; `--context
   <ctx>` filters by bounded_context.
5. **`Makefile`** — `make manifest` now invokes `render-vocabulary.sh`
   alongside the codebase manifest generator.
6. **`CLAUDE.md` Step 0** — instructs vocabulary consultation when the
   user mentions domain terms, before proposing names in the spec.
7. **`sync-validator.sh`** — added `ubiquitous-language.md` to
   `WORKFLOW_ARTIFACTS_PATHS` (auto-rendered, same scope as
   `codebase-manifest.md`).

Phase A delivers the foundation; Phases B and C add further
integrations (subagent-dispatch validation, Layer F enrichment,
pre-commit hooks, CI deprecation, drift maintenance) in later
interactions.

## Origin

Backlog hito 3 from the SPDD vs CLAUDE.md analysis (2026-04-28).
External proposal (Manus) was Doctrine-only and rejected — fails
4-test on multiple counts. Transversal version (this implementation)
covers backend domain + frontend types + workflow validators + cross-
references in a single canonical YAML registry.

## Implementation observations

### 1. Curation depth graceful-degraded as authorized by spec

The spec authorized partial curation if compaction approached:
"Curation depth is graceful-degrading. The schema, script, and
integrations are the load-bearing deliverables." Final curation:
~37 of 84 entries (5 anchors + 32 high-authority concepts via two
Python batch passes covering workflow validators, major domain
entities, key enums). Remaining 47 entries have auto-derived
canonical/path/layer and `TODO: curate definition` placeholders;
they grow organically via Phase C graduation pathway.

### 2. Sync gate forced scope adjustment mid-Wave-7

The smoke test (`make manifest`) regenerated
`docs/knowledge/ubiquitous-language.md`, which the plan didn't
declare. Discovered that the auto-rendered file must be in
`WORKFLOW_ARTIFACTS_PATHS` (same scope category as
`codebase-manifest.md`). Added inline; plan updated retroactively
to declare the touch. Pattern: when a smoke test reveals a
workflow-artifact path not in the scope list, add it inline rather
than declare in plan (plans don't declare artifacts they shouldn't
own).

### 3. Bootstrap script produced 79 new symbols on first run

Auto-extraction worked across 5 source patterns:
- `backend/src/Domain/*/Model/*.php` (PHP class declaration)
- `backend/src/Entity/*.php` (PHP class declaration)
- `backend/src/Enum/*.php` (PHP enum declaration)
- `frontend/src/types/*.ts` (TypeScript export type/interface/enum)
- `.claude/hooks/validators/*-validator.sh` (validator names)

Idempotency verified: second run reported "up to date — no new
symbols". Edge case: two duplicate canonicals appear (`PageKey`,
`WidgetType`) because they exist in both backend Enum and frontend
type. Acceptable for Phase A; dedup logic deferred to Phase B
(when the validator using vocabulary needs single-result guarantee).

## Changes

| File | Change |
|------|--------|
| `docs/knowledge/_vocabulary.yaml` | new (~830 lines): schema header + 84 entries |
| `scripts/bootstrap-vocabulary.sh` | new (~140 lines): idempotent extractor |
| `scripts/render-vocabulary.sh` | new (~95 lines): markdown renderer grouped by bounded_context |
| `.claude/hooks/consult.sh` | +85 lines: `vocab` subcommand block + dispatch |
| `.claude/hooks/validators/sync-validator.sh` | +1 path in `WORKFLOW_ARTIFACTS_PATHS` regex |
| `Makefile` | +1 line: `render-vocabulary.sh` invocation |
| `CLAUDE.md` | +5 lines: Step 0 vocabulary instruction |
| `docs/knowledge/ubiquitous-language.md` | auto-generated (~280 lines) |

Net: ~1100 lines added (estimate 840). Gap dominated by the YAML
file's auto-bootstrap output growing to 84 entries when ~50 were
estimated; rest within calibration.

## Verification

- `test-brainstorm-validator.sh` → **19/19 pass** (no regression)
- `test-sync-validator.sh` → **6/6 pass** (no regression)
- `test-pre-agent-check.sh` → **6/6 pass** (no regression)
- `bash -n` clean on bootstrap, render, and modified consult.sh
- Smoke `consult.sh vocab Route` → canonical entry printed (exit 0)
- Smoke `consult.sh vocab ruta` (alias) → same Route entry (exit 0)
- Smoke `consult.sh vocab nonexistent_xyz` → no output, exit 1
- Smoke `consult.sh vocab --all` → 79 unique canonicals (deduped)
- Smoke `consult.sh vocab --context route-planning` → 7 entries
- Smoke `make manifest` → regenerates rendered file successfully
- Sync gate at verification → capture: exit 0 after the
  WORKFLOW_ARTIFACTS_PATHS update + plan amendment

## Retrospective

### 1. Estimate accuracy

| Metric | Estimate | Actual | Delta |
|---|---|---|---|
| `_vocabulary.yaml` | +600 (50 entries × ~12 lines) | +830 (84 entries) | +38% |
| `bootstrap-vocabulary.sh` | +120 | +140 | +17% |
| `render-vocabulary.sh` | +60 | +95 | +58% |
| `consult.sh` vocab block | +50 | +85 | +70% |
| `Makefile` | +3 | +1 | OK |
| `CLAUDE.md` | +5 | +5 | OK |
| Total net | ~840 | ~1100 | +31% |

Causes:
- YAML grew because bootstrap found 79 symbols (estimated 45 from
  backend domain only; missed counting workflow validators and
  frontend types).
- Render script underestimated; awk-based per-context emission
  needed more state than expected.
- consult.sh vocab block grew because three sub-commands (--all,
  --context, term lookup) each have edge cases (empty result, exit
  codes, exit-from-awk pattern).

### 2. Process gaps

- **Plan didn't anticipate ubiquitous-language.md needing
  WORKFLOW_ARTIFACTS_PATHS entry.** The auto-rendered file is
  structurally identical to `codebase-manifest.md` (regenerated
  by `make manifest`, never hand-edited), but I forgot to add it
  to the scope regex when designing sync-validator originally
  (Hito 2). **Lesson:** when adding any auto-rendered docs file,
  audit `WORKFLOW_ARTIFACTS_PATHS` immediately. Pattern: if `make
  manifest` writes it, it's a workflow artifact.

- **Curation by Python batch (regex pattern replace) was
  efficient.** 18 + 13 entries curated in two passes, avoided
  individual Edit-tool round-trips. Pattern reusable for future
  vocabulary expansion or any "many similar small edits" task.

- **Spec's "graceful-degrading curation" Norm worked as
  designed.** Allowed me to ship Phase A with 37/84 curated rather
  than blocking on perfectionism. Schema + script + integrations
  are load-bearing; curation is data quality that improves over
  time. Pattern endorsed for similar registry interactions.

### 3. Emergent patterns

- **Auto-rendered artifact + WORKFLOW_ARTIFACTS_PATHS pairing.**
  Now at 2 occurrences (`codebase-manifest.md`,
  `ubiquitous-language.md`). If a third emerges, graduate the
  rule "any file regenerated by `make manifest` is a workflow
  artifact" to explicit documentation.

- **Idempotent bootstrap with curated-field preservation.** The
  pattern (read existing, merge new, never overwrite curated keys)
  is reusable for any registry where auto-extraction + human
  curation both contribute. First occurrence in this repo;
  precedent for future registries (e.g., a future
  `_endpoint-registry.yaml` for API surface auditing).

- **Phase splitting as a respect for session capacity.** This is
  the second instance of "split for operational reasons, each
  phase delivering its full scope" (first: Hito 3 itself proposed
  Phase A/B/C). When in pattern review, distinguish from "split
  to dilute scope" (which would be Layer K recoil).

## Follow-ups

1. **Phase B** — subagent dispatch validator using vocabulary +
   Layer F enrichment + pattern-audit integration.
2. **Phase C** — pre-commit hook + CI deprecation check +
   `vocab-drift.sh` + `vocab-rename.sh` + remaining curation.
3. **Bootstrap dedup** — `PageKey` and `WidgetType` appear twice
   (backend enum + frontend type). Decide canonical authoritative
   source per concept (likely backend, frontend cross-references
   it). Phase B work as part of validator integration.
4. **Curation backlog** — 47 entries with `TODO: curate definition`
   placeholders. Curate organically as drift is observed; do not
   block on completing all in one shot.
