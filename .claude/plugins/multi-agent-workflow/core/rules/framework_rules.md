# Framework Rules - Multi-Agent Workflow

**Framework Version**: 3.0.0
**Last Updated**: 2026-02-12

---

## Purpose

These are the fundamental operational rules of the Multi-Agent Workflow framework. They apply to all projects using this plugin. Project-specific rules go in `.ai/project/rules/`.

For additional scoped rules see: `testing-rules.md`, `security-rules.md`, `git-rules.md` in this directory.

---

## Core Principles

### 1. Route Before Acting

Every interaction passes through the workflow router before work begins. See `CLAUDE.md` for the routing protocol and `core/docs/ROUTING_REFERENCE.md` for question templates.

**Exception — Quick Mode**: `/workflows:quick` can be invoked directly for simple tasks (≤3 files, no architecture impact, no sensitive paths). Quick Mode performs its own inline assessment and can escalate to the full workflow if the task is more complex than expected.

**Human-in-the-Loop Checkpoints**: User confirmation is required at these transition points:
- **Route → Plan**: After routing classification, before starting planning
- **Plan Phase 2 → Phase 3**: After specs are defined, before design begins (see plan.md HITL Checkpoint)
- **Plan completion**: Before marking planner as COMPLETED (part of Completeness Verification)
- **Work → Review**: Automatic (review prerequisite is work COMPLETED)
- **Review → Compound**: Automatic (compound prerequisite is QA APPROVED)

### 2. Karpathy Principles — Apply to All Work

Four principles that prevent common AI failure modes. Apply them at every stage:

- **Think Before Coding**: State assumptions and clarify ambiguities before writing code
- **Simplicity First**: Implement only what is explicitly requested
- **Surgical Changes**: Touch only the code essential to the task
- **Goal-Driven Execution**: Transform vague requests into testable success criteria

See `core/docs/KARPATHY_PRINCIPLES.md` for detailed guidance with examples.

### 3. Explicit Context — No Implicit Memory

All shared knowledge lives in files. Never assume implicit context.

```
Avoid: "Remember we said earlier that..."
Prefer: "Read `openspec/changes/FEATURE_X/tasks.md` Workflow State"
```

### 4. Immutable Roles

One Claude instance = one fixed role during the entire session.

- Don't switch from Backend to Frontend mid-task
- Don't implement code if you're QA
- Don't make design decisions if you're Backend/Frontend

### 5. Execution Mode Awareness

The agent's execution behavior depends on the resolved execution_mode provider:

- **agent-executes**: Generate code following patterns, run tests, iterate autonomously
- **human-guided**: Create instructions, human implements, agent verifies
- **hybrid**: Generate code, pause for human review at checkpoints

Resolve execution_mode from `core/providers.yaml` before starting any task. In `auto` mode, low-trust paths (auth/, security/, payment/) default to `hybrid`.

### 6. Workflow Sequence and Flow Guards

Follow the defined workflow without skipping stages. Each core command enforces prerequisites:

- **`plan`** requires: request routed via `/workflows:route`
- **`work`** requires: planner status = `COMPLETED` in `tasks.md` Workflow State AND all required plan files exist on disk (`proposal.md`, `specs.md`, `design.md`, `tasks.md`)
- **`review`** requires: implementation status = `COMPLETED` in `tasks.md` Workflow State
- **`compound`** requires: review status = `APPROVED` in `tasks.md` Workflow State

If a prerequisite is not met, STOP and complete the missing step first.

- For complex or unclear features, Shape before Planning (`/workflows:shape` → `/workflows:plan`)
- Don't implement before Planning is `COMPLETED`
- Don't Review before Implementation is `COMPLETED`
- If you need to change workflow, document why in `DECISIONS.md`

**Full sequence**: Route → Shape (optional) → Plan → Work → Validate (auto) → Review → Compound

### 7. Command Tiers

Commands are organized in tiers. Only Tier 1 and Tier 2 commands should be invoked directly by users:

- **Tier 1 (Core Flow)**: `route`, `quick`, `shape`, `plan`, `work`, `review`, `compound`
- **Tier 2 (Support)**: `status`, `help`, `discover`

### 8. Validate Before Delivering (Self-Questioning)

Every AI-generated solution must be questioned before delivery. The AI must:

- **Extract hidden assumptions** — every solution has at least 3
- **Check blind spots** — context, technical, business, and integration
- **Consult past learnings** — read `validation-learning-log.md` before asking questions
- **Ask the user only what's necessary** — never repeat a question already answered in the log
- **Log every interaction** — answers become permanent learnings for future features

```
Question first, deliver second.
Never assume the 70% zone is complete.
Every user answer is a permanent asset, not a one-time input.
```

The validation learning log (`validation-learning-log.md`) grows with each feature, making the workflow dynamically smarter. Patterns confirmed in ≥5 features are promoted to project rules automatically.

See `core/docs/VALIDATION_LEARNING.md` for the full specification.

