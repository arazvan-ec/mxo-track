# Plan: Auto-Evidence Detection

**Spec:** `docs/superpowers/specs/2026-04-01-auto-evidence-design.md`
**Approach:** A — `auto-evidence.sh` + enhance `user-prompt-state.sh`

## Phase 1 (v0): Working auto-detection

### Task 1: Create `auto-evidence.sh`
- New file: `.claude/hooks/auto-evidence.sh`
- Read `CLAUDE_TOOL_NAME` and `CLAUDE_TOOL_INPUT_*` env vars
- Detect evidence patterns (Read → decisions/logs, Write/Edit → spec/plan/tests, Bash → phpunit/lint)
- Update session-state.json via jq
- Always exit 0
- **Test:** Manual — trigger each detection rule, verify session-state updates

### Task 2: Enhance `user-prompt-state.sh` with auto user_turns
- During brainstorming phase, auto-increment `evidence.user_turns`
- Guard: only when `flow_type == "full"` and `current_phase == "brainstorming"`
- **Test:** Manual — send prompts during brainstorming, verify increment

### Task 3: Register hook in `settings.json`
- Add `auto-evidence.sh` as FIRST PostToolUse hook (before workflow-status-line.sh)
- Matcher: "" (all tools), timeout: 2
- **Test:** Verify hook fires and status-line reflects auto-detected evidence

### Task 4: Add tests to `test-status-line.sh`
- Test auto-evidence detection for each rule
- Test that status-line shows rich evidence after auto-detection
- **Test:** Run `test-status-line.sh`, all pass

### Task 5: Verify end-to-end + commit
- Verify: Read decisions/log.md → decisions_read auto-set
- Verify: Write spec → spec_path auto-set
- Verify: user_turns increments during brainstorming
- TypeScript N/A (bash only)
- Commit and push

## Phase 2 (Mature): Deferred
- Consider deduplication for tests_written (track seen files)
- Consider Bash output parsing for more granular test results
