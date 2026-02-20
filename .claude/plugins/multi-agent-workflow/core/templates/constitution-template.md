# Project Constitution: ${PROJECT_NAME}

**Created**: ${DATE}
**Last Updated**: ${DATE}
**Version**: 1.0

---

## Purpose

This document defines the non-negotiable principles for this project. Every planning decision, implementation choice, and review criterion must be consistent with these principles. AI agents and human engineers read this file to understand the project's constraints before making any change.

Inspired by GitHub Spec Kit's `constitution.md` — this is the "bill of rights" for the codebase.

---

## Architecture Principles

<!-- List the architectural principles this project follows. These are enforced, not advisory. -->

1. **[Principle Name]**: [Description — what it means concretely for this project]
2. **[Principle Name]**: [Description]
3. **[Principle Name]**: [Description]

## Code Quality Standards

<!-- Define the minimum quality bar. These are gate criteria for /workflows:review. -->

- **Test coverage minimum**: [e.g., 80% backend, 70% frontend]
- **Test methodology**: [e.g., TDD required for all new features]
- **Linting**: [e.g., ESLint/Prettier with project config, zero warnings allowed]
- **Type safety**: [e.g., strict TypeScript, no `any` types]

## Technology Constraints

<!-- Technologies that are allowed, required, or prohibited. -->

### Required
- [e.g., TypeScript for all new code]
- [e.g., PostgreSQL for persistence]

### Prohibited
- [e.g., No ORM for complex queries — use raw SQL with query builders]
- [e.g., No new jQuery — use React for all UI]

### Preferred (not mandatory)
- [e.g., Prefer composition over inheritance]
- [e.g., Prefer functional components over class components]

## Security Principles

<!-- Non-negotiable security constraints. -->

1. **[Principle]**: [e.g., All user input validated at controller level before reaching domain]
2. **[Principle]**: [e.g., Secrets never committed to git — use environment variables]
3. **[Principle]**: [e.g., All API endpoints require authentication unless explicitly marked public]

## API Conventions

<!-- Standards for API design. -->

- **Style**: [e.g., RESTful, resource-based URLs]
- **Versioning**: [e.g., URL prefix /api/v1/]
- **Response format**: [e.g., JSON with standard envelope {data, error, meta}]
- **Error format**: [e.g., {code, message, details}]
- **Authentication**: [e.g., Bearer JWT in Authorization header]

## Git & Workflow Conventions

<!-- How code changes flow through the project. -->

- **Branch strategy**: [e.g., feature branches from main, squash merge]
- **Commit format**: [e.g., Conventional Commits — feat:, fix:, refactor:]
- **PR requirements**: [e.g., Tests passing, 1 approval, no force push to main]

## What This File Is NOT

- This is NOT a style guide (that belongs in linter configs)
- This is NOT documentation (that belongs in docs/)
- This is NOT a feature spec (that belongs in openspec/)
- This IS the set of constraints that never change without explicit team agreement

---

**Location**: `openspec/specs/constitution.md`
**Loaded by**: `/workflows:plan` (Step 0), `/workflows:review` (Phase 4)
**Updated by**: `/workflows:compound` (only after team agreement)