### 9. Synchronized State

Use `tasks.md` Workflow State section to communicate state between roles.

- Update `tasks.md` Workflow State frequently
- Read other roles' state before starting
- Use standard states: `PENDING`, `IN_PROGRESS`, `BLOCKED`, `WAITING_API`, `COMPLETED`, `APPROVED`, `REJECTED`

### 10. Baseline Freeze (openspec/specs/)

The `openspec/specs/` directory is the **read-only baseline** of project specifications. Only the `/workflows:compound` command (via spec-merger) may write to it.

```
BASELINE FREEZE RULE:

  openspec/specs/ is READ-ONLY during plan, work, and review.
  Only /workflows:compound may write to openspec/specs/.

  Planner writes to:  openspec/changes/{slug}/ (proposal, specs, design, tasks)
  Implementer reads:  openspec/changes/{slug}/ and openspec/specs/
  Reviewer reads:     openspec/changes/{slug}/ and openspec/specs/
  Compound writes to: openspec/specs/ (merges changes into baseline)
```

### 11. Planning Persistence (Write-Then-Advance)

Planning output must be written to disk incrementally, not accumulated in memory.

```
THE WRITE-THEN-ADVANCE RULE:

Every planning phase writes its output file BEFORE the next phase begins.
Every work task updates tasks.md BEFORE the next task begins.

PHASE COMPLETION PROTOCOL (applies to every phase):

1. GENERATE the phase output in full
2. WRITE the output file to disk immediately (use Write tool)
3. UPDATE tasks.md with phase completion status + timestamp
4. VERIFY the file exists on disk (use Read tool to confirm)
5. ONLY THEN advance to the next phase

If step 2 fails, RETRY the write. Do NOT proceed to next phase
with unwritten output.
```

This ensures:
- Interrupted sessions can be resumed from the last completed phase/task
- Partial progress is never lost
- `tasks.md` always reflects the true current state

Violations:
- Generating multiple phase outputs without writing intermediate files
- Advancing to Phase 3 without Phase 2 output written to disk
- Completing tasks without updating `tasks.md` between them

This rule applies to both `/workflows:plan` and `/workflows:work`.

### 12. Contradiction Detection Protocol (CDP)

When information from one artifact contradicts another, **stop and ask the user** instead of silently choosing one side. Contradictions are objective — unlike low confidence (which is subjective), a contradiction between documents is verifiable.

```
CONTRADICTION DETECTION PROTOCOL:

TRIGGER: Any of these detected during plan, work, or review:
  - User request contradicts constitution.md principles
  - User request contradicts existing specs in openspec/specs/
  - specs.md contradicts design.md (WHAT vs HOW mismatch)
  - design.md contradicts architecture-profile.yaml patterns
  - Implementation deviates from design.md (caught during self-review)
  - Decision Log entry contradicts a prior decision
  - Cross-session: previous decision contradicts current context

ACTION:
  1. STOP the current phase (do not silently resolve)
  2. IDENTIFY the contradiction explicitly:
     "Document A says X (source: [file:line])
      but Document B says Y (source: [file:line])"
  3. PRESENT options to the user:
     - [Keep A]: Follow Document A, update Document B
     - [Keep B]: Follow Document B, update Document A
     - [Reconcile]: Both are partially right — user provides resolution
     - [Override]: User provides a new answer that supersedes both
  4. LOG the resolution in tasks.md Decision Log with:
     - What contradicted what
     - Which option was chosen
     - Why (user's rationale)
  5. UPDATE the losing document to reflect the resolution
     BASELINE FREEZE GUARD: If the losing document is in openspec/specs/
     (read-only baseline per §10), do NOT update it directly. Instead:
     - Record the required change in openspec/changes/{slug}/specs.md
     - The actual baseline update will happen via /workflows:compound
  6. RESUME the phase

EXCEPTIONS (do NOT stop for these):
  - Formatting/style differences between documents (non-semantic)
  - Outdated comments in code that don't affect behavior
  - Version number mismatches in documentation headers
```

This protocol is referenced by `/workflows:plan` (Phase 1, 2, 3), `/workflows:work` (Solution Validation), and `/workflows:review` (Code Quality Review).

---

## Context Window Management

Treat context as a resource. Thresholds depend on the active provider (see `core/providers.yaml`).

### Signs to Restart Session

Resolve the context_management provider first, then apply thresholds:

| Signal | Manual Snapshots (standard) | Compaction-Aware (advanced) |
|--------|----------------------------|----------------------------|
| Files read | > 20 | > 50 |
| Session duration | > 2 hours | > 4 hours |
| Messages | > 50 | > 150 |
| Context capacity | > 70% | > 85% |

If provider is `auto`, detect tier per `core/docs/CAPABILITY_PROVIDERS.md` Detection Protocol.

### Session Restart Protocol
1. Save current state in `tasks.md`
2. Commit all work with checkpoint message
3. Document resume point (last checkpoint, next task, relevant files)
4. Start new session reading: role.md, tasks.md, relevant files

