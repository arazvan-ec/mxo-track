# Development Workflow — Technical Reference

**Última actualización:** 2026-03-30
**Estado:** Vigente

Reference documentation for the workflow engine, session state management, gates, validators, and operational knowledge. For the narrative "why" of the development flow, see CLAUDE.md.

---

## Workflow Engine

The hooks in `.claude/hooks/` mechanically enforce the development flow. Claude must update `.claude/session-state.json` to progress through phases; if it doesn't, hooks block code edits.

### How It Works

1. **SessionStart hook** (`session-start.sh`) — resets `session-state.json` at the start of each new day (same-day sessions are preserved). Outputs session context (branch, recent commits, last execution log).
2. **PreToolUse hook** (`workflow-engine.sh`) — before Edit/Write, verifies:
   - `flow_type` is declared (HARD for `src/tests`, SOFT warning for other files)
   - `interaction_id` matches between top-level and evidence (scope-change detection)
   - All flows have mechanical enforcement (see gates table below)
   - Excluded files: `.claude/hooks/*`, `.claude/session-state.json`, `vendor/`, `node_modules/`
3. **PostToolUse hooks** — validate commits (prefixes, length) and run `make manifest` post-push
4. **Pre-push gate** — verifies `phase_history` contains mandatory phases (verification, capture, finalize) before allowing push to protected paths

### session-state.json Schema

```jsonc
{
  "flow_type": "micro|light|debug|full|explore|null",
  "current_phase": "consult|brainstorming|planning|implementation|verification|capture|retrospective|finalize|null",
  "interaction_id": 0,              // Increment on scope change
  "phase_history": [],              // Append previous phase on each transition
  "last_work_summary": {            // Preserved by session-start.sh across daily resets
    "previous_date": "YYYY-MM-DD",
    "previous_branch": "...",
    "previous_flow": "full|debug|...",
    "previous_phase": "...",
    "recent_commits": ["..."],
    "merged_branches": ["..."],
    "last_execution_log": { "file": "...", "preview": "..." }
  },
  "evidence": {
    "interaction_id": 0,            // Must match top-level interaction_id
    "decisions_read": false,
    "logs_scanned": false,
    "user_turns": 0,
    "alternatives_proposed": false,
    "user_approved": false,
    "spec_path": null,
    "plan_path": null,
    "tests_written": 0,
    "tests_passed": null,
    "lint_clean": null,
    "execution_log_path": null,
    "branch_strategy": null,
    "root_cause_identified": false,
    "pattern_wide_search_done": false
  },
  "deviation": {
    "active": false,
    "reason": null,
    "skipped_phases": [],
    "return_to_phase": null,
    "acknowledged_by_user": false
  }
}
```

### How to Update session-state.json

Use `jq` for atomic updates. **When transitioning phases, ALWAYS append the previous phase to `phase_history`:**

```bash
# Transition from consult to brainstorming
jq '.phase_history += ["consult"] | .current_phase = "brainstorming"' .claude/session-state.json > /tmp/ss.json && mv /tmp/ss.json .claude/session-state.json

# Simple evidence update (no phase transition)
jq '.evidence.decisions_read = true' .claude/session-state.json > /tmp/ss.json && mv /tmp/ss.json .claude/session-state.json
```

---

## Gates by Flow and File Type

| Flow | `src/`, `tests/` | `specs/`, `plans/` | `docs/`, config | otros |
|------|-------------------|--------------------|--------------------|-------|
| **micro** | DENY (reclassify) | DENY (reclassify) | pass | pass |
| **light** | DENY (reclassify) | DENY (reclassify) | pass | pass |
| **explore** | DENY (reclassify) | DENY (reclassify) | pass | pass |
| **debug** | HARD (debug-validator) | pass | pass | pass |
| **full** | HARD (full validators) | HARD (phase validators) | SOFT | SOFT |

### Full-flow Gates (by file)

| To edit... | Requires phases completed | Gate |
|------------|--------------------------|------|
| `docs/superpowers/specs/*` | consult | HARD |
| `docs/superpowers/plans/*` | consult, brainstorming | HARD |
| `src/*`, `tests/*` | consult, brainstorming, planning | HARD |
| `docs/superpowers/execution-logs/*` | (self) | SOFT |
| `docs/decisions/*` | (self) | SOFT |

### Debug-flow Gates (code)

