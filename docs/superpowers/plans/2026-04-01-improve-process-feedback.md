# Plan: Improve Process Feedback

**Spec:** `docs/superpowers/specs/2026-04-01-improve-process-feedback-design.md`
**Branch:** `claude/improve-process-feedback-Tc6PZ`

## Phase 1 (v0): Working implementation

### Task 1: Update statusMessages in settings.json
- **File:** `.claude/settings.json`
- **Action:** Replace 6 statusMessage strings with improved Spanish versions
- **Verify:** Read file, confirm strings changed

### Task 2: Enrich workflow-engine.sh messages
- **File:** `.claude/hooks/workflow-engine.sh`
- **Action:** Update `deny()` and `warn()` call sites to include flow, phase, file class, missing phases, and unblock actions
- **Verify:** Run `bash .claude/hooks/test-workflow-engine.sh` if exists, otherwise manual review

### Task 3: Enrich pre-push-gate.sh messages
- **File:** `.claude/hooks/pre-push-gate.sh`
- **Action:** Change error accumulation to use checklist format (✅/❌) with actual values
- **Verify:** Manual review of message format

### Task 4: Enrich post-commit-validator.sh messages
- **File:** `.claude/hooks/post-commit-validator.sh`
- **Action:** Compact warning format with clear indicators
- **Verify:** Manual review

### Task 5: Create UserPromptSubmit hook script
- **File:** `.claude/hooks/user-prompt-state.sh` (new)
- **Action:** Script reads session-state.json, outputs compact workflow state block
- **Verify:** Run script manually, check output format

### Task 6: Register UserPromptSubmit hook in settings.json
- **File:** `.claude/settings.json`
- **Action:** Add `UserPromptSubmit` entry pointing to new script
- **Verify:** Read settings.json, confirm structure is valid JSON

### Task 7: Commit and push
- **Action:** Commit all changes, push to branch
- **Verify:** `git status` clean, push successful

## Phase 2 (Mature): Not needed
This is a config/hooks change — v0 is the final version. No refactoring phase required.