See `core/docs/SESSION_CONTINUITY.md` for detailed strategies.

---

## Comprehension Debt (The 80% Problem)

Code generation speed should not exceed comprehension speed.

### Indicators

| Level | Indicator | Action |
|-------|-----------|--------|
| Critical | "Works but I don't know why" | Stop and understand before continuing |
| High | Need to re-read file for every change | Document key concepts |
| Medium | Copy patterns without understanding why | Study the pattern first |
| Healthy | Can explain code from memory | Continue |

### Before Marking COMPLETED

Verify you can:
- Explain what the code does without looking at it
- Rewrite it if necessary
- Explain all abstractions used
- Explain decisions to a new team member

---

## Workflow Evolution (Governance)

The workflow evolves deliberately, not by fashion. Every addition demonstrates value before implementation.

### Evaluation Criteria

| Criterion | Weight |
|----------|--------|
| Solves a real problem we have | 30% |
| Proven in production by others | 20% |
| Integrates with our system | 20% |
| Benefit justifies complexity | 15% |
| Maintainable long-term | 15% |

Minimum threshold: weighted score ≥ 3.5/5 to proceed.

Avoid: implementing trends because they're fashionable, adding tools without concrete use cases, major refactors without impact analysis.

---

## Permissions

### Reading — Each role can read:
- Their own role definition
- All rules files
- Workflow definitions
- Feature states (`tasks.md`)
- Code relevant to their role

### Writing — Each role can only write to:
- Their assigned code area
- Their section of `tasks.md`
- Files assigned to their role

Only the Planner role can modify project rules (with justification in `DECISIONS.md`).

---

## Global Prohibitions

1. Committing code without tests
2. Pushing with failing tests
3. Changing code without documenting why
4. Skipping the workflow
5. Implementing features not defined by Planner
6. Changing API contracts without consensus
7. Force pushing to `main` or `develop`
8. Committing generated files (`.env`, `node_modules/`, `vendor/`, etc.)

---

## Scoped Rules Reference

| Rule File | Applies To | Content |
|-----------|-----------|---------|
| `testing-rules.md` | Test files (`*Test.php`, `*.test.ts`, etc.) | TDD workflow, coverage, Bounded Correction Protocol |
| `security-rules.md` | Auth, security, payment paths | Trust model, supervision calibration, security prohibitions |
| `git-rules.md` | Git operations | Branching, commits, conflict management, multi-agent sync |

---

## Rollback Protocol

When a task or phase produces broken state that cannot be corrected via BCP:

```
ROLLBACK STEPS:
1. Identify the last known-good checkpoint (git commit with passing tests)
2. git stash (preserve current work for analysis)
3. git checkout <last-good-commit> -- <affected-files>
4. Verify tests pass at this state
5. Document what went wrong in tasks.md Decision Log
6. Re-plan the failed task with a different approach
```

**Rules:**
- Never rollback without documenting the reason
- Never rollback another role's completed work without their confirmation
- Prefer targeted file rollback (`git checkout <commit> -- <file>`) over full branch reset
- After rollback, the task returns to PENDING status with a note about the previous attempt

---

## Terminology

Canonical terms used throughout the plugin. Use these consistently — avoid synonyms.

| Term | Abbreviation | Definition | NOT |
|------|-------------|------------|-----|
| **Workflow State** | — | Section in `tasks.md` tracking role statuses, phases, and resume points | "Feature state", "Phase state" |
| **Checkpoint** | — | Git commit marking task completion. Invoked via `/multi-agent-workflow:checkpoint` | "Snapshot" (for git commits), "save point" |
| **Bounded Correction Protocol** | BCP | Auto-correction loop with scale-adaptive limits (simple:5, moderate:10, complex:15). See `testing-rules.md` | "Bounded Auto-Correction", "fix loop", "retry loop" |
| **Flow Guard** | — | Pre-execution check at the start of each command verifying prerequisites are met | "Guard", "Prerequisites check" |
| **Quality Gate** | QG | Validation of output at the end of a phase. Uses BCP with max 3 iterations in planning | "Quality Check", "validation step" |
| **OpenSpec** | — | Directory structure (`openspec/`) holding project specs, feature changes, and architecture docs | "spec files", "spec directory" |
| **SOLID Constraint** | — | Mandatory SOLID compliance verification in Phase 3 (planning) and checkpoints (work) | "SOLID check", "SOLID score" |
| **Compound Capture** | — | The process in `/workflows:compound` of extracting and persisting learnings from a feature | "Knowledge extraction", "post-mortem" |
| **Provider** | — | Abstraction layer resolving commands to model-specific implementations. See `CAPABILITY_PROVIDERS.md` | "Backend", "adapter" |

---

**Note**: These rules are part of the Multi-Agent Workflow Plugin and should not be modified per-project. For project-specific rules, create files in `.ai/project/rules/`.
