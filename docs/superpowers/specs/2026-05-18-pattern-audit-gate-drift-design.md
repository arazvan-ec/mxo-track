# Spec — `pattern-audit.sh` Gate-Drift Detection

**Date:** 2026-05-18
**Branch:** `claude/compare-claude-workflows-yrl2P`
**Type:** code change (full flow)
**Scope ref:** Harness improvement P2 of 3 — extend existing audit hook to flag gates with repeated bypasses.

## Problem

`docs/decisions/log.md` records **5 `SKIP_*_GATE` bypass entries** since 2026-04-22, including 3 of `SKIP_PHASE_EXIT_GATE` (2026-04-22, 2026-05-03, 2026-05-06). The 2026-05-06 entry explicitly notes *"3rd case, should graduate to structural fix"* — but nothing in the harness surfaces this pattern automatically. The 3-occurrence graduation threshold that triggers knowledge-module updates (via `pattern-audit.sh`) does not apply to gate-bypass tracking, so the signal sits unaddressed until a human notices.

Concretely, `pattern-audit.sh` today has two detections:
1. Tags/patterns with ≥3 occurrences in execution logs not yet in `_graduations.yaml`.
2. Deprecated-alias mentions in recent logs (≤30 days).

What is missing: **a third detection that parses `docs/decisions/log.md` for `SKIP_*_GATE` bypass entries**, groups by gate name, and surfaces gates with ≥3 bypasses in the last 90 days as advisory candidates for either tuning or legitimization.

## Approach Chosen

Extend `.claude/hooks/pattern-audit.sh` with a new section appended after the existing deprecated-alias scan. Same contract as existing detections: **advisory only** (exit 0), runs automatically on `retrospective → finalize` via `phase-advance.sh`.

### Detection logic

1. Parse `docs/decisions/log.md` extracting all entries with `SKIP_<GATE>_GATE` references in the heading or body.
2. For each entry, extract the date from the `### [YYYY-MM-DD]` heading and the gate name from the `SKIP_*_GATE` token.
3. Filter to entries within the last **90 days** (window fixed in v1; configurable via `PATTERN_AUDIT_BYPASS_WINDOW_DAYS` env var for future tuning).
4. Group by gate name; for groups with count ≥ 3, emit advisory.

### Output format

When ≥1 gate hits the threshold, emit:

```
⚠ pattern-audit: gates with ≥3 bypasses in last 90 days:
  • SKIP_PHASE_EXIT_GATE (3 entries: 2026-04-22, 2026-05-03, 2026-05-06)
    Choose one structural response:
    [TUNE]       Update validator heuristic — gate fires on legitimate work.
                 → review .claude/hooks/validators/<gate>.sh logic
    [LEGITIMIZE] Document as accepted bypass case in CLAUDE.md.
                 → add row to § Bypass env vars with the recurring justification
```

Both options are emitted because the same signal (≥3 bypasses) can mean opposite things (gate too strict vs. legitimate recurring exception). Forcing explicit choice prevents default-to-tune bias.

### Configuration

| Env var | Default | Purpose |
|---|---|---|
| `PATTERN_AUDIT_BYPASS_WINDOW_DAYS` | `90` | Time window for bypass aggregation |
| `PATTERN_AUDIT_BYPASS_THRESHOLD` | `3` | Minimum bypass count to surface a gate |
| `PATTERN_AUDIT_DECISION_LOG` | `docs/decisions/log.md` | Path to decision log |

## Prior Art Audit

