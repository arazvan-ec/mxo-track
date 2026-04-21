---
type: bugfix
tags: []
files_touched: []
patterns: []
outcome: null
outcome_verified_at: null
regressions_later: []
pr_number: null
estimated_lines: null
actual_lines: null
duration_minutes: null
consulted_in_future: []
---

# Execution Log — 2026-04-07 — Repository & Performance Improvements

**Type:** bug fix + performance
**Branch:** `claude/analyze-repo-improvements-vqgSV`

## Brainstorming
- Explored full codebase via subagent, found 4 findings (N+1, catch scope, test coverage, panel duplication)
- Discarded 2 findings: panel header extraction (forced abstraction) and controller test coverage (separate initiative)
- Identified false positives in multi-tenancy bypass reports (CustomerScopedEntityInterface handles it)

## Planning
- 3 tasks: AlertService N+1 fix, 10 standard repo catches, 4 infrastructure repo catches
- All parallelizable

## Implementation
- AlertService: replaced N+1 loop with single DQL LEFT JOIN (Vehicle ↔ VehicleLastPosition)
- 14 repositories: narrowed `catch (\Throwable)` → `catch (\InvalidArgumentException)` in findOneByPublicId()
- 15 files modified total

## Verification
- PHP lint: 15/15 files clean
- Tests: 602 tests, 0 new failures (11 pre-existing failures unrelated to changes)

## Retrospective
- Workflow enforcement hooks have a bug: phase-transition-controller reverts user_approved on read-only jq commands that contain the string "user_approved". The grep check at line 93 doesn't distinguish reads from writes.
- UserPromptSubmit hook's user_prompt detection may not work in Claude Code web environment (user_turns increments but approval pattern matching doesn't fire).
- Both issues are worth fixing in a separate session.

## Lessons
- Always verify multi-tenancy claims against CustomerScopedEntityInterface before flagging as security issue
- The catch(\Throwable) pattern in repositories is a codebase-wide smell worth tracking
