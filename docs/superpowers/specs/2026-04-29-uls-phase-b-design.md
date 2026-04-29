# Spec — Hito 3 Phase B: ULS Vocabulary Consumers

**Date:** 2026-04-29
**Branch:** `claude/review-workflow-improvements-x78Zp`
**Type:** code change (full flow)
**Backlog ref:** Hito 3 Phase B of 3.

## Problem

Phase A (commit `11747bf`) created `_vocabulary.yaml` (84 entries),
the bootstrap script, the render pipeline, and `consult.sh vocab` —
but no validator yet **consumes** the registry to enforce term
alignment. Vocabulary exists but doesn't catch drift.

Three concrete drift surfaces are unprotected:

1. **Subagent dispatch.** The orchestrator writes prompts in chat
   prose. Without vocab consultation, a prompt may say "tour" or
   "stop" when the canonical is `Route` or `RouteStop` — the
   subagent then propagates the deprecated alias.
2. **DDD boundary checks.** `ddd-boundary-check.sh` (Layer F) maps
   file paths to bounded contexts via `_ddd-boundaries.yaml`. If a
   spec mentions a canonical concept whose vocabulary entry has a
   different `bounded_context` than the path Layer F infers, that's
   a real architectural inconsistency and should be flagged.
3. **Pattern-audit / retrospective surfacing.** `pattern-audit.sh`
   runs on retrospective→finalize and surfaces tag patterns. It
   should also surface deprecated-alias usage in execution logs
   (e.g., a log uses "tour" but `_vocabulary.yaml` has
   `tour, surface: deprecated`).

## Approach Chosen

**A — Three vocabulary-consumer integrations in one interaction.**

### B-1: Subagent prompt vocab consultation

Extend `pre-agent-check.sh` Gate 3 (Norms+Safeguards) with an
additional check: scan the agent prompt for any token that matches
a `canonical` or any `aliases[].term` in `_vocabulary.yaml`. If a
token matches an alias with `surface: deprecated`, **WARN** (do not
block — the model may legitimately quote the deprecated term in
context). If a token matches an alias with `surface: user|internal`
but the canonical isn't also in the prompt, **suggest** the canonical
in the warning.

Rationale: hard-blocking aliases is too aggressive (legitimate uses
exist in narration). The warning surface keeps the orchestrator
informed without false-positives.

### B-2: ddd-boundary-check vocab cross-reference

Extend `ddd-boundary-check.sh` to consult `_vocabulary.yaml` when
processing a spec that touches critical paths. For each canonical
mentioned in the spec whose `bounded_context` differs from the
context inferred from `_ddd-boundaries.yaml` for the touched path,
emit a WARN. Only WARN, not BLOCK: vocabulary `bounded_context` is
informational metadata, not architectural truth.

### B-3: pattern-audit deprecated-alias detection

Extend `pattern-audit.sh` to scan the latest N execution logs for
deprecated-alias mentions. If a log uses a term that
`_vocabulary.yaml` lists with `surface: deprecated`, surface a
suggestion to update the log or graduate the alias.

All three integrations are WARN-only (no new HARD gates). The vocab
registry is informational scaffolding for now; HARD gating waits for
Phase C when curation depth is higher.

## Alternatives Rejected

**B — Sub-split into B-1, B-2, B-3 separate interactions.**

- Rejected: triple workflow ceremony for three consumers of the
  same registry that share parser logic. Compatibility with the
  Phase A spec commitment.

**C — HARD-gate any drift detection.**

- Rejected: vocabulary is at 37/84 curated; a HARD gate against
  partial data would block legitimate work. Phase C raises
  curation; HARD gating waits for then.

**D — Skip B-2 and B-3, ship only B-1 (subagent dispatch).**

- Rejected: Phase A spec committed all three. Skipping is Layer K
  recoil ("only the high-leverage one"); cost difference is
  marginal (~100 lines per integration).

## 4-Test (honest, on the maximal version)

| Test | Verdict | Evidence |
|---|---|---|
| 1. LLM no aplica espontáneamente | ✓ | The model writes agent prompts and specs in prose; without these gates, no mechanism aligns prose with canonical vocabulary. |
| 2. Fase correcta | ✓ | After Phase A (foundation exists), before Phase C (curation depth). Each consumer fires at its natural workflow point: agent dispatch, brainstorm exit, retrospective exit. |
| 3. Coste/valor | ✓ | ~400 lines of tooling. Three drift surfaces protected. WARN-only level matches the registry's informational maturity (37/84 curated). |
| 4. Backed by source | ✓ | Phase A spec; DDD Ubiquitous Language; existing patterns in pre-agent-check Gate 3 (Hito 4) and ddd-boundary-check (Layer F). |

Pass on all four.

## Existing Functionality Inventory

