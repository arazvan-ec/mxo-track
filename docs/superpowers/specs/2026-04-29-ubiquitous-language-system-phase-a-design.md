# Spec — Hito 3 Phase A: Ubiquitous Language System Foundation

**Date:** 2026-04-29
**Branch:** `claude/review-workflow-improvements-x78Zp`
**Type:** code change (full flow) — new infrastructure
**Backlog ref:** Hito 3 (transversal version), Phase A of 3.

## Problem

The repository has no canonical registry of vocabulary used across
backend, frontend, API, and workflow. The same business concept
appears under multiple names depending on where it is referenced
(documented examples from past logs and live code: `Route` vs `tour`
vs `RoutePlan`; `stop` vs `Waypoint` vs `RouteStop` vs `Delivery`;
`snapshot` vs `Position` vs `TraccarEvent`; `POD` vs
`ProofOfDelivery` vs `DeliveryEvidence`). Each divergence has cost:
double implementations, subagents diverging in parallel waves,
knowledge-module entries going stale, `consult.sh` searches missing
the canonical name.

Without a registry, the model invents names or picks the first match
from grep. The brainstorming Step 0 (consult past decisions) cannot
ground term usage in established vocabulary because no such corpus
exists.

This is Phase A of three. It establishes the foundation: a YAML
registry, a bootstrap script, an initial curated population, and
three integrations that make the registry queryable end-to-end.
Phases B and C add further integrations atop this foundation in
later interactions.

## Approach Chosen

**Phase A — Foundation + 3 core integrations.** Concrete deliverables:

1. **Canonical schema** at `docs/knowledge/_vocabulary.yaml`. Each
   entry has:
   - `canonical` (required, string)
   - `aliases` (list of objects: `{term, lang, surface}` where lang ∈
     `es|en` and surface ∈ `user|internal|deprecated`)
   - `definition` (required, one-line string)
   - `bounded_context` (string, references `_ddd-boundaries.yaml` keys)
   - `layer` (`domain|application|infrastructure|ui|workflow`)
   - `authoritative_path` (file path where the canonical lives)
   - `related` (list of `{concept, relation}`)
   - `lifecycle` (`active|deprecated`)
   - `cross_references` (object with `frontend`, `api`,
     `knowledge_module` keys, all optional)

2. **Bootstrap script** at `scripts/bootstrap-vocabulary.sh`:
   - Scans `backend/src/Domain/*/Model/`, `backend/src/Entity/`,
     `backend/src/Enum/`, `frontend/src/types/`, page registry,
     widget registry, route names, workflow phases, validator
     names, knowledge module headings.
   - Emits a YAML draft with auto-extracted canonical names + path,
     leaving aliases and definitions empty for human curation.
   - Idempotent: re-running merges new symbols without overwriting
     curated fields.

3. **Initial population:** the spec author (this interaction) runs
   the bootstrap script, then curates aliases and definitions for
   the highest-authority concepts identified from execution logs:
   `Route`, `RouteStop`, `Driver`, `Vehicle`, `RouteSnapshot`,
   `Position`, `Customer`, `User`, `Delivery`, plus the workflow
   layers (Layer H/K/N/S/Sync/Agent), plus key enums (RouteEventType,
   WidgetType, PageKey). Estimated ~50 high-authority entries
   curated; remaining auto-extracted entries kept with empty aliases
   for organic growth.

4. **`consult.sh vocab` subcommand:**
   - `consult.sh vocab <term>` — lookup; prints canonical entry if
     `<term>` matches a `canonical` or any `alias.term`.
   - `consult.sh vocab --all` — list all canonical names (one per
     line).
   - `consult.sh vocab --context <ctx>` — entries in a bounded context.
   - Exit codes: 0=found, 1=not found, 2=error.

5. **Brainstorming Step 0 update in CLAUDE.md:** add explicit
   instruction to consult `_vocabulary.yaml` (via `consult.sh vocab`)
   when the user mentions domain terms, before proposing names in
   the spec.

6. **Auto-rendered human view** at `docs/knowledge/ubiquitous-language.md`:
   regenerated from `_vocabulary.yaml` by `make manifest` (extending
   the existing manifest pipeline). Read-only artifact for humans;
   YAML is the source of truth.

Phases B and C (separate interactions later) extend integrations:
Phase B adds subagent-dispatch validation + Layer F enrichment +
pattern-audit; Phase C adds pre-commit hook + CI deprecation check
+ vocab-drift maintenance script.

## Alternatives Rejected

