# Design Spec — CLAUDE.md Restructuring

**Date:** 2026-03-30
**Type:** documentation restructure
**Complexity:** XL (1993 lines → 3 documents)

---

## Problem

CLAUDE.md has grown to 1993 lines mixing three fundamentally different types of content:
1. **Instructions Claude needs every turn** (~400 lines of project identity, critical patterns, flow narrative)
2. **Workflow engine technical reference** (~300 lines of session-state.json, gates, validators)
3. **Skills reference** (~900 lines of 15 skills that are consulted on-demand)
4. **Principles detail** (~300 lines of SOLID, DDD, Design Patterns with examples and anti-patterns)

The current structure lists rules as mandates without explaining WHY they exist, causing the model to follow them mechanically but take shortcuts when a rule feels arbitrary.

## Design Philosophy

Four principles guide the restructuring:

1. **Filosofia y decisiones** — Every system/rule explains WHY it exists, what problem it solved, what happens without it
2. **Como funciona cada tecnica** — Fresh context, QA loop, TDD explained as mechanisms, not commands
3. **Flujo paso a paso con contexto** — "This produces X which feeds Y" not just "execute this command"
4. **Reglas criticas integradas** — Rules live inside the phase where they apply, not in separate sections

## Approach

Three-layer split with philosophy integrated into the main document:

### Layer 1: `CLAUDE.md` (~400-500 lines) — "The Why and the How"

Everything Claude needs in **every turn**. Organized as a narrative flow, not a rule list.

**Structure:**

```
# Project Identity
  - Overview, tech stack, common commands (as-is, ~40 lines)

# Principles (executive summaries with "why")
  - SOLID — 10-line summary: why each principle matters for THIS codebase, with pointer to docs/knowledge/solid-principles.md
  - DDD — 10-line summary: hybrid purity model, when to use which, pointer to docs/knowledge/architecture-ddd.md
  - Design Patterns — 10-line summary: problem-first approach, pointer to docs/knowledge/design-patterns.md

# Development Flow (the narrative core)
  - Why this flow exists (fresh context problem, session ephemerality, QA loop)
  - Flow classification (micro/light/debug/full/explore) — with WHY each exists
  - The full-flow as connected narrative:
    Phase 1: Consult — "You read past decisions because without them you repeat mistakes. This produces context that informs brainstorming."
    Phase 2: Brainstorm — "You explore alternatives WITH the user because the model's first instinct is often wrong. The spec this produces is the contract that planning and verification check against."
    Phase 3: Plan — "You write a plan because implementation without a plan skips edge cases. The plan feeds the implementer (possibly a subagent) who has zero context."
    Phase 4: Implement — "TDD is mandatory here because the test you write IS the proof that you understood the requirement. Without it, verification in phase 5 degrades to just a build check."
    Phase 5: Verify — "Evidence before claims. The test from phase 4 + lint is what you check here. Without phase 4's test, this phase is hollow."
    Phase 6: Capture — "The execution log you write here feeds the Learning Loop. Next session's consult phase reads this."
    Phase 7: Retrospective — "Decision log entries feed future brainstorming. This closes the loop."
    Phase 8: Finalize — "Branch strategy + manifest update."
  - Anti-omission rule integrated into brainstorming phase (not separate section)
  - Scope change detection integrated into flow classification
  - Debug-flow as variant (root cause → pattern-wide → fix)

# Working Principles (behavioral, needed every turn)
  - Atomic commits — WHY: sessions are ephemeral, a crash loses unpushed work
  - Non-redundancy — WHY: tool calls cost time and context window
  - Pre-exploration gate — WHY: manifest exists precisely to avoid redundant exploration
  - Exploration layers — compact version (Capa 1/1.5/2/3)
  - Scalability in decisions — WHY: the flow IS the safety net for big changes
  - Documentation honesty
  - Context hygiene

# Critical Patterns (project-specific, needed every turn)
  - Entity identity (BIGINT + ULID)
  - Multi-tenancy
  - Role hierarchy
  - Constructor signature changes + factory pattern

# Reference Index
  - Knowledge modules table
  - Conventions (short list)
  - Backlog arquitectonico
  - Harness assumptions table
```

### Layer 2: `docs/knowledge/development-workflow.md` (~600-800 lines) — "Technical Reference"

Consulted when understanding a gate, resolving a block, or debugging the workflow engine.

**Content migrated here:**
- Workflow Engine Integration (session-state.json schema, gates table, validators table, deviation mode)
- Automatic Session Context (hook details)
- Automatic Status Line (format, rules)
- Feedback Capture (execution log template refs, data per phase)
- Learning Loop (immediate + periodic details)
- Harness Assumptions detailed table
- Known problems (tool_use ids, assistant prefill, subagent infra failures)
- Subagent output limits

### Layer 3: `docs/knowledge/superpowers-skills.md` (already exists, ~900 lines)

Skills stay as-is, consulted on-demand. Already referenced from CLAUDE.md.

### Layer 4: `docs/knowledge/solid-principles.md` (new, ~100 lines)

Full SOLID detail migrated from CLAUDE.md (current L42-103).

### Note on Design Patterns and DDD

