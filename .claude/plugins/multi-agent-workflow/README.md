# Multi-Agent Workflow Plugin

A **compound engineering** framework for Claude Code that coordinates multiple AI agents on software development tasks. 100% stack-agnostic -- detects your project's stack at runtime.

> *"Each unit of engineering work should make subsequent units easier -- not harder."*

**v3.2.0** -- 8 agents, 10 commands, 15 skills, 3 roles.

---

## The Flow

```
ROUTE --> SHAPE --> PLAN --> WORK --> REVIEW --> COMPOUND
(entry)  (optional)  (80%)   (15%)    (4%)       (1%)

ROUTE --> QUICK (lightweight path for simple tasks)
```

Every request starts with routing. No exceptions.

## Installation

```bash
/plugin marketplace add https://github.com/arazvan-ec/workflow
/plugin install multi-agent-workflow
```

## Quick Start

```bash
# Onboard a new project (auto-detects stack, configures plugin)
/workflows:discover --setup

# Route any request (always start here)
/workflows:route add user authentication with JWT

# Plan a feature (where 80% of the value lives)
/workflows:plan user-authentication

# Execute with TDD + Bounded Correction Protocol
/workflows:work user-authentication

# Multi-agent quality review
/workflows:review user-authentication

# Capture learnings for compound acceleration
/workflows:compound user-authentication
```

---

## Commands (10)

### Core Commands (7)

| # | Command | Purpose | When |
|---|---------|---------|------|
| 0 | `/workflows:route` | Classify request, ask questions, select workflow | **Always first** |
| 0b | `/workflows:quick` | Lightweight path for simple tasks | Simple changes (3 files or fewer) |
| 1 | `/workflows:shape` | Separate problem from solution, spike unknowns | Complex/unclear features only |
| 2 | `/workflows:plan` | Architecture-first planning with SOLID constraint | Before any implementation |
| 3 | `/workflows:work` | TDD implementation with Bounded Correction Protocol | After plan is COMPLETED |
| 4 | `/workflows:review` | Multi-agent quality review | After work is COMPLETED |
| 5 | `/workflows:compound` | Capture learnings for future acceleration | After review is APPROVED |

### Support Commands (3)

| Command | Purpose |
|---------|---------|
| `/workflows:status` | View all roles' progress and state |
| `/workflows:help` | Quick reference and guidance |
| `/workflows:discover` | Auto-analyze project architecture (`--setup` flag for first-time onboarding) |

### Flow Guards

Commands enforce ordering via `tasks.md` Workflow State (in `openspec/changes/{slug}/tasks.md`):
- `plan` requires routing completed
- `work` requires plan status = COMPLETED
- `review` requires work status = COMPLETED
- `compound` requires review status = APPROVED

---

## Agents (8)

### Review (4)

| Agent | Purpose |
|-------|---------|
| `code-reviewer` | Stack-agnostic code review (detects language/framework at runtime) |
| `security-reviewer` | OWASP compliance, vulnerability scanning |
| `performance-reviewer` | Performance analysis (context-activated) |
| `architecture-reviewer` | DDD + SOLID compliance (context-activated) |

### Research (2)

| Agent | Purpose |
|-------|---------|
| `codebase-analyzer` | Analyze project structure, patterns, and conventions |
| `learnings-researcher` | Search past solutions and documented learnings |

### Workflow (2)

| Agent | Purpose |
|-------|---------|
| `diagnostic-agent` | Systematic debugging with bug reproduction mode |
| `spec-analyzer` | Validate implementation against specifications |

All agents run with `context: fork` -- isolated context windows that return summaries only, preventing parent session pollution.

---

## Skills (14)

| Category | Skills | Purpose |
|----------|--------|---------|
| **Core** | `consultant`, `checkpoint`, `git-sync`, `commit-formatter` | Analysis, quality gates, repo sync, conventional commits |
| **Quality** | `test-runner`, `coverage-checker`, `lint-fixer` | Test execution, coverage thresholds, auto-fix style |
| **Compound** | `spec-merger`, `validation-learning-log` | Spec consolidation, AI validation learning |
| **Integration** | `mcp-connector` | External tools via MCP (postgres, github, slack) |
| **SOLID** | `solid-analyzer`, `criteria-generator` | SOLID compliance analysis, acceptance criteria generation |
| **Shaping** | `shaper`, `breadboarder` | Problem shaping, UI flow sketching |

