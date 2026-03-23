# Design Spec — Completion Gate via `phase_history[]` + Evidence Cross-Validation

**Date:** 2026-03-23
**Type:** Enhancement to workflow enforcement
**Branch:** `claude/review-workflow-compliance-yCsZC`
**Bounded context:** Pragmático (tooling/hooks, not domain code)

---

## Problem

The current pre-push gate only checks `tests_passed` (HARD) and execution log existence (SOFT). Claude can push code without having completed capture, finalize, or retrospective phases. The verification, capture, and finalize validators exist but only run during Edit/Write — they don't gate pushes.

## Chosen Approach: C — Completion Gate with `phase_history[]` + Evidence Cross-Validation

### Concept

1. Each time Claude transitions `current_phase`, the previous phase is appended to `phase_history[]`
2. The pre-push gate checks that `phase_history` contains all required phases AND that the corresponding evidence is real (files exist, fields populated)
3. The gate only activates when the push contains changes to protected paths

### Protected Paths (gate activates)

Any file under these directories triggers the completion gate:

| Path | Content |
|------|---------|
| `backend/src/` | PHP production code |
| `backend/tests/` | Backend tests |
| `backend/templates/` | Twig templates |
| `backend/config/` | Symfony config |
| `backend/migrations/` | DB migrations |
| `backend/assets/` | Backend assets |
| `frontend/src/` | Frontend production code |
| `ml-service/` | ML service |
| `docker/` | Docker config |
| `scripts/` | Operations scripts |
| `openspec/` | OpenAPI specs |

### Excluded Paths (push freely)

| Path | Reason |
|------|--------|
| `docs/` | Documentation, specs, plans, logs |
| `.claude/` | Hooks, session state |

### Phase Requirements for Push

| Phase | Gate Level | Evidence Cross-Validation |
|-------|-----------|--------------------------|
| `verification` | **HARD** (blocks push) | `tests_passed = true` AND `lint_clean = true` |
| `capture` | **HARD** (blocks push) | `execution_log_path` points to real file ≥ 500 bytes |
| `finalize` | **HARD** (blocks push) | `branch_strategy` is not null/empty |
| `retrospective` | **SOFT** (warning only) | Entry exists in decision log (best effort) |

### Anti-Gaming Measures

The gate does NOT just check `phase_history`. It cross-validates:

1. **Phase presence:** The phase name must be in `phase_history[]` OR be the `current_phase`
2. **Evidence reality:** The corresponding evidence fields must be truthful:
   - `execution_log_path` → file must exist AND be ≥ 500 bytes (not just a template stub)
   - `tests_passed` / `lint_clean` → must be `true` (boolean, not string "null")
   - `branch_strategy` → must be one of: `merge`, `pr`, `keep`, `discard`

### Detection of Protected Path Changes

The gate detects protected changes by comparing the current branch against `origin/main`:

```bash
git diff --name-only origin/main...HEAD
```

Then checks if any file matches a protected path pattern. If no protected files changed, the gate passes silently.

### Flow Scope

The gate applies to `full` and `debug` flows only (matching current behavior). For `micro`, `light`, `explore` — those flows can't edit protected code anyway (workflow-engine denies it), so there's nothing to gate on push.

### Deviation Mode

If `deviation.active = true`, the gate shows warnings but does NOT block. This preserves the existing escape hatch for hotfixes.

---

## Existing Functionality Inventory

| Element | Current State | Decision |
|---------|--------------|----------|
| `pre-push-gate.sh` — tests_passed HARD gate | Checks `tests_passed` on push for full/debug | **Transform** — Absorb into new comprehensive gate |
| `pre-push-gate.sh` — execution log SOFT warning | Checks execution log existence loosely | **Transform** — Upgrade to HARD with ≥500B validation |
| `capture-validator.sh` — SOFT (exit 1) | Only runs on Edit/Write of execution-log files | **Include** — Keep as-is for Edit/Write; push gate does its own check |
| `finalize-validator.sh` — SOFT (exit 1) | Only runs on Edit/Write context | **Include** — Keep as-is; push gate does its own check |
| `verification-validator.sh` — HARD (exit 2) | Runs on Edit/Write of code files | **Include** — Keep as-is; push gate mirrors the check |
| `post-push-validator.sh` — manifest + status | Runs after push for manifest auto-update | **Include** — No changes |
| `workflow-engine.sh` — central routing | Routes to validators on Edit/Write | **Include** — No changes |
| `session-state.json` — `phase_history[]` | Exists but always empty | **Transform** — Claude must populate it on phase transitions |

## Omission Decisions

No omissions — all inventory items addressed.

---

## Files to Modify

| File | Change | Risk |
|------|--------|------|
| `.claude/hooks/pre-push-gate.sh` | Rewrite: add protected path detection, phase_history + evidence cross-validation | Medium — must not break existing push flow |
| `CLAUDE.md` | Add instruction for Claude to populate `phase_history[]` on phase transitions | Low |

## Files NOT Modified

| File | Reason |
|------|--------|
| `capture-validator.sh` | Keeps SOFT for Edit/Write context; push gate handles HARD enforcement separately |
| `finalize-validator.sh` | Same — keeps SOFT for Edit/Write; push gate handles HARD |
| `workflow-engine.sh` | No changes needed — already routes correctly |
| `session-state.json` | Structure unchanged — `phase_history[]` already exists |

---

## Behavioral Examples

### Example 1: Push after completing all phases
```
phase_history: ["consult", "brainstorming", "planning", "implementation", "verification", "capture", "retrospective"]
current_phase: "finalize"
evidence: { tests_passed: true, lint_clean: true, execution_log_path: "docs/.../log.md" (exists, 1200B), branch_strategy: "pr" }
→ PASS
```

### Example 2: Push skipping capture
```
phase_history: ["consult", "brainstorming", "planning", "implementation", "verification"]
current_phase: "verification"
evidence: { tests_passed: true, lint_clean: true, execution_log_path: null }
→ DENY: "Fase 'capture' no completada. Crea execution log antes de pushear."
```

### Example 3: Push with fake execution log (stub)
```
evidence: { execution_log_path: "docs/.../log.md" (exists but 150B — just template header) }
→ DENY: "Execution log existe pero es < 500 bytes. Completa el log antes de pushear."
```

### Example 4: Push with no protected files changed
```
git diff origin/main...HEAD → only docs/ files
→ PASS (no protected paths)
```

### Example 5: Push in deviation mode
```
deviation.active: true
Missing phases in phase_history
→ WARN (not block): "Deviation activa: fases [capture, finalize] no completadas."
```
