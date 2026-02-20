---
name: workflows:work
description: "Execute implementation with configurable parallelization modes and execution mode (agent-executes, human-guided, or hybrid)."
argument_hint: <feature-name> [--mode=<layers|stacks>] [--layer=<layer>] [--stack=<stack>] [--exec=auto|agent|human|hybrid] [--isolation=session|task]
---

# Multi-Agent Workflow: Work

Execute implementation with the compound engineering principle: make each unit of work easier.

## Flow Guard (prerequisite check)

Before executing, verify the flow has been followed:

```
PREREQUISITE CHECK:
  1. Does tasks.md exist for this feature?
     - NO: STOP. Run /workflows:plan first.

  2. Is planner status = COMPLETED in tasks.md?
     - NO (PENDING or IN_PROGRESS): STOP. Planning not finished. Run /workflows:plan.
     - NO (BLOCKED): STOP. Planning is blocked. Resolve blocker first.
     - YES: Continue to work.

  3. Does the role's status allow starting work?
     - PENDING: OK, start work (set to IN_PROGRESS)
     - IN_PROGRESS: OK, resume work from last checkpoint
     - COMPLETED: Work already done. Confirm re-work with user.
     - BLOCKED: Show blocker details, ask user how to proceed.

  If checks 1 or 2 fail, do NOT proceed. Plan first.
```

## Automatic Operations (built into this command)

The following are executed automatically as part of `/workflows:work`:
- **Git sync** (Step 2) -- pulls latest changes before starting
- **HITL checkpoint** (Step 4.5) -- human confirmation for high-risk tasks (migrations, auth, payments)
- **Solution Validation** (Step 4.6) -- validates approach before TDD cycle
- **TDD enforcement** (Step 5) -- Red-Green-Refactor cycle
- **SOLID verification** (Step 7) -- checks compliance at each checkpoint via solid-analyzer
- **Bounded Correction Protocol** (Step 6) -- auto-corrects with scale-adaptive limits
- **Checkpoint** (Step 7) -- saves progress + commits after each logical unit
- **Snapshot** -- triggered when context exceeds 70% capacity

You do NOT need to invoke these separately.

## Usage

```bash
# Default (sequential tasks from tasks.md)
/workflows:work user-auth

# By Layer (DDD)
/workflows:work user-auth --mode=layers --layer=domain
/workflows:work user-auth --mode=layers --layer=application
/workflows:work user-auth --mode=layers --layer=infrastructure

# By Stack (parallel backend/frontend)
/workflows:work user-auth --mode=stacks --stack=backend
/workflows:work user-auth --mode=stacks --stack=frontend
```

## Execution Mode Resolution

Before executing tasks, resolve the execution mode:

` ` `
1. READ core/providers.yaml → providers.execution_mode

2. IF "auto":
   ├── Is the task in a LOW trust area (auth/, security/, payment/)?
   │   YES → hybrid (agent generates, human reviews)
   │
   ├── Does the task have a "Reference" file in tasks.md?
   │   YES → agent-executes (pattern exists to follow)
   │
   └── OTHERWISE → agent-executes (default)

3. IF --exec flag passed → override (session only)
` ` `

### Agent Executes (default)

The agent generates code following the plan. For each task in `tasks.md`:

` ` `
TASK EXECUTION LOOP:
  1. READ task (acceptance criteria, SOLID requirements, reference file)
  2. READ reference file to learn existing pattern
  3. WRITE test FIRST (TDD Red) — Write tool creates test file
  4. RUN tests → confirm failure (test-runner)
  5. WRITE implementation following pattern — Write/Edit tools
  6. RUN tests (test-runner)
  7. IF fail → analyze + fix (BCP, max 10 iterations)
  8. CHECK SOLID + DIMENSIONAL PATTERNS (solid-analyzer --mode=verify) — must be COMPLIANT
     IF design.md has "## API Architecture Constraints Addressed":
       - Verify AC patterns present: AC-01 (anti-corruption), AC-02 (assembler), AC-03 (async), AC-04 (transformers)
       - "must" constraint absent in code → enter BCP correction loop
  9. FIX lint (lint-fixer)
  10. CHECKPOINT → update tasks.md
  11. → Next task
` ` `