---

## Roles (3)

Defined in `core/roles/`. Assigned during the workflow, one role per session.

| Role | Purpose |
|------|---------|
| `planner` | Architecture, specifications, and coordination |
| `implementer` | Stack-agnostic code implementation (detects stack at runtime) |
| `reviewer` | Testing, validation, and quality approval |

---

## Key Patterns

### Bounded Correction Protocol (BCP)

Detects and corrects three deviation types with scale-adaptive iteration limits:

| Deviation | Detection | Action |
|-----------|-----------|--------|
| **Type 1**: Test failure | Tests fail | Fix implementation (never the test) |
| **Type 2**: Missing functionality | Gap vs acceptance criteria | Add missing code |
| **Type 3**: Incomplete pattern | Mismatch vs reference file | Complete the pattern |

Adaptive limits: simple = 5, moderate = 10, complex = 15 iterations.

After 3 consecutive identical errors, `diagnostic-agent` is invoked automatically for escalation.

### Compound Capture

After each feature, `/workflows:compound` extracts patterns, anti-patterns, and insights -- feeding them back into the project's knowledge base so future work accelerates.

### Validation Learning

The AI self-questions solutions, asks targeted user questions, and logs answers. Each completed feature makes future validation smarter. See `core/docs/VALIDATION_LEARNING.md`.

### SOLID Constraint

Phase 3 (plan) solutions must be COMPLIANT across all relevant SOLID principles. See `core/architecture-reference.md` and `openspec/specs/architecture-profile.yaml`.

### Karpathy Principles

Think before coding, simplicity first, surgical changes, goal-driven execution. Details in `core/docs/KARPATHY_PRINCIPLES.md`.

### Decision-Challenge Loop

Before selecting a workflow, the router challenges assumptions, compares alternatives, and asks blocking questions for missing constraints instead of guessing. This improves routing quality for ambiguous requests and sensitive domains.

---

## Context Engineering

The plugin manages what agents see and when they see it, following Fowler's activation taxonomy:

| Content | Activation | When Loaded |
|---------|-----------|-------------|
| `CLAUDE.md` + `framework_rules.md` | Always | Every session |
| Scoped rules (`*-rules.md`) | Software-determined | When matching file types are edited |
| Role definitions (`core/roles/`) | LLM-determined | When role is active |
| Skills (`skills/`) | Human-triggered | On `/skill:X` invocation |
| Review agents (`agents/review/`) | Human-triggered | During `/workflows:review` |

Heavy skills and review agents run with `context: fork` -- isolated context windows returning summaries only. See `core/docs/CONTEXT_ENGINEERING.md`.

---

## Capability Providers

The plugin is **model-agnostic** and **execution-agnostic**. Capabilities (parallelization, context management, fork strategy, execution mode) are abstracted behind providers that auto-detect the running model.

Configuration: `core/providers.yaml` (set to `auto` for detection or force specific providers).
Reference: `core/docs/CAPABILITY_PROVIDERS.md`.

---

## Project Structure

```
plugins/multi-agent-workflow/
├── .claude-plugin/plugin.json
├── agents/
│   ├── research/        # codebase-analyzer, learnings-researcher
│   ├── review/          # code-reviewer, architecture-reviewer,
│   │                    # security-reviewer, performance-reviewer
│   └── workflow/        # diagnostic-agent, spec-analyzer
├── commands/workflows/  # 10 commands (route, quick, shape, plan,
│                        # work, review, compound, status, help, discover)
├── skills/              # 14 skills across 6 categories
├── core/
│   ├── roles/           # planner, implementer, reviewer
│   ├── rules/           # framework_rules, testing-rules,
│   │                    # security-rules, git-rules
│   ├── docs/            # 8 reference documents
│   └── providers.yaml
├── CLAUDE.md
└── README.md
```

---

## v3.2.0 Changes

Key changes from v3.1.0:

