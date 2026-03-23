# Implementation Plan — Completion Gate via `phase_history[]`

**Date:** 2026-03-23
**Spec:** `docs/superpowers/specs/2026-03-23-completion-gate-phase-history-design.md`
**Branch:** `claude/review-workflow-compliance-yCsZC`

---

## Goal

Enhance `pre-push-gate.sh` to enforce completion of verification, capture, and finalize phases before pushing code to protected paths. Cross-validate evidence to prevent gaming.

## Architecture

- **Hook type:** PreToolUse on Bash (already wired in settings.json)
- **Trigger:** `git push` commands (excluding `--dry-run`)
- **Flow:** Detect protected paths → check phase_history + evidence → DENY or PASS

## File Structure

```
.claude/hooks/pre-push-gate.sh    # MODIFY — main implementation
CLAUDE.md                          # MODIFY — add phase_history population instruction
```

---

## Tasks

### Task 1: Rewrite `pre-push-gate.sh` with completion gate

**File:** `.claude/hooks/pre-push-gate.sh`

- [ ] 1.1 Keep existing structure (stdin parsing, deny/warn helpers, dry-run skip, state file check)
- [ ] 1.2 Add `has_protected_changes()` function:
  - Run `git diff --name-only origin/main...HEAD`
  - Check if any file matches protected path patterns: `backend/src/`, `backend/tests/`, `backend/templates/`, `backend/config/`, `backend/migrations/`, `backend/assets/`, `frontend/src/`, `ml-service/`, `docker/`, `scripts/`, `openspec/`
  - Return 0 (true) if any match, 1 (false) if none
- [ ] 1.3 Add `check_phase_completed()` function:
  - Takes phase name as argument
  - Checks if phase is in `phase_history[]` OR equals `current_phase`
  - Returns 0 (found) or 1 (not found)
- [ ] 1.4 Add evidence cross-validation for each HARD phase:
  - `verification`: `tests_passed = true` AND `lint_clean = true`
  - `capture`: `execution_log_path` is non-empty, file exists, file ≥ 500 bytes
  - `finalize`: `branch_strategy` is one of `merge|pr|keep|discard`
- [ ] 1.5 Add SOFT warning for `retrospective` (not in phase_history)
- [ ] 1.6 Handle deviation mode: if `deviation.active = true`, convert all DENY to WARN
- [ ] 1.7 Preserve existing flow_type filter (only `full` and `debug`)

### Task 2: Add `phase_history` population instruction to CLAUDE.md

**File:** `CLAUDE.md`

- [ ] 2.1 In the "Cómo actualizar session-state.json" section, add instruction:
  ```
  When transitioning phases, append the previous phase to phase_history:
  jq '.phase_history += ["previous_phase"] | .current_phase = "new_phase"' ...
  ```

### Task 3: Test the gate manually

- [ ] 3.1 Run `pre-push-gate.sh` with mock input simulating `git push` — verify it reads state correctly
- [ ] 3.2 Test with empty phase_history and protected changes → should DENY
- [ ] 3.3 Test with complete phase_history and valid evidence → should PASS
- [ ] 3.4 Test with docs-only changes → should PASS (no protected paths)
- [ ] 3.5 Test with deviation mode active → should WARN not DENY