| To edit `src/*` or `tests/*` | Requires | Gate |
|------------------------------|----------|------|
| Step 1: Consult | `decisions_read` OR `logs_scanned` | HARD |
| Step 2: Root Cause | `root_cause_identified = true` | HARD |
| Step 3: Pattern-Wide | `pattern_wide_search_done = true` | HARD |

**HARD** = blocks edit (exit 2). **SOFT** = warning, allows continue (exit 1). **DENY** = blocks and asks to reclassify flow.

### Excluded Files

Never validated: `.claude/session-state.json`, `.claude/hooks/*`, `.claude/settings*`, `node_modules/`, `vendor/`, `.git/`.

---

## Validators — Evidence Required per Phase

| Phase | Required Evidence | Level |
|-------|------------------|-------|
| `consult` | `decisions_read` OR `logs_scanned` | HARD |
| `brainstorming` | `user_turns ≥ 1` (HARD) + SOFT if `< 3` + `alternatives_proposed` + `user_approved` + `spec_path` (file ≥500B) | MIXED |
| `planning` | `plan_path` (file ≥300B) | HARD |
| `implementation` | plan exists (HARD) + `tests_written > 0` (SOFT) | MIXED |
| `verification` | `tests_passed = true` + `lint_clean = true` | HARD |
| `capture` | `execution_log_path` exists | SOFT |
| `retrospective` | (update decision log) | SOFT |
| `finalize` | `branch_strategy` declared + knowledge module check | SOFT |
| `debug-code` | `decisions_read`/`logs_scanned` + `root_cause_identified` + `pattern_wide_search_done` | HARD |

---

## Deviation Mode

When you need to skip a phase (urgency, hotfix), activate deviation. **Requires user confirmation.**

```bash
jq '.deviation.active = true | .deviation.reason = "hotfix: ..." | .deviation.skipped_phases = ["brainstorming","planning"]' .claude/session-state.json > /tmp/ss.json && mv /tmp/ss.json .claude/session-state.json
```

The engine shows warnings but doesn't block.

---

## Automatic Session Context

The SessionStart hook generates context automatically at each session start. This is a guaranteed mechanism, not dependent on Claude remembering to consult anything.

### What the hook provides (no Claude action needed)

On session start/resume, `session-start.sh` outputs:
- Current branch, date, resume/new-day status
- Previous session info (date, flow, phase) from `last_work_summary`
- Last 10 commits
- `claude/*` branches merged to main
- Last execution log preview (first 6 lines)

**Claude MUST read this output before responding to the user's first message.**

### Manual consultation (fallback)

| When | What to consult |
|------|----------------|
| Before any code change (in full-flow) | `docs/decisions/log.md` |
| Don't know current branch | `git branch -v` |
| Task touches specific subsystem | Corresponding knowledge module |

---

## Automatic Status Line

Every response to the user MUST start with the workflow status line.

The PostToolUse hook `workflow-status-line.sh` generates `.claude/workflow-status-line.txt` after every tool call.

### Rules

1. **Read** `.claude/workflow-status-line.txt` at the start of composing each response
2. **Display** as the FIRST line, verbatim
3. **Full** (phase changed): show complete line
4. **Compact** (same phase): show only `📍 {flow} | {Phase} ({index}/{total})`
5. **Never skip** — even for short answers
6. **No file/empty:** show `📍 status unavailable`
7. **No flow declared:** show `📍 no flow declared`

### Format Examples

```
📍 full | Brainstorming (2/8) | ✅ consult → 🔄 brainstorm | Pendiente: planning, implementation, verification, capture, retrospective, finalize
📍 full | Brainstorming (2/8)
📍 micro | Responder
📍 debug | Root_cause (2/4) | ✅ consult → 🔄 root_cause | Pendiente: pattern_search, fix
📍 no flow declared
```

---

## Context Hygiene

Sessions degrade with length. Structured checkpoints preserve progress.

1. **Checkpoint at ~50 tool calls** or on compaction: commit + push + update session-state
2. **Large tasks (>8 steps):** consider splitting into separate sessions
3. **Post-compaction:** verify access to spec, plan, task state. Re-read if lost.
4. **Structured handoff:** when suggesting new session, document completed/in-progress tasks and decisions in session-state

---

## Feedback Capture

Every non-trivial interaction produces structured feedback data. This closes the learning loop.

### Execution Logs

After EACH code change or bug fix: `docs/superpowers/execution-logs/YYYY-MM-DD-<feature-name>.md`
Template: `docs/superpowers/templates/execution-log-template.md`