### Human Guided

The agent presents each task with detailed instructions. The human implements. The agent verifies with test-runner and solid-analyzer.

### Hybrid

Same as agent-executes, but pauses before each checkpoint:
` ` `
After step 9 (lint fixed, tests passing):
  → Present generated code to human
  → Wait for: [Accept] [Modify] [Reject]
  → Accept: proceed to checkpoint
  → Modify: incorporate changes, re-run tests
  → Reject: regenerate with human feedback
` ` `

## Task Isolation Mode (Fresh Context per Task)

Resolve task isolation from `--isolation` flag or `fork_strategy` provider:

```
1. IF --isolation=task flag passed → per-task isolation
2. ELSE IF fork_strategy == "per-task" in providers.yaml → per-task isolation
3. ELSE → session isolation (default, all tasks in one context)
```

### Per-Task Isolation (`--isolation=task`)

Inspired by GSD's "fresh context per task" pattern. Each task in `tasks.md` executes in its own isolated subagent context, preventing context rot on long sessions.

```
PER-TASK ISOLATION PROTOCOL:

FOR each task in tasks.md:
  1. LAUNCH Task subagent (context: fork) with ONLY:
     - Role definition (implementer.md)
     - Task definition from tasks.md
     - Reference files listed in the task
     - design.md (for SOLID patterns to follow)
     → 200K tokens purely for this task, zero accumulated context

  2. Subagent executes:
     - TDD cycle (Red → Green → Refactor)
     - Bounded Correction Protocol (scale-adaptive limits)
     - SOLID verification

  3. Subagent returns summary:
     - Files created/modified
     - Tests: X passing, Y% coverage
     - SOLID: COMPLIANT/NEEDS_WORK (per-principle details)
     - Issues encountered (if any)

  4. Main agent:
     - Atomic git commit for this task
     - UPDATE tasks.md with task completion
     - Verify subagent output meets acceptance criteria
     - Launch next task with fresh context

BENEFITS:
  - No context degradation across tasks
  - Each task gets maximum available tokens
  - Individual task commits enable git bisect
  - Failed tasks don't pollute context for subsequent tasks

TRADE-OFFS:
  - Higher total token consumption (~30-50% more)
  - Subagent doesn't see changes from previous tasks (reads from disk)
  - Setup overhead per task (~5 seconds)

BEST FOR:
  - Features with 10+ tasks
  - Sessions expected to exceed 2 hours
  - When quality degradation is observed in later tasks
```

### Session Isolation (default, `--isolation=session`)

Standard behavior: all tasks execute in the same context window. The Bounded Correction Protocol, TDD, and checkpoints operate within a single session. Use context management thresholds from `providers.yaml` to manage context exhaustion.

---

## Parallelization Modes

### Mode: Layers (DDD)
```
Domain + Application + Infrastructure (can run parallel)
```

**Layers available**: `domain`, `application`, `infrastructure`

**Best for**:
- Complex business logic
- Backend-heavy features
- DDD architecture compliance

### Mode: Stacks
```
Backend complete + Frontend complete (parallel)
```

**Stacks available**: `backend`, `frontend`

**Best for**:
- Independent features
- Separate deployment pipelines
- Large features with clear boundaries

## Execution Flow

### Step 1: Load Context

```bash
# Load implementer role + mode-specific context
--mode=layers --layer=domain → Read: core/roles/implementer.md + core/rules/framework_rules.md
--mode=stacks --stack=backend → Read: core/roles/implementer.md (backend tasks)
--mode=stacks --stack=frontend → Read: core/roles/implementer.md (frontend tasks)
(default, no mode) → Read: core/roles/implementer.md (all tasks sequentially)
```

### Step 2: Git Sync

```bash
git pull origin feature/${FEATURE_ID} || git pull origin main
```

### Step 3: Load Feature Context

```bash
Read: openspec/changes/${FEATURE_ID}/tasks.md      # Tasks + Workflow State
Read: openspec/changes/${FEATURE_ID}/proposal.md    # Problem + success criteria
Read: openspec/changes/${FEATURE_ID}/design.md      # Solutions + SOLID patterns
```

### Step 4: Verify Prerequisites

**For Roles mode**:
- Check Planner status is COMPLETED
- If frontend stack: Check if backend API ready or mock needed