| File | Status | Coverage |
|---|---|---|
| `.claude/hooks/pattern-audit.sh:26-88` | ✅ Endorsed | Existing tag/pattern detection — extend pattern, do not duplicate |
| `.claude/hooks/pattern-audit.sh:90-118` | ✅ Endorsed | Existing deprecated-alias scan — same structural pattern (`if [ -f ... ]` guard, advisory output, exit 0) |
| `.claude/hooks/consult.sh` | ✅ Endorsed | Provides `stats` subcommand parsed by pattern-audit; new bypass detection follows same orchestration model |
| `scripts/graduate.sh` | new | Bypass graduation does NOT use `graduate.sh`. Gates are tuned in their own validator or documented in CLAUDE.md § Bypass env vars — neither path goes through `_graduations.yaml` |
| `phase-advance.sh` invocation of `pattern-audit.sh` | ✅ Endorsed | New section runs automatically at same trigger; no orchestration change |
| `.claude/hooks/lib/vocabulary-reader.sh` | ✅ Endorsed | Pattern of `lib/`-prefixed helpers is precedent; new code stays inline in `pattern-audit.sh` for v1 — extract to `lib/decision-log-parser.sh` only if 2nd consumer appears |

## Existing Functionality Inventory

| Element | Decision | Justification |
|---|---|---|
| `pattern-audit.sh` graduation detection (lines 26-88) | **Keep, unchanged** | New detection is additive |
| `pattern-audit.sh` deprecated-alias scan (lines 90-118) | **Keep, unchanged** | New detection appends after this section |
| `pattern-audit.sh` exit 0 contract | **Preserve** | Advisory only; never blocks |
| `phase-advance.sh` invocation point | **Keep, unchanged** | Same trigger; no changes to phase-advance.sh |
| Decision log format (`### [YYYY-MM-DD]` headings + `SKIP_*_GATE` tokens) | **Treat as stable contract** | Both already present in repo; format is the input grammar |

## Omission Decisions

| Element | Decision | Justification |
|---|---|---|
| Auto-edit CLAUDE.md when [LEGITIMIZE] is chosen | **Omit** | Editing CLAUDE.md is an architectural decision; output remains advisory. Auto-editing would violate autonomy contract. |
| Track follow-up status (was the suggestion acted on?) | **Omit** | Out of scope; Learning Review § Gate-drift (spec P3) closes this loop |
| Per-gate threshold customization | **Omit** | Single threshold (3) consistent with knowledge-module graduation threshold |
| Extract decision-log parser to `lib/` | **Omit for v1** | Single consumer; extract only if 2nd appears |
| Test harness for new section | **Include** | Add `tests/hooks/test-pattern-audit-gate-drift.sh` with fixture decision log |

## Norms

- The new detection **must** preserve `pattern-audit.sh`'s advisory contract — exit code remains 0 regardless of findings.
- The output **must** emit both `[TUNE]` and `[LEGITIMIZE]` options for every flagged gate — generic single-option suggestions are not permitted.
- The detection **shall** parse `docs/decisions/log.md` as the only source of bypass evidence — execution logs and other files are not authoritative for this signal.
- The 90-day window **must** be configurable via env var; hardcoded values without env-var indirection are not allowed.
- A gate flagged by this audit **shall** appear in the next Learning Review's gate-drift section (handoff to P3).

## Safeguards

| Risk | Mitigation |
|---|---|
| Decision log heading format changes silently, regex stops matching | Norm fixes decision-log format as contract; integration test with fixture log validates parsing |
| Window/threshold tuning hides legitimate patterns | Env vars allow ad-hoc tightening during audit; defaults are documented |
| False positives from old entries that have already been resolved (gate already tuned) | Window limits to 90 days; resolved gates fall out of window naturally |
| Output noise drowns existing detections | New section appears after existing two; same `⚠` prefix; consistent visual structure |
| `pattern-audit.sh` becomes too long/complex to maintain | If section exceeds ~40 lines, extract decision-log parsing to `lib/decision-log-parser.sh` (deferred to v2 per Omission Decisions) |

## Verification

1. Run `bash .claude/hooks/pattern-audit.sh` against current `docs/decisions/log.md` — **must** flag `SKIP_PHASE_EXIT_GATE` (3 entries: 2026-04-22, 2026-05-03, 2026-05-06).
2. Run test harness with fixture log containing exactly 2 entries of one gate and 3 of another — first must NOT flag, second must flag.
3. Run with `PATTERN_AUDIT_BYPASS_WINDOW_DAYS=7` — only the 2026-05-06 entry within window; **must NOT** flag (count=1).
4. `make lint-shell` passes against modified `pattern-audit.sh`.
