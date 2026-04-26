---
type: plan
feature: autonomy-contract-claude-md-permissions
spec: docs/superpowers/specs/2026-04-24-autonomy-contract-design.md
date: 2026-04-24
---

# Plan — Autonomy Contract

## Estimate

- 3 files touched: `CLAUDE.md`, `.claude/settings.local.json`, plus spec + plan + log
- ~60 lines net code/config
- 1 wave, sequential
- No test changes

## Wave 1 — Implementation

- **1a** — Add `## Autonomy Contract` section to `CLAUDE.md` near the
  "Executing actions with care" section. Add a 1-line reference from the
  existing section to the new one for concrete boundaries.
- **1b** — Update `.claude/settings.local.json` to the correct
  auto-approve syntax (`defaultMode: acceptEdits` preferred, `Edit(*)`
  fallback if not supported).
- **1c** — Regenerate manifest.

## Wave 2 — Verification

- Run full harness test suite; all 100 should remain green (no hook
  changes).
- Eyeball the modified sections of CLAUDE.md for coherence.

## Wave 3 — Capture / retro / finalize

- Execution log.
- Retrospective with architectural-concern content (Layer I requirement).
- Commit + push.

## Acceptance checklist

- [ ] `CLAUDE.md` has an Autonomy Contract section with two lists.
- [ ] `.claude/settings.local.json` uses correct format (auto-approve
      active for Edit/Write without per-call prompts).
- [ ] Harness tests remain 100/100 green.
- [ ] Commit + push clean.

## Non-goals

- Changes to hooks or validators.
- Changes to `settings.json` (shared).
- New tests.