- **Spec-Driven Development (SDD)**: Structured artifact pipeline — proposal → specs → design → tasks → scratchpad
- **Test Contract Sketch** (Phase 2.5): Pre-validates test boundaries and scenarios before design
- **Reflection Pattern**: Mandatory self-review before marking plan or work as COMPLETED
- **Decision Log enforcement**: Every non-obvious design choice gets rationale and risk logged
- **HITL checkpoints**: Human confirmation for high-risk tasks (migrations, auth, payments, CI/CD)
- **Feedback Loop REVIEW→PLAN**: Design flaws route back to planning instead of just rejecting
- **Scratchpad persistence**: Per-feature working notes survive compaction and session breaks
- **Constitution template**: Project-level non-negotiable principles (inspired by GitHub Spec Kit)
- **Context Budget Awareness**: Guidance on compaction, MCP overhead, and fork strategy
- **source-report skill**: New research skill (15 skills total, was 14)

## v3.1.0 Changes

Key changes from v3.0.0:

- **100% stack-agnostic**: No hardcoded TypeScript/PHP references; stack detected at runtime
- `code-review-ts` renamed to `code-reviewer` (language-agnostic)
- `ddd-compliance` renamed to `architecture-reviewer` (DDD + SOLID, context-activated)
- `security-review` renamed to `security-reviewer`
- `performance-review` renamed to `performance-reviewer` (context-activated)
- `diagnostic-agent` expanded with bug reproduction mode
- `backend` + `frontend` roles merged into `implementer`
- `qa` role renamed to `reviewer`
- `quickstart` merged into `discover --setup`
- Removed commands: `specs`, `validate`, `solid-refactor`, `role`
- Removed 12 agents: api-designer, solid-architecture-generator, ui-verifier, bug-reproducer, git-historian, dependency-auditor, code-simplicity-reviewer, pattern-recognition-specialist, agent-native-reviewer, style-enforcer, best-practices-researcher, comprehension-guardian

---

## Reference Documentation

| Topic | Location |
|-------|----------|
| Capability providers | `core/docs/CAPABILITY_PROVIDERS.md` |
| Provider configuration | `core/providers.yaml` |
| Routing reference | `core/docs/ROUTING_REFERENCE.md` |
| Workflow decision matrix | `core/docs/WORKFLOW_DECISION_MATRIX.md` |
| Karpathy principles | `core/docs/KARPATHY_PRINCIPLES.md` |
| Context engineering | `core/docs/CONTEXT_ENGINEERING.md` |
| Session continuity | `core/docs/SESSION_CONTINUITY.md` |
| MCP integration | `core/docs/MCP_INTEGRATION.md` |
| Validation learning | `core/docs/VALIDATION_LEARNING.md` |
| Architecture reference | `core/architecture-reference.md` |
| Operational rules | `core/rules/framework_rules.md` |
| Testing conventions | `core/rules/testing-rules.md` |
| Security & trust model | `core/rules/security-rules.md` |
| Git workflow | `core/rules/git-rules.md` |

---

## Intellectual Influences

- [Compound Engineering](https://every.to/source-code/compound-engineering-how-every-codes-with-agents-af3a1bae-cf9b-458e-8048-c6b4ba860e62) (Every) -- core philosophy of accelerating returns
- [Context Engineering for Coding Agents](https://martinfowler.com/articles/exploring-gen-ai/context-engineering-coding-agents.html) (Martin Fowler) -- activation taxonomy, context calibration
- [Build Agent Skills Faster with Claude Code 2.1](https://medium.com/@richardhightower/build-agent-skills-faster-with-claude-code-2-1-release-6d821d5b8179) (Rick Hightower) -- skill architecture, Queen Agent pattern
- [Shape Up](https://basecamp.com/shapeup) (Ryan Singer) -- shaping phase, appetite-based scoping
- [GSD (Get Shit Done)](https://github.com/gsd-build/get-shit-done) -- deviation detection, goal-backward verification
- [BMAD Method](https://github.com/bmad-code-org/BMAD-METHOD) -- scale-adaptive limits, adversarial self-review
- Andrej Karpathy -- coding principles for AI-assisted development

---

## License

MIT

## Author

[arazvan-ec](https://github.com/arazvan-ec)
