# Spec — Approval UX Overhaul

**Date:** 2026-05-20
**Branch:** `claude/compare-claude-workflows-yrl2P`
**Type:** code change (full flow)
**Scope ref:** P1 of 3 — backlog items "approval regex extension" + "UX layer: proactive gate-feedback + semantic approval interpretation".

## Problem

4 documented occurrences (2026-04-28, 2026-04-29, 2026-05-06, 2026-05-18) of the same pattern: user approves verbally with wording the regex doesn't match → `user_approved=false` → gate blocks → user friction. The user surfaced this explicitly: *"Hay que mejorar la detección de los approves, o me indicas mejor como seguir o entiendes mejor mis respuestas"*. Existing regex in `user-prompt-state.sh:73` and `:106` (duplicated) covers ~28 verbs/phrases but misses common Spanish directive verbs ("avanza", "sigue", "vamos", "pasa a", "arranca", "tira", "venga"). The hook is also reactive — informs only AFTER blocking, forcing extra friction turns.

## Approach Chosen

**Five coordinated changes** to `user-prompt-state.sh` and `post-bash-validator.sh`:

### (a) Extend approval regex
Add to the alternation: `avanza|sigue|vamos|pasa a|arranca|tira|tira para|tira con|venga|empieza|continúa con|ve con`.

### (b) DRY refactor — extract shared regex
Move the approval regex into a single variable `APPROVAL_REGEX` at the top of `user-prompt-state.sh`, used by both the general approval detection (was `:73`) and the retrospective approval detection (was `:106`). Same for rejection regex.

### (c) Proactive gate feedback
When the hook computes status line, if `user_approved=false` AND `current_phase ∈ {brainstorming, retrospective}` AND `evidence` indicates pre-gate state (alternatives_proposed=true for brainstorming, capture phase complete for retrospective), emit an extra line:

```
✋ Para avanzar di una de: apruebo / ok / procede / adelante / avanza / sigue / vamos
   Si quieres rechazar: no / cambia / rechazo
```

### (d) Direct-write warning
When `post-bash-validator.sh` (which absorbs phase-transition-controller logic) detects a direct jq write to `user_approved` from a non-sanctioned tool and reverts it, append a line to `/tmp/ptc-revert-warnings.log` AND emit a stderr warning visible to the model: *"⚠ user_approved direct-write detected and reverted. Only user-prompt-state.sh may write this flag."*. Helps the model notice its own mistake (the very mistake that caused the 4th occurrence in 2026-05-18).

### (e) Lightweight semantic probe
When prompt:
- Is short (≤ 80 chars)
- Does not match approval regex
- Does not match rejection regex
- Current phase is pre-gate-exit AND `evidence.user_approved=false`

→ hook emits a **clarification probe** line in stdout:

```
📋 Prompt ambiguo — no match approval/rejection.
   Si quisiste aprobar lo presentado: di "apruebo".
   Si quisiste rechazar o cambiar: di "no" o "cambia".
```

This is NOT an external LLM call. It uses the **orchestrator-side LLM** (the model already in the conversation) to disambiguate by re-asking with explicit options. Avoids latency/cost/auth burden of external API.

## Prior Art Audit

| File | Status | Coverage |
|---|---|---|
| `.claude/hooks/user-prompt-state.sh` (lines 73, 91, 106) | ✅ Endorsed | Regex extension is direct edit. **Control flow check (per 2026-05-18 retrospective Gap A):** lines 73-78 set `user_approved=true` and update snapshot; lines 84-87 do same for decision-ID approval; lines 91-96 reset to false on rejection; lines 105-110 do retrospective_shown. NO short-circuits between these blocks; safe to extend regex |
| `.claude/hooks/post-bash-validator.sh` | ✅ Endorsed | Contains phase-transition-controller logic inlined 2026-04-08. Adding the revert-warning emission requires reading + 1 new echo line; no structural change |
| `.claude/hooks/test-enforcement-layers.sh:225-232` | ✅ Endorsed | Existing test "Direct user_approved write is reverted" — extend to also assert warning log appears |
| `/tmp/ptc-state-snapshot.json` | ✅ Endorsed | Existing mechanism; we add `/tmp/ptc-revert-warnings.log` as sibling |
| `.claude/hooks/test-user-prompt-state.sh` | new | Does not exist; create with cases for (a) extended regex + (b) shared regex + (c) proactive feedback + (e) ambiguous probe |
| `.claude/hooks/lib/flow-phases.sh` | ✅ Endorsed | Read-only — used to detect "pre-gate state" semantically |

