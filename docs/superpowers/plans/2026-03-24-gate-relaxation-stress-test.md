# Implementation Plan: Gate Relaxation Stress Test

**Spec:** `docs/superpowers/specs/2026-03-24-gate-relaxation-stress-test-design.md`

## Tasks

- [ ] Task 1: Relax validators (exit 2 → exit 1)
  - Files: consult, brainstorm, planning, implementation, debug validators
  - Change all `exit 2` to `exit 1` (except verification-validator.sh)
  - Update error prefix from "BLOCKED" to "WARNING (SOFT)"

- [ ] Task 2: Create stress-test tracker
  - File: `docs/superpowers/stress-tests/gate-relaxation-tracker.md`
  - Create directory + scorecard template

- [ ] Task 3: Enhance workflow-status-line.sh
  - File: `.claude/hooks/workflow-status-line.sh`
  - Read evidence fields from session-state.json
  - Show per-phase evidence summary + current phase needs
  - Handle debug-flow evidence too

- [ ] Task 4: Update CLAUDE.md harness assumptions table
  - Update "Nivel" column for relaxed gates

- [ ] Task 5: Commit and push
