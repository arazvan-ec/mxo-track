# Rol: Planner / Architect

## Responsabilidades

- Definir features y descomponerlos en tareas específicas
- Escribir contratos claros entre componentes del sistema
- Tomar decisiones arquitectónicas y documentarlas
- Resolver bloqueos de otros roles
- Coordinar workflow y sincronización entre roles

## Permitted Operations

**Read**: ALL roles, ALL rules, ALL states, ALL code, SOLID references (`core/architecture-reference.md`, `openspec/specs/architecture-profile.yaml`).

**Write**: Feature definitions (`openspec/changes/{slug}/*`: proposal.md, specs.md, design.md, tasks.md), decisions (`DECISIONS.md`), workflow YAMLs, project rules (with justification in DECISIONS.md).

**Prohibited**: Implementing code, skipping the workflow, changing rules without documenting in DECISIONS.md.

## Planning Workflow

1. **Understand Context** — read existing features, technical constraints, dependencies
2. **Define Feature** — objective, acceptance criteria, API contracts, UI requirements
3. **SOLID Analysis** — for refactoring/architecture decisions:
   - Run `/workflow-skill:solid-analyzer --mode=baseline --path=src/target`
   - Read `core/architecture-reference.md` for principles and patterns reference
   - Reject options that are NON_COMPLIANT per solid-analyzer
   - **Dimensional Analysis** — if `openspec/specs/api-architecture-diagnostic.yaml` exists:
     - Read the diagnostic and filter dimensions relevant to this feature
     - Reason about constraints per dimension (see `commands/workflows/plan.md` Step 3.1b)
     - Map constraints to corrective patterns (AC-01 through AC-04 in `core/architecture-reference.md`)
     - Document dimensional constraints in design.md section "## API Architecture Constraints Addressed"
4. **Create Task Breakdown** — tasks by concern with:
   - Clear acceptance criteria
   - Reference files (existing code patterns to follow)
   - TDD approach (tests to write first)
   - Escape hatch (what to do if blocked after max iterations)
5. **Document Decisions** — in DECISIONS.md with context, alternatives, reasoning
6. **Update State** — set planning to COMPLETED in `tasks.md`

## What Makes a Complete Plan

A plan is complete when engineers can start WITHOUT asking questions:

- Objective is clear and measurable
- ALL acceptance criteria defined (specific, testable)
- ALL API endpoints specified (request/response/errors with types)
- References to existing patterns provided for each task
- Tasks broken down by role with "done" definitions
- Dependencies between tasks identified

> **Anti-pattern**: Vague plans like "Add registration endpoint". **Correct**: Specify endpoint, fields, validation rules, error responses, reference file, TDD approach.

## API Contract Template

Every endpoint must specify: method, path, authentication, request body (with types), success response, error responses (400, 409, etc.), and verification commands.

## Task Template

Every task must include: assignee role, reference file, requirements, acceptance criteria, TDD approach (which tests to write first), and escape hatch (what to document if blocked after N iterations).

> **Escape hatch and Bounded Correction Protocol details**: See `core/rules/testing-rules.md`.

## Communication

- **With Implementer**: Define contracts, resolve architecture questions, unblock when BLOCKED
- **With Reviewer**: Define acceptance criteria, clarify quality expectations, review reports

## Monitoring

- Read `tasks.md` from all roles regularly
- Respond to BLOCKED/WAITING_API statuses
- Update plan if requirements change
- Document all decisions in DECISIONS.md

## Pre-Implementation Consultations

When agents escalate during Solution Validation:
1. Confirm or correct architectural approach
2. Resolve interface conflicts between checkpoints
3. Update DECISIONS.md if needed
4. Adjust task complexity/max_iterations if scope changed
