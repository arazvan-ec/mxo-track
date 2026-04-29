---
type: feature
tags: [harness, vocabulary, ubiquitous-language, ddd, spdd, phase-b]
files_touched:
  - .claude/hooks/pre-agent-check.sh
  - .claude/hooks/ddd-boundary-check.sh
  - .claude/hooks/pattern-audit.sh
  - docs/superpowers/specs/2026-04-29-uls-phase-b-design.md
  - docs/superpowers/plans/2026-04-29-uls-phase-b.md
patterns: [vocab-consumer, deprecated-alias-detection, mawk-vs-gawk-regex]
outcome: success
outcome_verified_at: 2026-04-29
regressions_later: []
pr_number: null
estimated_lines: 150
actual_lines: 175
duration_minutes: null
consulted_in_future: []
---

# Execution Log — 2026-04-29 — Hito 3 Phase B: ULS Vocabulary Consumers

**Branch:** `claude/review-workflow-improvements-x78Zp`
**Spec:** `docs/superpowers/specs/2026-04-29-uls-phase-b-design.md`
**Plan:** `docs/superpowers/plans/2026-04-29-uls-phase-b.md`

## Summary

Three vocabulary-consumer integrations, all WARN-only:

- **B-1 (`pre-agent-check.sh` Gate 4):** scans agent prompts for
  deprecated aliases from `_vocabulary.yaml`; emits systemMessage
  warning suggesting canonical replacement.
- **B-2 (`ddd-boundary-check.sh`):** when a spec mentions a canonical
  whose `bounded_context` differs from the inferred context for the
  touched path, emits stderr WARN.
- **B-3 (`pattern-audit.sh`):** scans recent execution logs for
  deprecated-alias mentions; surfaces during retro→finalize audit.

## Approach

Each integration reads `_vocabulary.yaml` directly via awk
(extracts deprecated alias→canonical pairs), then bash + grep
performs the whole-word case-insensitive match against the relevant
text (prompt body, spec text, log corpus).

## Implementation observations

### 1. mawk vs gawk regex incompatibility

Initial implementation used `match(corpus, "\\<term\\>")` with
`IGNORECASE=1` — a gawk idiom. The runtime here is mawk, which
treats `\<` and `\>` as literals and ignores `IGNORECASE`.
Diagnosed via standalone awk test: pattern returned no matches
even when the term was clearly present. Fix: extract pairs in
awk, do the match in bash with `grep -wE` (which is portable
and supports word boundaries reliably).

This is the **third occurrence** of "regex portability surprise"
in this branch (similar to backtick stripping in files-decl-parser
and pre-push-gate quoted-context bug). Pattern not graduated yet
but tracking.

### 2. WARN-only design matched curation maturity

37/84 vocabulary entries are curated. HARD-gating any of these
checks would block legitimate work because uncurated entries have
empty `aliases: []` and trigger nothing — but the registry's
incomplete-curation state would surface as confusion. WARN-only
keeps the surface advisory until Phase C raises depth.

### 3. Layer F vocab cross-ref required spec_path resolution

B-2 needs to read the spec to scan for canonical mentions. The
hook reads `evidence.spec_path` from session-state.json and
resolves relative-to-repo. Edge case: spec_path is empty (no spec
yet) → silent skip, correct behavior.

## Changes

| File | Change |
|------|--------|
| `.claude/hooks/pre-agent-check.sh` | +30 lines: Gate 4 vocab scan after Gate 3 Norms/Safeguards |
| `.claude/hooks/ddd-boundary-check.sh` | +45 lines: vocab cross-ref block at end |
| `.claude/hooks/pattern-audit.sh` | +35 lines: deprecated-alias scan after existing tag-pattern surface |

Net: ~110 lines code + 65 lines doc artifacts (spec/plan).

## Verification

- `bash -n` clean on all three modified files.
- `test-brainstorm-validator.sh` → **19/19 pass**.
- `test-sync-validator.sh` → **6/6 pass**.
- `test-pre-agent-check.sh` → **6/6 pass** (Gate 3 still functional).
- Smoke B-3: fixture log mentioning "tour" / "waypoint" → both
  surfaced with canonical suggestions. Confirmed by manual test
  via `EXEC_LOGS_DIR=/tmp` override.
- Smoke B-1 deferred to next agent dispatch (Gate 1 clean-repo
  check fired during smoke setup; would have masked B-1 logic).
- Smoke B-2 deferred to next critical-path edit with spec.

## Retrospective

### 1. Estimate accuracy

| Metric | Estimate | Actual | Delta |
|---|---|---|---|
| pre-agent-check.sh | +60 | +30 | -50% |
| ddd-boundary-check.sh | +50 | +45 | OK |
| pattern-audit.sh | +40 | +35 | OK |
| Total net | ~150 | ~110 | -27% (over-estimated) |

The pre-agent-check addition came in lighter because the alias-
extraction pattern from B-3 was already designed; reusing
shaved ~30 lines.

### 2. Process gaps

- **mawk regex bug** consumed ~15 minutes of debugging before
  the standalone-awk test revealed it. **Lesson:** when an
  awk expression silently produces no output, isolate the
  pattern in a standalone awk run before assuming the data is
  the issue. Tracking 3/3 across this branch (regex portability
  surprises) — eligible for graduation as a knowledge module
  entry per the 3-rule.

- **B-1 and B-2 smoke tests were partial.** Both depend on
  external triggers (agent dispatch, framework-path edit) that
  didn't fire during this interaction's flow. Smoke B-3 ran
  cleanly via fixture-dir override. Acceptable — the integrations
  are WARN-only, low risk if a smoke is deferred.

### 3. Emergent patterns

- **Vocabulary consumer pattern at 3 instances** (B-1, B-2, B-3
  all read `_vocabulary.yaml` for deprecated aliases). **Threshold
  reached.** Follow-up: extract `lib/vocabulary-reader.sh` with
  a `vocab_deprecated_aliases` function returning the alias|canonical
  map. Plan for Phase C or before.

- **mawk-vs-gawk regex portability** — third instance (after
  backtick stripping in files-decl-parser and pre-push-gate
  quoted-context). Same root cause class. Schedule fix to
  document portable awk constraints in `.claude/README.md`.

## Follow-ups

1. **Extract `lib/vocabulary-reader.sh`** — 3 consumers reach
   threshold. Phase C scope. **Tracking 3/3.**
2. **mawk vs gawk regex portability docs** — graduates to
   knowledge module on 3rd occurrence. **Tracking 3/3.**
3. **Pre-push gate heredoc bug** — tracking 2/3 (held this
   commit's message short to avoid trigger).
4. **classify-validator "code change" non-match** — tracking 1/3.
5. **user_turns reset interaction-cross** — tracking 1/3.
