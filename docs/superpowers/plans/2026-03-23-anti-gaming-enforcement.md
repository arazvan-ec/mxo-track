# Plan — Anti-Gaming Workflow Enforcement

**Spec:** `docs/superpowers/specs/2026-03-23-anti-gaming-enforcement-design.md`
**Goal:** Make workflow enforcement hooks verify real artifacts instead of trusting boolean flags
**Architecture:** Bash hooks in `.claude/hooks/` and `.claude/hooks/validators/`

## File Structure

```
.claude/hooks/
├── validators/
│   ├── implementation-validator.sh  # MODIFY — anti-gaming for tests_written
│   └── debug-validator.sh           # MODIFY — require textual descriptions
├── pre-push-gate.sh                 # CREATE — block push without tests/exec-log
├── tdd-gate.sh.bak                  # DELETE
├── full-flow-gate.sh.bak            # DELETE
├── commit-msg-lint.sh.bak           # DELETE
├── manifest-auto-run.sh.bak         # DELETE
└── post-commit-reminder.sh.bak      # DELETE
.claude/settings.json                # MODIFY — add PreToolUse Bash hook
```

## Tasks

### Task 1: Modify implementation-validator.sh — anti-gaming for tests_written

**File:** `.claude/hooks/validators/implementation-validator.sh`

Add a NEW check after the existing TDD logic: when `tests_written > 0`, cross-reference against real git artifacts.

- Check git diff (staged + unstaged) AND `git log` for test file changes in recent commits on current branch
- If `tests_written > 0` but zero real test files found in git → BLOCK with "ANTI-GAMING" contradiction message
- Keep existing working-tree TDD check as-is for `tests_written == 0` case

- [ ] Modify implementation-validator.sh
- [ ] Test: `tests_written=5`, no test changes → BLOCK
- [ ] Test: `tests_written=1`, real test file in diff → PASS

### Task 2: Modify debug-validator.sh — require textual evidence

**File:** `.claude/hooks/validators/debug-validator.sh`

Change from boolean-only to boolean + text description:

- Gate 2: require `evidence.root_cause_description` (string, min 20 chars) in addition to `root_cause_identified = true`
- Gate 3: require `evidence.pattern_wide_description` (string, min 20 chars) in addition to `pattern_wide_search_done = true`

- [ ] Modify debug-validator.sh
- [ ] Test: boolean true, no description → BLOCK
- [ ] Test: boolean true, description "x" (too short) → BLOCK
- [ ] Test: boolean true, description ≥20 chars → PASS

### Task 3: Create pre-push-gate.sh

**File:** `.claude/hooks/pre-push-gate.sh` (NEW)

PreToolUse hook on Bash. Detects `git push` commands and gates them:

1. Parse stdin JSON for `tool_input.command`
2. Only activate on commands containing `git push` (skip `--dry-run`)
3. Read session-state.json
4. For `full` or `debug` flow_type:
   - `tests_passed != true` → DENY
   - No execution log for today → WARN (systemMessage)
5. All other cases → pass (exit 0)

- [ ] Create pre-push-gate.sh
- [ ] chmod +x

### Task 4: Modify settings.json — register pre-push-gate

**File:** `.claude/settings.json`

Add new PreToolUse entry with matcher `Bash` for pre-push-gate.sh.

- [ ] Modify settings.json

### Task 5: Delete .bak files

Delete 5 superseded files:
- `.claude/hooks/tdd-gate.sh.bak`
- `.claude/hooks/full-flow-gate.sh.bak`
- `.claude/hooks/commit-msg-lint.sh.bak`
- `.claude/hooks/manifest-auto-run.sh.bak`
- `.claude/hooks/post-commit-reminder.sh.bak`

- [ ] Delete files
- [ ] Verify no references in settings.json

### Task 6: End-to-end verification

- [ ] Run test-workflow-engine.sh
- [ ] Manual verification of each new gate
