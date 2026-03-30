# Plan — CLAUDE.md Restructuring

**Date:** 2026-03-30
**Spec:** `docs/superpowers/specs/2026-03-30-claude-md-restructure-design.md`
**Phase:** Single-phase — this is documentation restructuring, no code abstractions needed.

## Files

| File | Action | ~Lines |
|------|--------|--------|
| `docs/knowledge/solid-principles.md` | Create | ~100 |
| `docs/knowledge/development-workflow.md` | Create | ~600 |
| `CLAUDE.md` | Rewrite | ~500 |
| `docs/knowledge/index.md` | Update | minor |

## Tasks

- [ ] **Task 1:** Create `docs/knowledge/solid-principles.md`
  - Migrate SOLID detail (L42-103 of current CLAUDE.md) with examples and violations
  - Commit and push

- [ ] **Task 2:** Create `docs/knowledge/development-workflow.md`
  - Migrate: Workflow Engine Integration (session-state schema, gates, validators, deviation mode)
  - Migrate: Automatic Session Context (hook details)
  - Migrate: Automatic Status Line (format, rules)
  - Migrate: Feedback Capture detail (templates, data per phase) — reference feedback-learning.md for system architecture
  - Migrate: Learning Loop detail (immediate + periodic process)
  - Migrate: Harness Assumptions table
  - Migrate: Known problems (tool_use ids, assistant prefill, subagent infra, subagent output limits)
  - Migrate: Context Hygiene detail
  - Commit and push

- [ ] **Task 3:** Rewrite CLAUDE.md — Part 1 (Project Identity + Principles summaries)
  - Keep project overview, tech stack, commands as-is
  - Write SOLID executive summary (~10 lines) with "why" + pointer
  - Write DDD executive summary (~10 lines) with "why" + pointer
  - Write Design Patterns executive summary (~10 lines) with "why" + pointer
  - Keep Conventions compact

- [ ] **Task 4:** Rewrite CLAUDE.md — Part 2 (Development Flow narrative)
  - Why this flow exists (fresh context, ephemerality, QA chain)
  - Flow classification table
  - Full-flow as connected narrative (8 phases, each explains what it produces and why it feeds the next)
  - Debug-flow as variant
  - Micro/light/explore flows compact
  - Anti-omission integrated into brainstorm phase
  - Scope change detection integrated

- [ ] **Task 5:** Rewrite CLAUDE.md — Part 3 (Working Principles + Critical Patterns + Reference)
  - Atomic commits with "why"
  - Non-redundancy with "why"
  - Pre-exploration gate compact
  - Scalability in decisions with "why"
  - Documentation honesty
  - Critical patterns (entity identity, multi-tenancy, roles, constructor changes)
  - Knowledge modules table
  - Backlog arquitectonico
  - Skills reference (index only, not full content)

- [ ] **Task 6:** Update `docs/knowledge/index.md`
  - Add solid-principles.md and development-workflow.md entries
  - Commit and push

- [ ] **Task 7:** Verify consistency
  - Check all pointers from CLAUDE.md resolve to existing files
  - Check no critical behavioral instruction was lost (diff review)
  - Commit final and push