**Data per phase:**

| Phase | Required data |
|-------|--------------|
| Brainstorming | Alternatives, chosen approach + reason, complexity (S/M/L/XL), confidence |
| Planning | Task count, affected files, time estimate, risk |
| Implementation | Actual time, blockers, plan deviations, debugging episodes |
| Verification | Test results, lint, coverage delta |
| Retrospective | Estimate accuracy, what worked, what didn't, lessons |

### When to Capture

| Interaction type | Execution Log | Retrospective | Decision Log |
|-----------------|---------------|---------------|--------------|
| Informational | No | No | Only if gap found |
| Documentation | No | No | If non-trivial decision |
| Bug fix | Yes | Yes | If root cause reveals pattern |
| Code change | Yes | Yes | If design decision |

---

## Learning Loop

Double loop: immediate per-interaction + periodic analysis to update permanent guides.

### Immediate Loop (before each brainstorming)

1. Read `docs/decisions/log.md` — search keywords related to current task
2. Scan recent `docs/superpowers/execution-logs/` — lessons on similar topics
3. Scan `docs/superpowers/retrospectives/` — recent reviews
4. For route optimization features: `php bin/console app:learning:metrics --period=30d`
5. Declare: "Consulté decisiones pasadas: [found X relevant / nothing relevant]"

### Periodic Loop (monthly)

1. Collect: execution logs, learning metrics, decision log entries for period
2. Analyze: estimate accuracy, blocker frequency, decision outcomes
3. Produce: `docs/superpowers/retrospectives/YYYY-MM-review.md`
4. Act: update knowledge modules, propose CLAUDE.md updates, adjust calibration

---

## Harness Assumptions & Evolution

> "Every component in a harness encodes assumptions about model limitations worth stress-testing." — Anthropic, 2025

### Assumption Inventory

| Component | Assumption | Level | Last validated |
|---|---|---|---|
| Workflow engine HARD gates | Claude skips phases without enforcement | HARD | 2026-03-24 |
| Brainstorm `user_turns ≥ 1` + SOFT `< 3` | Claude may not converse enough | SOFT | 2026-03-24 |
| `session-state.json` granular evidence | External state needed cross-session | HARD | 2026-03-24 |
| Subagent output limits (300 lines) | Subagents produce excessive output | Docs | 2026-03-24 |
| Pre-Exploration Gate | Claude explores redundantly without manifest | Docs | 2026-03-24 |
| Scope Change Detection | Claude mixes tasks without detecting scope change | SOFT | 2026-03-24 |
| Atomic commits | Work is lost in long sessions | Docs | 2026-03-24 |

### Enforcement Levels

- **HARD** — Blocks (exit 2). Validated as necessary.
- **SOFT** — Warning (exit 1). In transition.
- **Docs** — Best practice, no mechanical enforcement.
- **Removed** — Obsolete.

### Evolution Model

```
HARD → (5 tasks, ≥90% compliance) → SOFT → (10 tasks, ≥95%) → Docs → Remove
```

**Review trigger:** Each base model change (e.g., Opus 4.6 → 5.0).

---

## Known Problems

### Subagent Infrastructure Failures

Subagents can fail with runtime errors like `undefined is not an object (evaluating 'H.includes')`. When this happens, ALL tools fail.

**Fix:** Don't retry same subagent. Execute task in main thread or launch a fresh subagent. If persistent, suggest user restart session.

### Error "tool_use ids must be unique" (API 400)

Client bug — duplicate tool_use IDs in conversation history. Caused by parallel tool calls or long conversations.

**Mitigation:** Frequent commits, TodoWrite state, atomic tasks, limit subagent depth.
**Recovery:** `/clear`, new session, check `git log` + `git status` before continuing.

### Error "assistant message prefill" (API 400)

Client bug — malformed message structure from context compression or corrupted resume history.

**Same mitigation and recovery** as tool_use ids error.

### Subagent Output Limits

Web environment has ~25,000 token read limit for subagent output.

**Rules for subagents:**
- Max 300 lines or 15,000 tokens
- Write extensive output to files, return summary with path
- Never include full source code — reference files and lines

**Rules for dispatching:**
- Include in EVERY subagent prompt: "Output must not exceed 200 lines. Write to file if needed."
- Explore agents: request specific findings, not code dumps
- Plan agents: concise plans with file paths, no full code inline
