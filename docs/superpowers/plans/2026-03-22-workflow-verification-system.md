# Implementation Plan: Workflow Verification System

**Spec:** `docs/superpowers/specs/2026-03-22-workflow-verification-system-design.md`
**Goal:** Replace monolithic hooks with a composable workflow engine + phase validators
**Complexity:** L (12 files, migration of 5 existing hooks)

## File Structure

```
.claude/hooks/
├── session-start.sh                    # MODIFY — new state model
├── workflow-engine.sh                  # CREATE — central engine
├── validators/
│   ├── consult-validator.sh            # CREATE
│   ├── brainstorm-validator.sh         # CREATE
│   ├── planning-validator.sh           # CREATE
│   ├── implementation-validator.sh     # CREATE (migrates tdd-gate.sh)
│   ├── verification-validator.sh       # CREATE
│   ├── capture-validator.sh            # CREATE
│   ├── retrospective-validator.sh      # CREATE
│   └── finalize-validator.sh           # CREATE
├── post-commit-validator.sh            # CREATE (merges commit-msg-lint + post-commit-reminder)
├── post-push-validator.sh              # CREATE (migrates manifest-auto-run)
├── workflow-status.sh                  # CREATE
├── test-workflow-engine.sh             # CREATE (replaces test-self-gating.sh)
├── full-flow-gate.sh                   # KEEP (deprecated, renamed to full-flow-gate.sh.bak after migration)
├── tdd-gate.sh                         # KEEP (deprecated, renamed after migration)
├── commit-msg-lint.sh                  # KEEP (deprecated after migration)
├── post-commit-reminder.sh             # KEEP (deprecated after migration)
└── manifest-auto-run.sh               # KEEP (deprecated after migration)
.claude/settings.json                   # MODIFY — new hook wiring
backend/Makefile                        # MODIFY — new targets
```

## Tasks

### Task 1: Update session-start.sh with new state model
- [ ] Modify `/home/user/mxo-track/.claude/hooks/session-start.sh`
- [ ] Replace the JSON template with the new model (session_date, flow_type, current_phase, interaction_classification, phase_history, evidence, deviation)
- [ ] Keep the same-day resume logic
- [ ] Verify: `bash .claude/hooks/session-start.sh` creates valid JSON

### Task 2: Create validators directory and phase validators
- [ ] `mkdir -p .claude/hooks/validators`
- [ ] Create `consult-validator.sh` — checks `evidence.decisions_read || evidence.logs_scanned`; exit 1 (warn) if not
- [ ] Create `brainstorm-validator.sh` — checks user_turns ≥ 3, alternatives_proposed, user_approved, spec ≥500B; exit 2 (block) if not
- [ ] Create `planning-validator.sh` — checks plan_path exists and contains `- [ ]`; exit 2 if not
- [ ] Create `implementation-validator.sh` — for full-flow: requires plan; checks tests_written > 0 (warn); migrates TDD check from tdd-gate.sh
- [ ] Create `verification-validator.sh` — checks tests_passed AND lint_clean; exit 2 if not
- [ ] Create `capture-validator.sh` — checks execution_log_path exists; exit 1 (warn) if not
- [ ] Create `retrospective-validator.sh` — always passes (gate suave, exit 1 warning)
- [ ] Create `finalize-validator.sh` — checks branch_strategy declared; exit 1 (warn) if not
- [ ] `chmod +x` all validators

### Task 3: Create workflow-engine.sh
- [ ] Create `/home/user/mxo-track/.claude/hooks/workflow-engine.sh`
- [ ] Implement: read stdin (tool input), parse file_path
- [ ] Implement: load session-state.json, check flow_type declared
- [ ] Implement: deviation warning if active
- [ ] Implement: determine required phase from file path mapping
- [ ] Implement: invoke appropriate validator
- [ ] Implement: output deny JSON for hard gates, systemMessage for soft gates
- [ ] `chmod +x`

### Task 4: Create post-commit-validator.sh
- [ ] Create `/home/user/mxo-track/.claude/hooks/post-commit-validator.sh`
- [ ] Merge logic from `commit-msg-lint.sh`: validate prefix format, message length, generic patterns
- [ ] Merge logic from `post-commit-reminder.sh`: remind about execution logs
- [ ] Add: unpushed commits counter warning (>3 commits)
- [ ] `chmod +x`

### Task 5: Create post-push-validator.sh
- [ ] Create `/home/user/mxo-track/.claude/hooks/post-push-validator.sh`
- [ ] Migrate logic from `manifest-auto-run.sh`: run make manifest, auto-commit if changed
- [ ] Add: generate workflow-status.md after push
- [ ] `chmod +x`

### Task 6: Create workflow-status.sh
- [ ] Create `/home/user/mxo-track/.claude/hooks/workflow-status.sh`
- [ ] Read session-state.json, generate `.claude/workflow-status.md`
- [ ] Show: flow type, current phase, deviation status, phase progress table
- [ ] `chmod +x`
- [ ] Ensure `.claude/workflow-status.md` is in `.gitignore`

### Task 7: Update settings.json
- [ ] Replace PreToolUse hooks (full-flow-gate + tdd-gate) with workflow-engine.sh
- [ ] Replace PostToolUse Bash hooks (commit-msg-lint + post-commit-reminder + manifest-auto-run) with post-commit-validator.sh + post-push-validator.sh
- [ ] Keep: SessionStart hook, PostToolUse Write|Edit plan-copy hook
- [ ] Verify JSON is valid

### Task 8: Update Makefile
- [ ] Add `workflow-status` target
- [ ] Add `workflow-reset` target
- [ ] Add `hooks-health` target

### Task 9: Write test suite
- [ ] Create `test-workflow-engine.sh` replacing `test-self-gating.sh`
- [ ] Test: flow_type not declared → block on src/ edits
- [ ] Test: deviation active → warning
- [ ] Test: each validator gate (brainstorm, planning, implementation, verification)
- [ ] Test: soft gates emit warnings not blocks
- [ ] Test: non-src files bypass engine
- [ ] Test: micro/light flows skip validators
- [ ] Run tests, verify all pass

### Task 10: Deprecate old hooks
- [ ] Rename old hooks with `.bak` suffix (keep as reference)
- [ ] Update `.gitignore` if needed
- [ ] Final push