**For Layers mode**:
- Domain: Can start immediately
- Application: Domain should be COMPLETED (or mock)
- Infrastructure: Application should be COMPLETED

### Step 4.5: HITL Checkpoint for High-Risk Tasks

Before executing a task, check if it touches high-risk areas that require human confirmation:

```
HIGH-RISK TASK DETECTION:

IF task modifies ANY of:
  - Database migrations / schema changes
  - Authentication / authorization logic
  - Payment / billing code
  - CI/CD configuration files
  - Environment variables / secrets configuration
  - Infrastructure-as-code files
  - Public API contracts (breaking changes)

THEN:
  → Present task plan to user BEFORE executing
  → Show: what files will change, what tests will run, what could break
  → Wait for: [Proceed] [Modify approach] [Skip task]
  → Log user decision in tasks.md Decision Log

IF task is in a LOW trust area (auth/, security/, payment/) AND execution_mode == "auto":
  → Override to hybrid mode for this task only
```

This checkpoint catches destructive or irreversible changes before they happen, without slowing down routine implementation tasks.

### Step 4.6: Solution Validation (Pre-Implementation Check)

Before starting the TDD cycle for each task, validate the approach is sound:

```
SOLUTION VALIDATION (for each task in tasks.md):

1. REFERENCE CHECK: Does a reference file exist for this task?
   - YES: Read reference file. Confirm approach follows the same pattern.
   - NO: Check design.md for architectural guidance. Confirm alignment.

2. INTEGRATION CHECK: Will this conflict with completed checkpoints?
   - Read completed checkpoints in tasks.md
   - Verify interfaces match (DTO shapes, method signatures, API contracts)
   - If conflict detected → STOP. Consult planner before proceeding.

3. DECISION CHECK: Is approach consistent with DECISIONS.md?
   - Read DECISIONS.md for relevant architectural decisions
   - If approach contradicts a decision → STOP. Consult planner.

4. COMPLEXITY ASSESSMENT: Resolve max_iterations for this task
   - Read task complexity from tasks.md (or infer from scope)
   - Set max_iterations from providers.yaml correction_limits
   - simple: 5, moderate: 10, complex: 15

5. CONTRADICTION CHECK (CDP): Does the task's approach conflict with
   existing code, specs, or prior decisions?
   - Compare task requirements against openspec/specs/ baseline
   - Compare against constitution.md principles (if exists)
   - If contradiction detected → apply Contradiction Detection Protocol
     (framework_rules.md §12): stop, present both sides, ask user

If ALL checks pass → proceed to TDD (Step 5)
If check 5 (CDP) fails → apply CDP resolution (ask user, not planner)
If checks 1-4 fail → escalate to planner with specific conflict details
```

This step prevents wasting TDD iterations on an architecturally flawed approach.

### Step 5: Execute with TDD + SOLID

Execution behavior depends on the resolved execution mode (see Execution Mode Resolution above).

**In `agent-executes` mode** (default), the agent performs all steps autonomously following the Task Execution Loop. The TDD cycle is executed directly by the agent using Write, Edit, and tool invocations.

**In `human-guided` mode**, the agent presents each task with detailed instructions, acceptance criteria, and reference patterns. The human engineer performs the TDD cycle manually. The agent verifies results with test-runner and solid-analyzer after each task.

**In `hybrid` mode**, the agent executes autonomously but pauses before each checkpoint for human review (Accept / Modify / Reject).

Regardless of mode, follow the TDD cycle for each task, ensuring SOLID compliance:

```
1. 🔴 RED: Write test FIRST (must fail)
2. 🟢 GREEN: Write minimum code to pass
3. 🔵 REFACTOR: Improve while keeping tests green
4. ✅ SOLID: Verify code follows SOLID patterns from design.md
```

**SOLID Verification During Implementation**:

```bash
# After each logical unit, verify SOLID compliance
/workflow-skill:solid-analyzer --mode=verify --path=src/modified-path --design=design.md

# Must be COMPLIANT. If NEEDS_WORK, refactor before proceeding. If NON_COMPLIANT, enter BCP correction loop.
```

### Step 6: Bounded Correction Protocol + Diagnostic Escalation