## Existing Functionality Inventory

| Element | Decision | Justification |
|---|---|---|
| Existing 28-verb regex | **Keep, extend** | Backwards compat critical — every prior approved interaction relied on it |
| Decision-ID detection (lines 81-88) | **Keep, unchanged** | Different mechanism; not duplicated |
| Rejection regex (line 91) | **DRY refactor only** | Same refactor pattern as approval; not extended |
| Retrospective approval gate phase=retrospective | **Keep, share regex** | Behavior unchanged; only regex source changes |
| `/tmp/ptc-state-snapshot.json` | **Keep** | Used by controller for revert detection |
| Status line format (compact) | **Keep, append** | New feedback lines append after, never replace existing format |

## Omission Decisions

| Element | Decision | Justification |
|---|---|---|
| External LLM call for semantic probe | **Omit** | Adds API key mgmt + latency + cost + failure modes. Orchestrator-side LLM (model in conversation) already has full context; ask-explicitly is the right primitive |
| Hook-side caching of approval state | **Omit** | session-state.json already authoritative; cache adds invalidation risk |
| Auto-correct on rejection (e.g., "did you mean cambia?") | **Omit** | Rejection regex is generous enough; out of scope |
| Multi-language regex beyond Spanish + English | **Omit** | Repo is bilingual; other languages YAGNI |

## Norms

- The approval regex **must** be a single shared variable in `user-prompt-state.sh`; duplication between general approval and retrospective approval is **forbidden** after this change.
- Direct writes to `user_approved` from any source other than `user-prompt-state.sh` **must** trigger both revert (existing behavior) AND warning log emission.
- The proactive feedback line **must** appear ONLY when (a) `user_approved=false`, (b) phase is pre-gate-exit, (c) evidence indicates the gate will fire — never spuriously.
- The semantic-probe clarification **must NOT** make external API calls; it relies on orchestrator-side LLM re-asking.

## Safeguards

| Risk | Mitigation |
|---|---|
| Extended regex matches too aggressively (false positive approval) | New verbs are all directive (`avanza`, `sigue`, `vamos`, etc.) and word-boundary-anchored; rejection regex still has precedence; tests cover counter-examples ("no avances" must NOT match approval) |
| Proactive feedback spam — every turn shows the line | Conditional emission: only when pre-gate AND user_approved=false. After approval detected, line disappears |
| DRY refactor breaks retrospective approval detection | Test must assert: same prompt that approves in brainstorming also sets `retrospective_shown` in retrospective phase |
| Direct-write warning log grows unbounded | Log file is `/tmp/ptc-revert-warnings.log` — ephemeral; cleared on container restart. No retention policy needed |
| Semantic probe creates noise when prompt is genuinely off-topic | Probe condition requires phase=pre-gate-exit; off-topic prompts in normal work won't trigger |

## Verification

1. `test-user-prompt-state.sh` (new, 8+ cases): regex matches for all old + new verbs; DRY refactor preserves behavior; proactive feedback emitted conditionally; semantic probe emitted only on ambiguity; direct-write warning logged.
2. `test-enforcement-layers.sh` (existing): regression — direct-write to `user_approved` still reverted; new assertion: warning log line appears.
3. `test-retrospective-validator.sh` (existing): regression — retrospective approval still works with shared regex.
4. Integration: run a phase-advance with `user_approved=false` and a directive prompt ("avanza") — `user_approved` should transition to `true`, no bypass needed.
