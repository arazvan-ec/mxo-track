---
type: feature
tags: [workflow, autonomy, claude-md, permissions, ux]
files_touched: [CLAUDE.md, .claude/settings.local.json]
patterns: [autonomy-contract, permission-hook-separation]
outcome: success
outcome_verified_at: 2026-04-24
regressions_later: []
pr_number: null
estimated_lines: 60
actual_lines: 95
duration_minutes: null
consulted_in_future: []
---

# Execution Log — 2026-04-24 — Autonomy Contract + Permissions

**Spec:** `docs/superpowers/specs/2026-04-24-autonomy-contract-design.md`
**Plan:** `docs/superpowers/plans/2026-04-24-autonomy-contract.md`
**Triggering observation:** user noted repeated per-edit permission prompts
making development dependent on manual gatekeeping at every tool call.

## What shipped

### `CLAUDE.md` — new `## Autonomy Contract` section

Inserted after the Workflow Engine summary, before the QA loop section.
Contains:
- **Requires user input (always):** design approval, scope changes,
  retrospective approval, destructive git ops, shared-system side effects,
  bypass env var usage.
- **Does NOT require user input:** file edits within plan scope, tests /
  lint / build / manifest, local git, read/search, subagent dispatch, jq
  updates to session-state, writing docs under docs/superpowers.
- **Mechanism:** two orthogonal layers — Claude Code permission layer
  (controls prompts) + harness hooks (enforce architectural discipline).
- **When to still ask:** ambiguous spec, discovered scope creep, tool
  failure with unclear recovery, architectural decision not in brainstorm.

### `.claude/settings.local.json` — correct auto-approve syntax

Changed from bare tool names to `(*)` patterns + `defaultMode: acceptEdits`:
```json
{
  "permissions": {
    "defaultMode": "acceptEdits",
    "allow": ["Edit(*)", "Write(*)", "Read(*)", "Bash(*)", "Grep(*)", "Glob(*)", "Agent(*)"]
  }
}
```

(File is gitignored — per-dev-machine config; the shared expectation
lives in CLAUDE.md.)

## Verification

100/100 harness tests green. No hook changes, so no behavioral
regressions expected or observed.

## Lessons

### Estimate accuracy

| Metric | Estimate | Actual | Delta |
|---|---|---|---|
| Files | 3 | 3 | ✅ |
| Lines | ~60 | +95 | +58% |
| Waves | 3 | 3 | ✅ |

Line gap: the "When the model should still ask" subsection emerged during
writing — not in the original plan but necessary to prevent the autonomy
clause from being read as "never ask anything." Honest escalation cases
had to be enumerated alongside the autonomy scope.

### Process gap — architectural

- **Two orthogonal systems (permissions + hooks) were partially
  conflated in prior thinking.** I had been treating every permission
  prompt as "the harness asking" when in fact permissions are a Claude
  Code tool-level concern, and hooks are a separate architectural
  enforcement layer. The Autonomy Contract now documents this
  separation explicitly, so future decisions about "where to enforce X"
  can pick the correct layer.

- **CLAUDE.md had an implicit contradiction** between "file edits are
  fine" and "always confirm scope-beyond" that resolved to over-asking.
  Writing down the boundary forced the contradiction to surface. Lesson:
  when two pieces of guidance compound into defensive behavior, there's
  probably an implicit contradiction worth explicit resolution.

### Process gap — mechanical

- **`settings.local.json` is gitignored.** Local-only config means this
  change applies only to the current dev environment. Other collaborators
  (or future machines) need to re-apply. Documented in the Autonomy
  Contract so the shared expectation is portable even if the local file
  isn't.

### Emergent patterns

- **Autonomy-contract-as-doc** — writing the boundary between
  human-decision and autonomous-execution as a bullet list in CLAUDE.md
  is more durable than implicit convention. If this pattern works here,
  similar contracts could be written for other ambiguous zones (e.g.,
  "when to create a new file vs extend an existing one").

- **Permission-hook separation** — orthogonal layers is already the
  pattern with settings vs hooks; this just makes it explicit in docs.

## Follow-ups

1. Re-evaluate after a few interactions whether the contract reduces
   prompt-fatigue in practice. If yes, possibly port a similar contract
   to `AGENTS.md` for subagent autonomy.
2. Consider whether `settings.json` (shared) should have a recommended
   baseline that new collaborators inherit, vs leaving everything to
   local.
3. If new tools enter the ecosystem (e.g., MCP servers with new
   permissions), update the `allow` list accordingly.