| Element | Decision | Justification |
|---|---|---|
| `.claude/hooks/pre-agent-check.sh` Gate 3 | Transform | Add vocab-scan after existing Norms/Safeguards check |
| `.claude/hooks/ddd-boundary-check.sh` | Transform | Add vocab cross-ref check at the path/context evaluation step |
| `.claude/hooks/pattern-audit.sh` | Transform | Add deprecated-alias scan over recent logs |
| `.claude/hooks/lib/files-decl-parser.sh` | Omit | Phase B doesn't touch file declarations |
| `.claude/hooks/lib/section-validator.sh` | Omit | Phase B doesn't add new spec sections |
| `_vocabulary.yaml` | Omit | Read-only consumer; no schema changes |
| `consult.sh vocab` | Omit | Phase B doesn't consume via `consult.sh`; reads `_vocabulary.yaml` directly via awk for performance and atomicity |

## Omission Decisions

- **HARD gating any of the three checks:** out of scope. WARN-only
  matches Phase A's curation maturity. Promote to HARD in Phase C
  if specific surfaces prove worth it.
- **A shared `lib/vocabulary-reader.sh`:** premature. The three
  consumers each scan vocabulary differently (token match,
  cross-reference, log-corpus). Extract to lib if a 4th consumer
  emerges.
- **Tests / fixtures for the three integrations:** smoke tests via
  this interaction's flow. Building TDD fixtures for each is
  high-cost low-value when the gates are WARN-only.
- **Auto-update suggestions in execution logs:** out of scope.
  pattern-audit surfaces; humans decide whether to update.

## Norms

- All three integrations **must** be WARN-only, never BLOCK. The
  vocabulary registry is informational at Phase B; HARD gating
  waits for Phase C.
- The vocabulary scans **must** read `_vocabulary.yaml` directly
  (not via shell-out to `consult.sh`); per-call shell-out has
  unacceptable latency in a per-prompt hook.
- Scans **shall never** block on missing or malformed
  `_vocabulary.yaml`; the file may be absent in fresh checkouts or
  during bootstrap. Treat absence as "no vocabulary defined" and
  silently pass.
- Token detection **must** match whole-word boundaries
  (case-insensitive); substring matches against prose
  **shall never** trigger warnings (precedent: `consult.sh vocab`
  Norm in Phase A spec).
- Each integration **must** preserve the existing exit semantics of
  its host validator (Gate 3 of pre-agent-check still BLOCKS on
  Norms/Safeguards; Layer F still BLOCKS on Prior Art Audit
  mismatch; pattern-audit still SUGGESTS without blocking).

## Safeguards

| Risk | Mitigation |
|------|------------|
| `_vocabulary.yaml` missing breaks downstream hooks | Each integration checks `[ -f "$VOCAB_FILE" ] \|\| return 0` early. Absent file → silent pass. |
| Per-prompt vocabulary scan adds latency to UserPromptSubmit / PreToolUse hooks | Use awk single-pass extraction; cache the extracted alias→canonical map per hook invocation, not per token check. Estimated overhead <50ms even for 84-entry vocab. |
| WARN noise becomes white noise; orchestrator stops reading hook output | WARN messages are concise (one line: "vocab: 'tour' is deprecated alias for 'Route'"); each warning is high-signal because the registry only contains curated entries (auto-extracted entries with `aliases: []` produce no warnings). |
| Layer F vocab cross-ref produces false positives when a path is reused across contexts | Match only when the canonical's `bounded_context` is non-empty AND differs from inferred context. Empty `bounded_context` (uncurated entries) → no comparison. |
| pattern-audit deprecated-alias scan has high false-positive rate on prose ("the parade" matching alias "para") | Whole-word boundary match (`\bWORD\b`) eliminates substring confusion. Match against alias.term values only, not arbitrary prose. |
| Three integrations share parser logic; bug in one might propagate to others | Each integration has its own awk extraction; no shared library yet. Bug in one is isolated. Trade-off accepted for Phase B; consolidation when 4th consumer emerges. |
| Smoke testing all three requires constructing fixtures for each | Smoke via real flow: subagent dispatch with a prompt mentioning "tour" should produce vocab WARN; spec mentioning a canonical should trigger Layer F cross-ref; this very interaction's logs feed pattern-audit at retro. |

## Implementation outline

1. **Wave 1 — `pre-agent-check.sh` vocab scan (B-1).** After
   existing Gate 3 Norms/Safeguards check, scan prompt tokens
   against vocabulary. Emit WARN via `systemMessage` for
   deprecated aliases or alias-without-canonical mentions.
2. **Wave 2 — `ddd-boundary-check.sh` cross-ref (B-2).** When
   processing a spec, scan for canonical mentions, compare
   `bounded_context` of each match against the inferred context
   from `_ddd-boundaries.yaml` for the touched path. Emit WARN
   on mismatch.
3. **Wave 3 — `pattern-audit.sh` deprecated-alias scan (B-3).**
   At retrospective→finalize, scan recent execution logs for
   deprecated-alias mentions. Surface suggestions in the audit
   output.
4. **Wave 4 — Verification.**
   - All 31 existing tests still pass.
   - `bash -n` clean.
   - Smoke B-1: agent dispatch with deprecated alias → WARN.
   - Smoke B-2: spec mentioning canonical with mismatched context
     → WARN.
   - Smoke B-3: retrospective with log mentioning deprecated alias
     → WARN.

## Verification plan

- 31 existing tests pass.
- `bash -n` clean on all 3 modified files.
- Three smoke scenarios produce WARNs at the right moment without
  blocking.
