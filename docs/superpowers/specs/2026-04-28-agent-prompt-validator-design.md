# Spec — Hito: REASONS-light Validator for Subagent Prompts (Layer Agent)

**Date:** 2026-04-28
**Branch:** `claude/review-workflow-improvements-x78Zp`
**Type:** code change (full flow)
**Backlog ref:** Hito 4 of 5 (originally Manus's Hito 4) from 2026-04-28 SPDD analysis.

## Problem

Subagent prompts today require process boilerplate (consult+verify
mini-flow + parallel-progress-tracking, both documented in `AGENTS.md`)
but lack architectural framing. Subagents dispatch with a fresh context
and no conversation history; whatever the orchestrator writes is the
totality of their guidance. Without explicit Norms (architectural
invariants the agent's work must preserve) and Safeguards (risks
specific to the agent's scope, especially in parallel dispatch), agents
make local decisions that conflict with the orchestrator's spec.

The Hito 1 work (commit `b39e543`) added Layers N+S to specs in
full/debug flows, so canonical Norms/Safeguards now exist as artifacts.
The agent prompt should reference them (single source of truth) or
inline agent-specific extensions. Today neither is required.

## Approach Chosen

**A — HARD block at `pre-agent-check.sh` (Gate 3), inline OR
spec-reference.**

Adds a third gate to the existing PreToolUse hook (after clean-repo
and classify-validation gates). Triggered for any `Agent` dispatch
whose `subagent_type` is not in the read-only exempt list (currently
`Explore`).

The agent prompt (i.e., `tool_input.prompt`) must include both:

- A `## Norms` section satisfied either by:
  1. **Inline:** ≥1 line containing an imperative keyword (`must`,
     `shall`, `never`, `always`, `no se permite`, `no debe`,
     `siempre`, `jamás`).
  2. **Reference:** mention of a spec path
     (`docs/superpowers/specs/.+\.md`) plus the token `Norms`
     within ~50 chars of that path (e.g.,
     `see docs/superpowers/specs/2026-04-28-X.md § Norms`).

- A `## Safeguards` section satisfied either by:
  1. **Inline:** ≥1 markdown table row with both `Risk` and
     `Mitigation` column tokens on the header line.
  2. **Reference:** spec path + `Safeguards` token within ~50 chars.

Block (deny) on missing section or unsatisfied content of either.

## Alternatives Rejected

**B — Inline only (force literal copy from spec).**

- Violates DRY. Two sources of truth (spec and prompt) drift.

**C — Reference only (force `see spec § X` always).**

- Breaks legitimate use cases where no spec exists (light agents
  spawned for utility work, isolated worktree agents bootstrapping
  framework changes per AGENTS.md "Light Agent Mode" section).

**D — SOFT keyword scan** (Manus's literal proposal).

- SOFT is the recoil pattern blocked by Layer K (commit `0923cdb`).

**E — Auto-injection by the hook** (modify `tool_input.prompt`).

- Hook input modification is not a documented Claude Code
  capability, and even if technically possible would remove the
  orchestrator's accountability for articulating constraints.
  Visibility loss > automation gain.

## 4-Test Application (honest, on the maximal version)

| Test | Verdict | Evidence |
|---|---|---|
| 1. LLM no aplica espontáneamente | ✓ | Existing AGENTS.md boilerplate covers process (consult+verify, progress-tracking), not architecture. Execution log 2026-04-24-routes-widget-audit-fixes documents 4 parallel problems dispatched without canonical Norms/Safeguards in prompts. |
| 2. Fase correcta | ✓ | PreToolUse on Agent — blocks **before** dispatch. Cost to fix: edit prompt and retry (seconds). Catching mid-execution would require subagent rollback. |
| 3. Coste proporcional al valor | ✓ | ~50 lines validator + ~90 lines tests + ~15 lines AGENTS.md + ~3 lines CLAUDE.md. Parallel dispatch frequency: roughly 1 in 8 interactions per recent logs. When it happens, structural prompts have outsize value (subagents have no other context). Same order as Layer Sync. |
| 4. Backed by source | ✓ | SPDD REASONS Canvas (Norms + Safeguards dimensions); Hito 1 spec sections (canonical artifacts now exist); 2026-04-24 execution log (parallel dispatch precedent). |

Pass on all four. No reduction needed.

## Existing Functionality Inventory

| Element | Decision | Justification |
|---|---|---|
| `.claude/hooks/pre-agent-check.sh` (59 lines, Gates 1+2: clean-repo + classify) | Transform | Append Gate 3 for Norms+Safeguards |
| New `.claude/hooks/test-pre-agent-check.sh` | Create | TDD harness; no existing tests for this hook |
| `AGENTS.md` (544 lines, two existing boilerplates) | Transform | Add a third mandatory section block "Norms & Safeguards" with both forms documented |
| `CLAUDE.md` "Enforcement gates" table | Transform | One row for Gate 3 |
| `.claude/hooks/lib/test-harness.sh` | Omit | Reuse existing helper, no changes |
| Read-only subagent type list (currently just `Explore`) | Omit | Single-list maintenance is fine for now; if more exempt types emerge, graduate to a YAML registry |

## Omission Decisions

- **Auto-injection mechanism (E):** out of scope; documented as
  rejected alternative.
- **Per-subagent-type granularity:** all non-`Explore` types treated
  identically. If `Plan` (read-only architect agent) becomes a
  frequent dispatch target, exempt it explicitly. Single-occurrence
  precedent does not justify generalization yet.
- **Validation of the spec-reference target (does the cited spec
  actually have a Norms section?):** out of scope. Layer N already
  enforces Norms presence on every spec; a transitive guarantee is
  acceptable. Only structural validation here.

## Norms

- Gate 3 **must** apply only to write-capable subagent types; `Explore`
  and any future read-only types **shall never** be subject to this
  validation.
- The hook **must** continue blocking via the existing
  `permissionDecision: deny` JSON mechanism; new exit codes or
  unrelated side effects are forbidden.
- Inline imperative-keyword detection **shall** be bounded to the
  `## Norms` section content (state machine, mirroring Layer N from
  brainstorm-validator.sh).
- Spec references **must** match a real path pattern
  (`docs/superpowers/specs/.+\.md`); arbitrary strings invoking
  "Norms" without a path **shall not** satisfy the reference form.
- The validator **must never** false-positive on agent prompts that
  satisfy either form; the inline-OR-reference disjunction is
  load-bearing for legitimate use cases without specs.

## Safeguards

| Risk | Mitigation |
|------|------------|
| Hook stdin contains escaped JSON; naive `grep` on the prompt may match keywords inside JSON metadata rather than the prompt body | Extract `tool_input.prompt` via `jq -r` first (already the existing pattern for `AGENT_PROMPT`), then operate on the unescaped string |
| Imperative keyword regex over-matches casual prose elsewhere in the prompt (e.g., "always check the file path" appearing in the Operations section) | Bound the keyword scan to lines under `## Norms` heading only — same awk state machine as Layer N |
| Spec-reference proximity heuristic (path + Norms within 50 chars) misses legitimate longer-form references | Use a generous proximity (~200 chars) and document the convention in AGENTS.md so authors structure references compactly |
| Existing parallel-dispatches without Norms/Safeguards become retroactively blocked, breaking in-flight work | Gate runs only on new dispatches at PreToolUse; in-flight subagents already running are unaffected. Document in CLAUDE.md as forward-only |
| Hook test harness needs to fabricate the JSON tool_input shape | Build fixture JSON in `$TEST_TMPDIR` and pipe via stdin; mirror existing precedent from `pre-agent-check.sh` consumers (no test file exists yet, so this is greenfield) |
| Read-only types list is hardcoded; future additions require validator edits | Document in spec; if list grows past 3 entries, graduate to `.claude/hooks/lib/agent-readonly-types.txt` (one per line). Single-list policy for now |

## Implementation outline (informs planning)

1. **Wave 1 — TDD red.** Create `test-pre-agent-check.sh` with fixtures
   covering all gate behaviors (existing + new):
   - **A1:** dirty repo → block (regression test on existing Gate 1)
   - **A2:** clean repo + Explore type → pass (regression on existing exempt)
   - **A3:** clean repo + general-purpose + prompt without Norms → block (new Gate 3)
   - **A4:** clean repo + prompt with inline Norms+Safeguards → pass
   - **A5:** clean repo + prompt with spec-reference Norms+Safeguards → pass
   - **A6:** clean repo + prompt with `## Norms` heading but no imperative inline and no spec reference → block

2. **Wave 2 — Implement Gate 3** in `pre-agent-check.sh` after Gate 2.
   Use awk state machine for section extraction; reuse imperative
   keyword and Risk|Mitigation regex from brainstorm-validator's
   Layers N+S.

3. **Wave 3 — Document.**
   - `AGENTS.md`: new section "Norms & Safeguards (mandatory)" with
     two example forms (inline and reference).
   - `CLAUDE.md`: row in enforcement gates table.

4. **Wave 4 — Verify.**
   - `bash test-pre-agent-check.sh` → 6/6 pass.
   - `bash test-brainstorm-validator.sh` → 19/19 still pass (no regression).
   - `bash test-sync-validator.sh` → 6/6 still pass.
   - `bash -n` syntax checks.
   - Smoke test: invoke pre-agent-check with a constructed Agent JSON
     using this spec's path as reference → exit 0.

## Verification plan

- Test harness: 6/6 pass.
- Existing tests: 19+6 = 25 still pass (no regression).
- `bash -n` clean.
- Smoke test against fabricated Agent invocation referencing this
  spec's `§ Norms` and `§ Safeguards`: exit 0.
