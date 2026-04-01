# Spec: Improve Process Feedback

**Date:** 2026-04-01
**Type:** Enhancement (hooks/workflow engine)
**Branch:** `claude/improve-process-feedback-Tc6PZ`

## Problem

The workflow engine hooks provide minimal, generic feedback to the user and to Claude:
- `statusMessage` fields are vague ("Checking workflow compliance...")
- Blocking messages don't explain what's missing or how to fix it
- Claude has no automatic injection of workflow state per turn — it relies on reading files manually

## Existing Functionality Inventory

| Element | Decision | Justification |
|---------|----------|---------------|
| `settings.json` hooks config | Transform | Update statusMessage strings |
| `workflow-engine.sh` (PreToolUse) | Transform | Enrich deny/warn messages |
| `pre-push-gate.sh` (PreToolUse) | Transform | Enrich deny/warn messages |
| `post-commit-validator.sh` (PostToolUse) | Transform | Enrich warning messages |
| `post-push-validator.sh` (PostToolUse) | Include | No changes needed |
| `workflow-status-line.sh` (PostToolUse) | Include | No changes needed |
| `workflow-status.sh` (PostToolUse) | Include | No changes needed |
| `session-start.sh` (SessionStart) | Include | No changes needed |
| All validators in `validators/` | Include | No changes needed |

## Omission Decisions

| Element | Decision | Justification |
|---------|----------|---------------|
| `workflow-status-line.sh` | Omit | Already generates rich status, no changes needed |
| `workflow-status.md` generation | Omit | Not user-facing, works fine |
| Validator scripts | Omit | Their output is consumed by workflow-engine.sh which we improve |

## Design

### A. Better Static statusMessages (settings.json)

Update 6 statusMessage strings to be more descriptive in Spanish:

| Hook | Current | New |
|------|---------|-----|
| SessionStart | "Initializing session state..." | "Inicializando sesion..." |
| PreToolUse Edit\|Write | "Checking workflow compliance..." | "Verificando workflow gate..." |
| PreToolUse Bash | "Checking pre-push gates..." | "Verificando pre-push gate..." |
| PostToolUse all | "Updating status line..." | "Actualizando progreso del proceso..." |
| PostToolUse Bash (commit) | "Validating commit..." | "Validando formato de commit..." |
| PostToolUse Bash (push) | "Post-push tasks..." | "Ejecutando tareas post-push..." |

### B. Enriched Blocking/Warning Messages

**workflow-engine.sh changes:**
- `deny()` messages include: flow type, current phase, file being edited, what phases are missing, what actions are needed to unblock
- `warn()` messages include: same context but as advisory
- Format: `"[flow | phase (N/8)] mensaje | Falta: X, Y | Accion: Z"`

**pre-push-gate.sh changes:**
- Checklist format showing what passed and what failed
- Format: `"PUSH GATE [flow] | ✅ item | ❌ item (actual: value) | Accion: Z"`

**post-commit-validator.sh changes:**
- Compact format with clear indicators
- Format: `"COMMIT [prefix ok/warn] | [length ok/warn] | [N commits sin push] | [exec log ok/missing]"`

### C. UserPromptSubmit Hook (new)

New file: `.claude/hooks/user-prompt-state.sh`
New entry in `settings.json` under `hooks.UserPromptSubmit`.

The hook reads `session-state.json` and outputs a compact workflow state block to stdout.
Since UserPromptSubmit stdout is injected into Claude's context, this gives Claude
automatic awareness of the current workflow state on every user turn.

**Output format:**
```
── WORKFLOW STATE ──
Flow: full | Phase: brainstorming (2/8)
Evidence: decisions_read=Y logs_scanned=Y user_turns=1 alternatives=N approved=N spec=N
Next: propose alternatives, get approval, write spec, transition to planning
────────────────────
```

**For simple flows (micro/light/explore):**
```
── WORKFLOW STATE ──
Flow: micro | Phase: respond
────────────────────
```

**When no flow declared:**
```
── WORKFLOW STATE ──
Flow: not declared | Classify before proceeding
────────────────────
```

## Non-goals

- Changing validator logic (only their consumer messages)
- Adding new gates or enforcement
- Changing the workflow-status-line.txt format (already good)
