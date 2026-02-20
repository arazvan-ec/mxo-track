---
name: checkpoint
description: "Creates blocking checkpoints with auto-correction protocol (BCP) for quality-gated development."
hooks:
  PreToolUse:
    - matcher: Write
      command: "echo '[checkpoint] Updating state file...'"
  Stop:
    - command: "echo '[checkpoint] Checkpoint saved to tasks.md'"
---

# Checkpoint Skill

Create blocking checkpoints with auto-correction loops for quality-gated development.

## What This Skill Does

- Validates that tests pass before allowing progress
- Implements the Bounded Correction Protocol (3 deviation types, scale-adaptive limits)
- **Goal-Backward Verification**: Verifies against acceptance criteria, not just test results
- Documents progress for session resumption
- Manages context window through strategic stopping points
- Provides escape hatch after max iterations

## The Bounded Correction Protocol

Instead of pushing forward blindly, detect and correct three types of deviations:

```
while (tests_failing OR deviation_detected) and iterations < max_iterations:
    CLASSIFY deviation:
      TYPE 1 (test failure):    analyze error → fix implementation
      TYPE 2 (missing feature): compare vs acceptance criteria → add implementation
      TYPE 3 (incomplete pattern): compare vs reference → complete pattern

    Run verification (tests + acceptance criteria check)
    iterations++

if all_verified:
    → Checkpoint complete
elif iterations >= max_iterations:
    → Document blocker with deviation type breakdown
    → Mark as BLOCKED
    → Wait for help
```

**Deviation Types:**
- **TYPE 1 — Test Failure**: Tests fail with errors → fix implementation (NEVER the test)
- **TYPE 2 — Missing Functionality**: Tests pass but acceptance criteria unmet → add missing code
- **TYPE 3 — Incomplete Pattern**: Doesn't match reference file → complete the pattern

## Goal-Backward Verification

After the correction loop completes (tests pass), verify against the task's acceptance criteria:

```
GOAL VERIFICATION:
  1. Read acceptance criteria for current task from tasks.md
  2. For each criterion:
     AUTOMATED: testable via command → run → verify output
     OBSERVABLE: requires code inspection → grep/read files → verify behavior
     MANUAL: requires human verification → document → flag PENDING_REVIEW
  3. Score: X/Y criteria verified

  all verified      → checkpoint COMPLETE
  criterion FAILED  → re-enter correction loop (TYPE 2: missing functionality)
  criterion MANUAL  → checkpoint PENDING_REVIEW (add to tasks.md notes)
```

**Why this matters**: Tests can pass while the feature is still incomplete. A test suite might verify that a function exists but not that it handles all acceptance criteria. Goal-backward verification catches these gaps.

## Adversarial Self-Review (Checkpoint Prerequisite)

Before marking a checkpoint complete, the implementing agent MUST identify at least 1 potential issue:

```
ADVERSARIAL SELF-REVIEW:
  1. Review your own implementation with a critical eye
  2. Identify AT LEAST 1 of:
     - A potential edge case not covered by tests
     - A code smell or SOLID violation (even minor)
     - A performance concern or potential optimization
     - A security consideration
  3. Document finding in checkpoint notes
  4. If CRITICAL → fix before checkpoint
  5. If MINOR → document for future improvement
  6. If ZERO findings → review again. Zero findings on real code is a red flag.
```

**Checkpoint notes must include**: At least 1 self-review finding. This prevents rubber-stamp checkpoints and ensures continuous quality awareness.

## When to Use

- **Completing a logical unit**: Domain layer, component, endpoint
- **After multiple fix iterations**: Tests finally passing
- **Context feels heavy**: Responses slowing down (check provider thresholds in `core/providers.yaml`)
- **Before breaks**: End of session, lunch, etc.
- **Time-based**: Every 1-2 hours (standard provider) or every 2-4 hours (advanced provider)

## How to Use

### Via Slash Command

```
/multi-agent-workflow:checkpoint <role> <feature> "<message>"
```

Example:
```
/multi-agent-workflow:checkpoint backend user-auth "Domain layer complete"
```

### Checkpoint Document Format

The checkpoint updates `tasks.md`:

```markdown
## Backend Engineer
**Status**: IN_PROGRESS
**Checkpoint**: Domain Layer Complete
**Timestamp**: 2026-01-16T14:30:00Z
**Tests**: 15/15 passing, 92% coverage
**Iterations**: 3 (took 3 attempts to pass)

### Resume Information
- **Completed**: User entity, Email VO, Password VO, Repository interface
- **Next Task**: CreateUserUseCase (Task BE-005)
- **Files to Read**:
  - implementer.md (TDD section)
  - tasks.md (Task BE-005)
  - src/Domain/Entity/User.php (reference)
```

## Checkpoint Types by Concern

### Implementation Checkpoints
1. **Domain Layer**: Entities, Value Objects, Domain Services
2. **Application Layer**: Use Cases, DTOs
3. **Infrastructure Layer**: Repositories, Controllers, Adapters
4. **API Endpoints**: REST/GraphQL endpoints with tests
5. **UI Components**: Component structure, forms, integration
6. **Accessibility**: Score >90

### Review Checkpoints
1. **API Testing**: All endpoints tested
2. **UI Testing**: All flows verified
3. **Test Execution**: All suites passing
4. **Acceptance Validation**: All criteria checked

## Escape Hatch

If stuck after 10 iterations:

```markdown
## Blocker: <Task Name>

**Checkpoint**: <checkpoint name>
**Iterations attempted**: 10
**Last error**: <exact error message>

**What was tried**:
1. <Approach 1> → <Result>
2. <Approach 2> → <Result>
...

**Root cause hypothesis**: <Why you think it's failing>

**Suggested alternatives**:
1. <Alternative 1>
2. <Alternative 2>

**Status**: BLOCKED - Needs Planner decision
```

## Context Management

Checkpoints help manage Claude's context window:

- **Signal to restart**: If context feels "heavy", checkpoint and start fresh
- **Clean sessions**: New session loads only checkpoint state
- **Focused work**: Each session handles one checkpoint group