Applies the BCP (see `core/rules/testing-rules.md` for full protocol) with these work-specific additions:

- **Scale-adaptive limits**: `MAX_ITERATIONS` from `providers.yaml` → `correction_limits` (simple:5, moderate:10, complex:15)
- **Diagnostic escalation**: After 3 consecutive identical errors → invoke `diagnostic-agent` (forked context) for root cause analysis. If diagnosis confidence is LOW → mark BLOCKED.
- **3 deviation types**: TYPE 1 (test failure → fix code, NEVER fix test), TYPE 2 (missing functionality → compare vs acceptance criteria), TYPE 3 (incomplete pattern → compare vs reference file)
- **Exit**: All verified → checkpoint. Max iterations exhausted → document blocker, mark BLOCKED.

**Deviation Types:**
- **TYPE 1 — Test Failure**: Tests fail → fix implementation
- **TYPE 2 — Missing Functionality**: Tests pass but acceptance criteria unmet → add implementation
- **TYPE 3 — Incomplete Pattern**: Implementation doesn't follow reference file → complete pattern

**Diagnostic escalation**: When the same error recurs 3 times, the `diagnostic-agent` (see `agents/workflow/diagnostic-agent.md`) runs in a forked context to analyze root cause and recommend a different approach. This prevents wasting iterations on the same failing fix.

### Step 7: Checkpoint (includes SOLID + Goal Verification)

After each logical unit:

```bash
# 1. Verify SOLID compliance
/workflow-skill:solid-analyzer --mode=verify --path=src/modified-path --design=design.md

# 2. Goal-Backward Verification (from GSD Verify)
# Verify against acceptance criteria, not just test results
```

**Goal-Backward Verification** (tests passing is necessary but NOT sufficient):

```
GOAL VERIFICATION (after tests pass):
  1. Read acceptance criteria for current task from tasks.md
  2. For each criterion:
     - AUTOMATED: If testable via command → run command → verify output
     - OBSERVABLE: If requires code inspection → read files → verify behavior exists
     - MANUAL: If requires human verification → document what to verify
  3. Score: X/Y criteria verified

  If all criteria verified → proceed to checkpoint
  If any criterion FAILED → re-enter correction loop (TYPE 2 deviation)
  If any criterion MANUAL → add PENDING_REVIEW to checkpoint notes
```

```bash
# 3. Only checkpoint if SOLID + goal verification pass
/multi-agent-workflow:checkpoint ${ROLE} ${FEATURE_ID} "Completed ${UNIT}"
```

**Checkpoint SOLID Requirements**:

Gate: no principle may be NON_COMPLIANT. NEEDS_WORK triggers refactor before proceeding.

| Checkpoint Type | Verification |
|-----------------|-------------|
| Domain layer | solid-analyzer --mode=verify --path=src/Domain --design=design.md |
| Application layer | solid-analyzer --mode=verify --path=src/Application --design=design.md |
| Infrastructure | solid-analyzer --mode=verify --path=src/Infrastructure --design=design.md |
| Full feature | solid-analyzer --mode=verify --path=src --design=design.md --scope=full |

## Stack-Specific Workflows

### Backend Workflow (Layers: domain → application → infrastructure)

```markdown
Checkpoint 1: Domain Layer
- Entities, Value Objects
- Verification: php bin/phpunit tests/Unit/Domain/
- Coverage: >80%
- **SOLID**: solid-analyzer --mode=verify verifies principles according to architecture-profile.yaml
- Run: /workflow-skill:solid-analyzer --mode=verify --path=src/Domain --design=design.md

Checkpoint 2: Application Layer
- Use Cases, DTOs
- Verification: php bin/phpunit tests/Unit/Application/
- Coverage: >80%
- **SOLID**: solid-analyzer --mode=verify verifies principles according to architecture-profile.yaml
- Run: /workflow-skill:solid-analyzer --mode=verify --path=src/Application --design=design.md

Checkpoint 3: Infrastructure Layer
- Repositories, Controllers
- Verification: php bin/phpunit tests/Integration/
- Schema validation
- **SOLID**: solid-analyzer --mode=verify verifies principles according to architecture-profile.yaml
- Run: /workflow-skill:solid-analyzer --mode=verify --path=src/Infrastructure --design=design.md

Checkpoint 4: API Endpoints
- REST endpoints
- Verification: curl tests, API contract validation
- **SOLID**: solid-analyzer --mode=verify verifies principles according to architecture-profile.yaml
- Run: /workflow-skill:solid-analyzer --mode=verify --path=src --design=design.md --scope=full
```