- Design Patterns detail already exists at `docs/knowledge/design-patterns.md`
- DDD detail already exists at `docs/knowledge/architecture-ddd.md`
- Only need to write executive summaries in CLAUDE.md pointing to them

## Existing Functionality Inventory

| Section (current CLAUDE.md) | Lines | Decision | Destination |
|---|---|---|---|
| Project Overview + Tech Stack + Commands | L15-40 | Include | CLAUDE.md (as-is) |
| SOLID Principles detail | L42-103 | Transform | Executive summary in CLAUDE.md + new `docs/knowledge/solid-principles.md` |
| DDD Architecture detail | L104-150 | Transform | Executive summary in CLAUDE.md (detail already in `architecture-ddd.md`) |
| Design Patterns detail | L152-207 | Transform | Executive summary in CLAUDE.md (detail already in `design-patterns.md`) |
| Conventions | L209-227 | Include | CLAUDE.md (compact) |
| Atomic Commits & Push | L229-279 | Transform | CLAUDE.md with "why" narrative, compact |
| No-Redundancia | L281-288 | Transform | CLAUDE.md with "why", 3 lines |
| Pre-Exploration Gate | L290-389 | Transform | Compact version in CLAUDE.md, detail to `development-workflow.md` |
| Escalabilidad en Decisiones | L391-410 | Transform | CLAUDE.md with "why", integrated into flow narrative |
| Flujo Obligatorio | L412-520 | Transform | CLAUDE.md as connected narrative (core restructure) |
| Workflow Engine Integration | L521-672 | Move | `development-workflow.md` |
| Harness Assumptions | L673-710 | Move | `development-workflow.md` |
| Automatic Session Context | L711-737 | Move | `development-workflow.md` |
| Context Hygiene | L738-748 | Transform | CLAUDE.md compact (4 lines) |
| Automatic Status Line | L749-793 | Move | `development-workflow.md` |
| Feedback Capture | L794-823 | Move | `development-workflow.md` |
| Learning Loop | L824-861 | Transform | Compact "why" in CLAUDE.md, detail to `development-workflow.md` |
| Critical Patterns | L862-891 | Include | CLAUDE.md (as-is, already compact) |
| Anti-Omission Rule | L893-924 | Transform | Integrated into brainstorming phase narrative |
| Knowledge Modules table | L926-960 | Include | CLAUDE.md (as-is) |
| Governance rule | L962-976 | Transform | Simplified, integrated |
| Features Document | L977-980 | Include | CLAUDE.md (as-is) |
| Backlog Arquitectonico | L981-1028 | Include | CLAUDE.md (as-is) |
| Superpowers Skills (15 skills) | L1030-1993 | Include | Already in `superpowers-skills.md` — remove from CLAUDE.md |
| Notes/scratch at top | L1-13 | Include | CLAUDE.md (move to bottom or keep) |
| Known problems (subagent, API errors) | scattered | Move | `development-workflow.md` |

## Omission Decisions

| Element | Decision | Justification |
|---------|----------|---------------|
| Anti-rationalization tables (6 occurrences) | Omit as separate tables | Integrated as 1-2 line warnings within each phase. Having them scattered dilutes impact. |
| Anti-pattern lists per section | Transform | Keep only the 2-3 most common per section, integrated. Full lists in knowledge modules. |
| Detailed session-state.json schema | Move | Not needed every turn. Only when debugging workflow engine. |
| Detailed gate tables (flow x file type) | Move | Reference material, not behavioral instruction. |
| Skill full text (900 lines) | Remove from CLAUDE.md | Already exists in `docs/knowledge/superpowers-skills.md`. CLAUDE.md only needs skill index. |

## Key Narrative Connections to Make Explicit

These are the "this produces X which feeds Y" connections missing from the current doc:

1. **Consult → Brainstorm:** Past decisions prevent repeating mistakes. Without consult, brainstorming proposes approaches already proven wrong.
2. **Brainstorm → Plan:** The spec is the contract. Without it, the plan has no acceptance criteria to verify against.
3. **Plan → Implement:** The plan assumes zero context (for subagents). Without it, implementation skips edge cases the spec covered.
4. **TDD in Implement → Verify:** The test IS the verification evidence. Without it, verification degrades to "does it compile?"
5. **Verify → Capture:** Verification results (pass/fail counts) go into the execution log. Without verify, capture has no data.
6. **Capture → future Consult:** The execution log is what the next session's consult phase reads. Without capture, the learning loop is broken.
7. **Atomic commits → Session resilience:** Claude sessions crash. Every uncommitted line is lost work. Commits are checkpoints, not ceremony.
8. **Pre-exploration gate → Context window:** Every unnecessary grep/glob costs context window. The manifest exists to avoid this cost.
9. **Anti-omission → Brainstorm quality:** Inventorying existing functionality prevents the most common spec defect: silently dropping features that existed before.

## Files to Create/Modify

| File | Action |
|------|--------|
| `CLAUDE.md` | Rewrite (~400-500 lines) |
| `docs/knowledge/development-workflow.md` | Create (~600-800 lines) |
| `docs/knowledge/solid-principles.md` | Create (~100 lines, migrated from CLAUDE.md) |
| `docs/knowledge/superpowers-skills.md` | Verify exists, no changes needed |
| `docs/knowledge/index.md` | Update with new modules |
