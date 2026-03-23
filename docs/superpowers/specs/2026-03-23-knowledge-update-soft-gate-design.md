# Design Spec — Knowledge Update Soft Gate in Finalize Phase

**Date:** 2026-03-23
**Type:** enhancement (workflow enforcement)
**Bounded Context:** Pragmatic (hooks infrastructure)
**Approach:** SOFT gate in finalize phase

---

## Problem

Knowledge modules (`docs/knowledge/*.md`) must be updated when a task touches a subsystem, per CLAUDE.md Freshness Protocol. However, this is an "honor system" rule with no mechanical enforcement. Today, the API Keys menu task completed all 8 phases but forgot to update `ui-frontend.md` until the user pointed it out.

## Solution

Add a SOFT gate (warning, not block) to the finalize phase that:
1. Detects which directories were modified in the current branch (via `git diff`)
2. Maps those directories to relevant knowledge modules
3. Emits a warning listing which knowledge modules may need updating

**Why SOFT, not HARD:**
- Not every code change affects knowledge modules
- A HARD gate would force trivial/fake updates to pass the gate
- A reminder (SOFT) is sufficient — the problem was omission, not resistance

## Brainstorming Summary

### Alternatives Evaluated

1. **New phase (9th phase)** — Adds ceremony; knowledge update is conditional, not always needed. Rejected: YAGNI.
2. **SOFT gate in finalize (chosen)** — Piggybacks on existing finalize validator. Conditional check. Warning only.

### User Decision

User approved Approach 2.

## Changes

1. **`.claude/hooks/validators/finalize-validator.sh`** — Add knowledge module check logic:
   - Run `git diff --name-only origin/main...HEAD` to get changed files
   - Map changed directories to knowledge modules (lookup table)
   - If matches found, emit SOFT warning listing modules to review
2. **`CLAUDE.md`** — Document the new SOFT gate in the Workflow Engine section

## Directory → Knowledge Module Mapping

| Directory pattern | Knowledge module |
|-------------------|-----------------|
| `backend/src/Controller/` | `api-surface.md` |
| `backend/src/Entity/`, `backend/src/Enum/` | `domain-model.md` |
| `frontend/src/` | `ui-frontend.md` |
| `backend/src/Service/*Provider*`, `*Factory*` | `provider-framework.md` |
| `backend/src/Service/*Gps*`, `*Traccar*` | `gps-tracking.md` |
| `backend/src/Service/*Optim*`, `*Routing*` | `route-optimization.md` |
| `backend/src/Service/*Mercure*`, `*Realtime*` | `realtime.md` |
| `backend/src/Security/` | `security.md` |
| `backend/tests/` | `testing.md` |
| `docker/`, `Dockerfile`, `railway*` | `deployment.md` |
| `backend/src/Service/*Notification*`, `*Sms*` | `notifications.md` |

## Existing Functionality Inventory

| Element | Decision | Justification |
|---------|----------|---------------|
| `finalize-validator.sh` — checks `branch_strategy` | Include (extend) | Add knowledge check alongside existing check |
| `workflow-engine.sh` — routes to validators | Include (no change) | Already calls finalize-validator.sh |
| CLAUDE.md finalize phase docs | Include (update) | Document new SOFT gate |

## Omission Decisions

No omissions — all inventory items addressed.