### Frontend Workflow

```markdown
Checkpoint 1: Component Structure
- TypeScript types, component skeleton
- Verification: npm run type-check

Checkpoint 2: Form Logic
- Validation, event handlers
- Verification: npm test -- ComponentName

Checkpoint 3: API Integration
- Real API or mocks
- Verification: Integration tests

Checkpoint 4: Responsive Design
- Mobile (375px), Tablet (768px), Desktop (1024px)
- Verification: Visual inspection

Checkpoint 5: Accessibility
- Lighthouse score >90
- Verification: npm run lighthouse
```

## State Updates

Update `tasks.md` at each checkpoint:

```markdown
## Implementer
**Status**: IN_PROGRESS
**Checkpoint**: Domain layer complete
**Timestamp**: 2026-01-16T14:30:00Z
**Tests**: 15/15 passing, 92% coverage
**SOLID**: COMPLIANT (SRP: ✓, OCP: ✓, LSP: N/A, ISP: ✓, DIP: ✓)
**Iterations**: 3

### Resume Information
- **Completed**: User entity, Email VO
- **Next Task**: CreateUserUseCase (BE-005)
- **Files to Read on Resume**:
  - tasks.md (Task BE-005)
  - src/Domain/Entity/User.php
- **SOLID**: COMPLIANT (SRP: ✓, OCP: ✓, LSP: N/A, ISP: ✓, DIP: ✓)
```

## Per-Task State Persistence (MANDATORY)

> **CRITICAL RULE**: Update `tasks.md` after completing EACH individual task, not just at checkpoints. If a session is interrupted between tasks, the resume point must be documented.

After completing each task in `tasks.md`:

```
PER-TASK UPDATE PROTOCOL:

1. Mark the task as COMPLETED in tasks.md task tracker
2. Record the timestamp (ISO 8601)
3. Update the "Resume Point" section with the NEXT task
4. WRITE tasks.md to disk immediately

This happens BEFORE the checkpoint (which includes git commit).
Even if no checkpoint is triggered, the state file is updated.
```

### Task-Level State Tracker (in tasks.md)

```markdown
## Implementer
**Status**: IN_PROGRESS
**Last Updated**: ${ISO_TIMESTAMP}

### Task Progress
| Task ID | Description | Status | Completed At |
|---------|-------------|--------|--------------|
| BE-001 | Create User Entity | COMPLETED | 2026-01-16T14:00:00Z |
| BE-002 | Create Email VO | COMPLETED | 2026-01-16T14:15:00Z |
| BE-003 | Create UserRepository Interface | IN_PROGRESS | - |
| BE-004 | CreateUserUseCase | PENDING | - |
| BE-005 | POST /api/users endpoint | PENDING | - |

### Resume Point
**Last Completed Task**: BE-002 (Create Email VO)
**Currently Working On**: BE-003 (Create UserRepository Interface)
**Next Task After Current**: BE-004 (CreateUserUseCase)
**Files to Read on Resume**:
  - openspec/changes/${FEATURE_ID}/tasks.md (Task BE-003)
  - src/Domain/Entity/User.php (reference for repository)
**Last Save**: 2026-01-16T14:15:00Z
```

### Interrupted Session Recovery

When resuming a session (detected by reading `tasks.md` with status `IN_PROGRESS`):

```
RESUME PROTOCOL:
1. Read tasks.md → identify Resume Point
2. Read the "Currently Working On" task from tasks.md
3. Read "Files to Read on Resume" list
4. Check git status for uncommitted changes
5. Continue from the identified task

DO NOT restart from the beginning.
DO NOT re-do completed tasks.
```

### State Update Timing

