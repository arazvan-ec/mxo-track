---
type: spec
feature: autonomy-contract-claude-md-permissions
date: 2026-04-24
---

# Spec — Autonomy Contract (CLAUDE.md + permissions)

## Context

Over the last N interactions the user observed friction: "en todas las
ediciones de archivos me has pedido permisos, eso hace que el desarrollo
dependa mucho de mí". Three sources compound this:

1. **CLAUDE.md ambiguity** — the "Executing actions with care" section
   says file edits are fine, then in the same paragraph insists on
   confirmation for "scope beyond." The model resolves the tension by
   defaulting to over-asking.
2. **`.claude/settings.local.json` format** — bare tool names like
   `"Edit"` may not auto-approve; proper syntax is `"Edit(*)"` or
   `defaultMode: acceptEdits`.
3. **Model habit** — prose-level "¿procedo?" questions that are not
   required by the harness or CLAUDE.md, just defensive friction.

## Problem

The user is forced into per-edit gatekeeping instead of phase-level
oversight. The harness already has structured checkpoints (H, C, I,
verification, retrospective visibility, pre-push-gate) that provide
architectural and quality oversight. Per-edit prompts are redundant
friction on top.

## Approaches Considered

### Approach α — Weaken harness hooks (rejected)

Remove or loosen hooks to reduce gating entirely.

- **Ventaja:** frictionless.
- **Desventaja:** loses the architectural discipline we just built
  (H/C/F/I/J). Exactly the wrong trade.
- **Rejected.**

### Approach β — Explicit Autonomy Contract + settings fix (chosen)

Add a "## Autonomy Contract" section to CLAUDE.md that enumerates what
requires user input vs what doesn't. Update `.claude/settings.local.json`
to use the correct auto-approve syntax. Model reads the contract each
interaction and drops the defensive "¿procedo?".

- **Ventaja:** clear boundary; user keeps phase-level oversight; harness
  hooks continue to enforce architectural rules orthogonally.
- **Ventaja:** explicit opt-ins preserved (destructive git, shared-system
  side effects, uploads).
- **Desventaja:** user loses granular interrupt; course corrections now
  happen at phase boundaries rather than per-edit.
- **Trade-off accepted:** the harness already provides ~6 structured
  checkpoints; granular per-edit interrupts are redundant.

### Approach γ — Per-interaction autonomy declaration (rejected)

User declares at interaction start "full autonomy for this one" or "strict
gatekeeping for this one." Configurable per task.

- **Ventaja:** flexible.
- **Desventaja:** adds a new decision at every interaction start; negates
  the "less gatekeeping" goal.
- **Rejected.**

## Trade-offs accepted

1. **Model acts without per-edit confirmation within planned scope.** The
   model edits freely during implementation; user reviews at verification,
   retrospective, and finalize checkpoints. Accepted because the plan
   already enumerates files-to-touch and the brainstorm already approved
   the approach.
2. **Destructive operations still require explicit approval.** `reset
   --hard`, `push --force`, `branch -D`, writes to shared systems all
   continue to prompt. Accepted — the asymmetry between reversible and
   destructive ops is real.
3. **`.claude/settings.local.json` is gitignored.** The permissions file
   lives per-dev-machine. Each user configures their own autonomy level;
   the CLAUDE.md contract is the SHARED expectation. Accepted.

## Existing Functionality Inventory

| Element | Decision | Justification |
|---|---|---|
| `CLAUDE.md` "Executing actions with care" section | **Modify** — keep the core guidance but reference the new Autonomy Contract for concrete boundaries. |
| New `## Autonomy Contract` section in `CLAUDE.md` | **Add** — enumerate REQUIRE-input and NO-input lists. |
| `.claude/settings.local.json` permissions | **Modify** — use `(*)` per-tool syntax or `defaultMode: acceptEdits`. |
| Harness hooks (classify/phase-advance/validators) | **Keep unchanged** — they enforce architectural rules orthogonally to tool-level permissions. |
| Test suite | **No changes** — this is doc + config only. |

## Omission Decisions

| Element | Decision | Justification |
|---|---|---|
| Changes to any validator | Omit | Orthogonal — hooks already handle architectural discipline. |
| New tests | Omit | Pure doc + config change; existing tests cover behavior. |
| Per-interaction autonomy config | Omit | Per Approach γ rejection — adds ceremony instead of removing. |
| Migrating `settings.json` (shared) | Omit | Personal autonomy preference belongs in the gitignored local file. |

## Design

### CLAUDE.md: new section

A new `## Autonomy Contract` section is added early in the file (near
"Executing actions with care" or right after Workflow Engine summary)
with two lists:

**REQUIRES user input:**
- Design approval (brainstorming checkpoint)
- Scope changes (new interaction trigger)
- Retrospective approval before finalize
- Destructive git operations
- Shared-system side effects (PRs, external APIs, pushes to main)
- Uploads to third-party tools

**NO user input required:**
- File edits within agreed plan scope
- Tests / lint / build / manifest
- Local git (add, commit, branch create, push feature branch)
- Reading, searching, exploring
- Subagent dispatch for planned work
- `jq` updates to session-state.json for phase/evidence advancement

The existing "Executing actions with care" section receives a one-line
reference to the new Autonomy Contract for concrete decision boundaries.

### `.claude/settings.local.json`: correct syntax

Current bare-name format replaced with either:

**Option A:** per-tool patterns
```json
{ "permissions": { "allow": ["Edit(*)", "Write(*)", "Read(*)", "Bash(*)", "Grep(*)", "Glob(*)", "Agent(*)"] } }
```

**Option B:** default mode
```json
{ "permissions": { "defaultMode": "acceptEdits" } }
```

Will try Option B first (simpler); fallback to Option A if defaultMode
isn't supported in this Claude Code version.

## Verification Plan

- Manual: after applying, run a dummy `Edit` call via the model and
  confirm no permission prompt appears.
- No automated tests needed (pure doc + local config).
- Full regression on existing harness tests to confirm no side-effects
  (should be identical — hooks untouched).

## Non-goals

- Changes to any hook or validator.
- Changes to `settings.json` (shared/committed); local file only.
- Teaching the model to distinguish "risky" vs "safe" within the file
  system — categorical lists are enough.
- Rolling out the same autonomy to shared systems (GitHub, Slack) — those
  keep explicit confirmation.