**Doctrine-only registry (Manus's literal proposal):** rejected in
prior interactions — fails 4-test (Test 1: model already sees entity
names in grep; Test 3: noise > signal; misses cross-stack drift
which is the actual problem).

**Single-interaction "do everything":** rejected operationally —
~12 files, ~400 entries curated, ~1000 lines of tooling exceeds the
practical capacity of a single session-state coherence cycle. Per
CLAUDE.md guidance ("split tasks > 8 steps"), this is split for
operational reasons, not scope reasons. Each phase delivers the full
version of its bounded scope.

**Auto-extracted only (no curation):** rejected on quality. Auto-
extraction produces canonical names accurately but cannot generate
aliases (the actual value of the registry). A registry without
curated aliases is just a duplicate of the codebase manifest; the
canonical→aliases map is the load-bearing structure.

**Inline-in-codebase-manifest (no separate YAML):** rejected on
separation of concerns. The manifest is a derived artifact
regenerated frequently; vocabulary is curated state with its own
lifecycle. Mixing them couples regeneration cadence and breaks
human curation across regenerations.

## 4-Test Application (honest, on the maximal version of Phase A)

| Test | Verdict | Evidence |
|---|---|---|
| 1. LLM no aplica espontáneamente | ✓ | Without a registry, the model picks first-grep match for any term. Documented drift across 4+ execution logs. The model has not built a registry spontaneously despite the cost being visible. |
| 2. Fase correcta | ✓ | Foundation must precede the integrations in B/C. Building the integrations first leaves them pointing at an empty/missing registry. Phase A is the prerequisite layer. |
| 3. Coste proporcional al valor | ✓ | ~300 lines of tooling + ~50 curated entries (~10 lines each = 500 lines YAML) + integration touches. Value: brainstorming Step 0 gains a real grounding for term usage; future Phases B/C compose on this foundation; cross-stack drift detection becomes possible. Comparable to other foundational additions like `_ddd-boundaries.yaml` + `lib/ddd-boundaries.sh` (commit `ad11cc4`). |
| 4. Backed by source | ✓ | DDD Ubiquitous Language (Evans); SPDD REASONS Canvas (Entities dimension); `_ddd-boundaries.yaml` precedent (canonical YAML registry consumed by validators); `_graduations.yaml` precedent (curated vocabulary with graduation pathway). |

Pass on all four. No reduction.

## Existing Functionality Inventory

| Element | Decision | Justification |
|---|---|---|
| `docs/knowledge/_ddd-boundaries.yaml` | Omit | Different concern (boundary registry, not vocabulary). May be cross-referenced from vocabulary entries via `bounded_context` field but not modified. |
| `docs/knowledge/_graduations.yaml` | Omit | Different concern (pattern graduation registry). Same precedent shape (curated YAML + lib + validator). |
| `docs/codebase-manifest.md` and `make manifest` | Transform | Extend the manifest regeneration pipeline to also render `docs/knowledge/ubiquitous-language.md` from `_vocabulary.yaml`. |
| `.claude/hooks/consult.sh` (311 lines) | Transform | Add `vocab` subcommand alongside existing `tag`, `file`, `pattern`, etc. |
| `CLAUDE.md` Step 0 of brainstorming checklist | Transform | Add instruction to consult vocabulary when user mentions domain terms. |
| `Makefile` manifest target | Transform | Add ubiquitous-language render step. |
| `backend/bin/generate-manifest.sh` | Omit (read for pattern) | Pattern reused for new render script; not modified directly. |
| `lib/files-decl-parser.sh`, `lib/section-validator.sh` | Omit | Phase A doesn't add new validators (those land in Phase B). |

## Omission Decisions

- **No new validator in Phase A.** Validators that consume the
  vocabulary land in Phase B (subagent dispatch, Layer F enrichment).
  Phase A makes the registry queryable; gating decisions wait for
  evidence from real consult usage.
- **Aliases for the long tail of auto-extracted entries:** left
  empty intentionally. Curated only for the ~50 highest-authority
  concepts. Aliases for the rest grow organically as drift is
  documented (graduate-vocab.sh mechanism, deferred to Phase C).
- **Authority score:** documented in schema as future field,
  not populated in Phase A. Will be derived from usage_count
  + cross_references count in Phase C maintenance scripts.
- **Tests for `consult.sh vocab`:** unit tests via existing
  `consult.sh` patterns (no test file currently exists for consult;
  smoke test via real registry sufficient for Phase A. Test harness
  for consult is itself a follow-up).
- **Rename support (`vocab-rename.sh`):** out of Phase A scope.
  Initial registry is created, not modified — rename tooling lives
  in Phase C.

## Norms

- The `_vocabulary.yaml` file **must** be the single source of truth
  for vocabulary; the rendered `ubiquitous-language.md` **shall
  never** be edited directly.
- Every `canonical` entry **must** have a non-empty `definition` and
  a real `authoritative_path`; auto-bootstrap **shall** mark
  uncurated entries with empty `aliases` (not absent), so curation
  status is visible.
- The bootstrap script **must** be idempotent: re-running on an
  existing `_vocabulary.yaml` **shall never** overwrite curated
  fields (`aliases`, `definition`, `related`); it only adds new
  symbols and updates `authoritative_path` if the symbol moved.
- `consult.sh vocab <term>` **must** match against `canonical` and
  every `aliases[].term`, case-insensitive; matching **shall never**
  fall back to substring (a misspelled term should report not-found,
  not silently match a partial).
- The render step in `make manifest` **must** fail loudly if
  `_vocabulary.yaml` is malformed YAML; silent skip is forbidden.

## Safeguards

| Risk | Mitigation |
|------|------------|
| Bootstrap script overwrites curated aliases on re-run | Idempotency via key-aware merge: read existing YAML, preserve curated keys, only add/update auto-derived keys (`canonical`, `authoritative_path`, `layer`). Tests with two-pass invocation in TDD. |
| `_vocabulary.yaml` becomes a sprawl of ~400 auto-extracted entries with empty curation | Accept as expected initial state. The 50 high-authority entries are curated in this interaction; the rest grow via Phase C graduation pathway. The render groups by `bounded_context` so navigation stays usable. |
| `consult.sh vocab` matches noise tokens (e.g., user types "the" and matches an entry whose definition contains "the") | Match only against `canonical` and `aliases[].term` fields, never `definition` or other prose. Implementation uses `yq`-like extraction restricted to those keys. |
| YAML parsing in shell is fragile (no `yq` guaranteed in environment) | Use awk/sed with documented YAML constraints (one entry per `- canonical:` block, no nested anchors, alias terms quoted). The bootstrap and consult both follow the same constraint set; broken-YAML scenario would already fail bootstrap idempotency check. |
| Render step inflates the manifest cycle time | Render targets `docs/knowledge/ubiquitous-language.md` only when `_vocabulary.yaml` newer than the rendered file (mtime check). Average regeneration adds ~1 sec. |
| CLAUDE.md Step 0 update produces a false sense that Phase A's registry covers all terms | Step 0 instruction explicitly says "consult vocabulary; if term not found, proceed normally and consider whether the term should graduate". Empty registry hits are not blocking; they just don't help. |
| Curated entries drift from authoritative_path as code moves | `vocab-drift.sh` (Phase C) detects this; for Phase A, the bootstrap's idempotent re-run updates `authoritative_path` if the symbol moved within the same canonical name. Renames (canonical changes) are out of scope until Phase C `vocab-rename.sh`. |
| 50 curated entries take significant time and may be incomplete | Curate iteratively in this interaction; if compaction approaches, commit partial curation as a checkpoint and continue in a follow-up interaction (which would be Phase A.2, not Phase B — same scope, just split for operational reasons). The schema, script, and integrations are the load-bearing deliverables; curation depth is graceful-degrading. |

## Implementation outline (informs planning)

1. **Wave 1 — Schema document.** Write `docs/knowledge/_vocabulary.yaml`
   with header comment documenting schema + 5 hand-curated example
   entries (Route, RouteStop, Driver, Vehicle, RouteSnapshot) to
   anchor the format.
2. **Wave 2 — Bootstrap script.** `scripts/bootstrap-vocabulary.sh`
   that scans the listed sources, generates auto-derived fields,
   and merges into existing YAML idempotently.
3. **Wave 3 — Run bootstrap + curate ~45 more high-authority
   entries.** Manual judgment on aliases; definitions one-line each.
4. **Wave 4 — `consult.sh vocab` subcommand.** Add subcommand block
   alongside existing patterns; implement lookup, --all, --context.
5. **Wave 5 — Render script + `make manifest` integration.** Script
   emits markdown grouped by `bounded_context`. Make target updated.
6. **Wave 6 — CLAUDE.md Step 0 update.** Add the consultation
   instruction.
7. **Wave 7 — Verification.**
   - Existing test suite (31 tests) → still pass (no behavior change
     to validators).
   - `bash -n` syntax checks.
   - Smoke: `consult.sh vocab Route` returns the canonical entry.
   - Smoke: `make manifest` regenerates `ubiquitous-language.md`.
   - Visual: rendered file readable, grouped by context.

## Verification plan

- 31 existing tests still pass.
- `bash -n` clean on bootstrap script + render script + modified
  consult.sh + Makefile syntax.
- Smoke: `consult.sh vocab Route` → exit 0 with canonical entry
  printed; `consult.sh vocab nonexistent_xyz` → exit 1.
- Smoke: `consult.sh vocab ruta` (alias) → returns same Route entry
  as canonical lookup.
- `make manifest` regenerates `docs/knowledge/ubiquitous-language.md`
  without errors.