```
WHEN to update tasks.md:

1. After EACH task completion (even if not a checkpoint)
   → Update: task status, timestamp, resume point
   → Cost: ~5 seconds, prevents hours of lost work

2. At EACH checkpoint (logical unit boundary)
   → Update: full checkpoint details, SOLID compliance, tests
   → Also: git commit

3. Before ANY pause or break
   → Update: resume point with current context
   → This is the minimum for session recovery

ALWAYS include "Last Save" timestamp in tasks.md.
Format: ISO 8601 (e.g., 2026-01-16T14:30:00Z)
```

## Escape Hatch

If blocked after max iterations:

```markdown
## Blocker: [Task Name]

**Deviation type**: TYPE 1 | TYPE 2 | TYPE 3
**Iterations attempted**: [N] (Type 1: X, Type 2: Y, Type 3: Z)
**Last error/gap**: [exact error or unmet criterion]

**What was tried per deviation type**:
- TYPE 1 (test failures): [approaches tried]
- TYPE 2 (missing functionality): [gaps identified and attempted]
- TYPE 3 (incomplete patterns): [reference comparisons made]

**Root cause hypothesis**: [Why failing]

**Suggested alternatives**:
1. [Alternative 1]
2. [Alternative 2]

**Status**: BLOCKED - Needs Planner decision
```

## Verification Commands

Use project-detected test commands. Common patterns:

```bash
# Run tests (detected from project config)
/workflow-skill:test-runner

# Check SOLID compliance
/workflow-skill:solid-analyzer --path=src/modified-path

# Fix linting
/workflow-skill:lint-fixer
```

## Self-Review Before Completion (Reflection Pattern)

After ALL tasks are completed and before marking implementer status as `COMPLETED`, perform a self-review:

```
IMPLEMENTER SELF-REVIEW:

1. ACCEPTANCE CRITERIA AUDIT:
   - Re-read proposal.md success criteria
   - For each criterion: is there test evidence that it passes?
   - If any criterion lacks evidence → add test or document gap

2. DESIGN FIDELITY CHECK:
   - Re-read design.md SOLID patterns
   - For each pattern specified: was it implemented as designed?
   - If deviation found → either fix it or log reason in Decision Log

3. TEST COVERAGE SCAN:
   - Do test contract sketch scenarios (from specs.md) have matching tests?
   - Are edge cases from the sketch covered?
   - If gaps → add missing tests before marking COMPLETED

4. SCRATCHPAD REVIEW (if exists):
   - Read scratchpad.md for any unresolved hypotheses or TODOs
   - If unresolved items exist → either resolve or escalate to reviewer

IF self-review finds issues:
  → Fix them (within BCP limits)
  → Log what was caught: update tasks.md with "Self-review: [fix description]"

IF no issues found:
  → Mark implementer status as COMPLETED in tasks.md
  → Create final checkpoint with summary
```

This prevents shipping work that technically "passes tests" but doesn't match the design or acceptance criteria.

---

## Chunking Directive

Long implementation sessions should be chunked to preserve context quality:

- **Max tasks per session**: 8-10 tasks before considering a checkpoint + session restart
- **Max files open in context**: Follow provider thresholds (see `providers.yaml` → `context_management`)
- **Per-task output**: Keep checkpoint notes concise (status, files changed, tests, SOLID verdict)

If context capacity exceeds 70% (standard) or 85% (compaction-aware), trigger a session restart per the Session Restart Protocol in `framework_rules.md`.

---

## Error Recovery

- **Test runner unavailable**: Fall back to manual test commands detected from project config (package.json, composer.json, Makefile). If no test infrastructure exists, document as BLOCKED.
- **BCP exhausted (max iterations reached)**: Document blocker with deviation type classification. Mark task BLOCKED in tasks.md. Do NOT skip the task — proceed to next task only if independent.
- **Git conflict during checkpoint**: Stash changes, pull latest, attempt auto-merge. If conflict requires manual resolution, present diff to user.
- **Reference file missing**: If a task references a file that doesn't exist, check if a previous task should have created it. If dependency is unmet, mark task BLOCKED with dependency note.
- **Session interrupted mid-task**: On resume, read tasks.md Resume Point. Check git status for uncommitted changes. Continue from the identified task, do NOT restart completed tasks.

---

## Compound Effect

Good work execution compounds:
- TDD prevents regressions
- Checkpoints enable session resumption
- Documentation helps onboarding
- Tests become living specifications
