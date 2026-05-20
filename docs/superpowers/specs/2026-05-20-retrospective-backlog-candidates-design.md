# Spec — Retrospective Auto-Propose Backlog Candidates

**Date:** 2026-05-20
**Branch:** `claude/compare-claude-workflows-yrl2P`
**Type:** code change (full flow)
**Scope ref:** P2 of 3 — backlog item "Retrospective phase debe incluir análisis explícito de backlog candidates antes de finalize".

## Problem

The retrospective rule in CLAUDE.md mandates 3 obligatory points (estimate accuracy, process gap, emergent patterns) but **does not require explicit translation of surfaced improvements into backlog entries**. In 2026-05-18 the retrospective listed 5 follow-ups as emergent patterns; the model advanced toward `finalize` without proposing backlog entries until the user interrupted with *"antes de seguir hay que crear un backlog"*. The retrospective → backlog → next-interaction link is the durable value of the retrospective; without enforcement it's lost.

## Approach Chosen

**Three coordinated changes** with HARD enforcement per user's decision:

### (1) Extend `docs/CLAUDE.md` Retrospective visibility rule

Add 4th obligatory point:

```markdown
4. **Backlog candidates analysis (always perform; "0 candidates" is a valid result):**
   Review the emergent patterns and process gaps. For each that meets ≥1 of:
   (a) ≥3 occurrences documented,
   (b) explicitly requested by the user,
   (c) passes the 4-test (forces quality practice + right phase + cost/value + sourced),
   → propose a backlog entry in `docs/backlog.md` BEFORE advancing to finalize.
   If 0 candidates: write the literal line `Backlog candidates: 0 — no surfaced improvements this interaction` in the execution log retrospective section.
```

### (2) Extend `retrospective-validator.sh` (HARD)

At `retrospective → finalize` exit, validate:
- The execution log file at `evidence.execution_log_path` contains either:
  - A `## Backlog candidates` heading with ≥1 bullet, OR
  - The literal line `Backlog candidates: 0 — no surfaced improvements`
- IF the execution log has ≥1 bullet under `## Backlog candidates`, then `git status --short docs/backlog.md` OR `git diff --name-only ...HEAD` must show `docs/backlog.md` modified.

Exit 2 (BLOCK) if either condition fails. Message guides the user: *"Retrospective lists N candidates but docs/backlog.md unchanged. Add entries before advancing."*

### (3) Heuristic for the model (documented, not enforced by code)

Add to CLAUDE.md after the rule: *"After presenting the retrospective and receiving user approval (`retrospective_shown=true`), the model SHOULD automatically ask: '¿Propongo entradas de backlog para los N follow-ups identificados?' before invoking `phase-advance.sh finalize`."* — documented behavior reinforces what the validator enforces structurally.

## Existing Functionality Inventory

| Element | Decision | Justification |
|---|---|---|
| `docs/CLAUDE.md` § Retrospective visibility rule | **Extend** | Add 4th point; preserve existing 3 |
| `.claude/hooks/validators/retrospective-validator.sh` | **Extend with new check** | Add backlog-candidates verification at exit gate |
| `.claude/hooks/test-retrospective-validator.sh` | **Extend with new test cases** | New cases: retro without candidates section → fail; retro with candidates but no backlog edit → fail; retro with "0 candidates" line → pass |
| `docs/backlog.md` | **No format change** | Existing entry format already supports the use case |
| `.claude/hooks/phase-advance.sh` retrospective→finalize call | **Unchanged** | Validator is invoked from existing slot |

## Omission Decisions

| Element | Decision | Justification |
|---|---|---|
| Auto-generate backlog entries from emergent-patterns text | **Omit** | Triage is human reasoning; auto-generation risks low-quality entries |
| Track outcome of backlog candidates (did they get implemented?) | **Omit for v1** | Out of scope; future Learning Review § Gate-drift consumes the items |
| WARNING instead of HARD | **Omit per user decision** | User chose HARD (2026-05-20 brainstorm Q3) |
| Cross-link from CLAUDE.md root to this rule | **Omit** | The rule lives in docs/CLAUDE.md; main CLAUDE.md doesn't need duplicated reference |

## Norms

- The retrospective execution-log section **must** include either `## Backlog candidates` heading with bullets OR the literal line `Backlog candidates: 0 — no surfaced improvements`. Implicit silence **is not acceptable**.
- When candidates are listed, `docs/backlog.md` **must** be modified in the same interaction's git diff before `retrospective → finalize` succeeds.
- The model **shall** ask the user *"¿Propongo entradas de backlog para los N follow-ups?"* after retrospective approval is detected — undocumented silent advance is forbidden.
- The validator **must** be HARD (exit 2) per user's explicit decision; soft warning is not permitted.

## Safeguards

| Risk | Mitigation |
|---|---|
| Validator over-strict — fails on retros with "0 candidates" written slightly differently | Match heading `## Backlog candidates` AND/OR exact literal line "Backlog candidates: 0 — no surfaced improvements" (case-insensitive on "no surfaced"). Tests cover variants |
| User legitimately wants to skip backlog this time | Per user decision, no opt-out for v1. If 3+ legitimate skips occur, graduate to WARNING. Bypass available: `SKIP_PHASE_EXIT_GATE=1` + decision-log entry |
| Validator fails because backlog edit is in a separate commit | Check `git diff --name-only $PLAN_COMMIT...HEAD` (same reference as sync-validator) — covers any commit within interaction range |
| False positive — execution log mentions "backlog" in emergent patterns body but no real section | Heading regex anchored at `^## Backlog candidates` (line start) only — body mentions don't trigger |
| Heuristic-3 "model should ask" is unenforced — model skips | Validator enforces structurally; the model's ask is the polite UX layer. Validator catch is the safety net |

## Verification

1. **Test case A:** retro execution log without `## Backlog candidates` section → validator exit 2.
2. **Test case B:** retro with `## Backlog candidates` heading + 2 bullets, but `docs/backlog.md` not in git diff → validator exit 2.
3. **Test case C:** retro with `## Backlog candidates` + bullets + backlog modified → validator exit 0.
4. **Test case D:** retro with literal "Backlog candidates: 0 — no surfaced improvements" line + backlog NOT modified → validator exit 0.
5. **Integration:** advance current/next interaction through retrospective → finalize with proper section; verify no false block.
