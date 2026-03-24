# Design Spec: Gate Relaxation Stress Test

**Date:** 2026-03-24
**Type:** Harness improvement
**Branch:** `claude/verify-app-improvements-Bg8gD`

## Problem

HARD gates encode assumptions about model limitations. Per Anthropic's harness design patterns article, these assumptions should be stress-tested when models improve. We need to relax HARD to SOFT for 5 tasks to measure compliance.

## Approach

Three pieces:

### Piece 1: Relax HARD gates to SOFT

Change `exit 2` to `exit 1` in validators that enforce phase ordering:
- `consult-validator.sh` — exit 2 to exit 1
- `brainstorm-validator.sh` — exit 2 to exit 1
- `planning-validator.sh` — exit 2 to exit 1
- `implementation-validator.sh` — exit 2 to exit 1
- `debug-validator.sh` — exit 2 to exit 1

**NOT relaxed (safety, not model-limitation assumptions):**
- `verification-validator.sh` — stays HARD (tests+lint is safety)
- Flow DENY gates (micro/light/explore editing src/) — stays DENY (classification)

### Piece 2: Stress-test tracker

Create `docs/superpowers/stress-tests/gate-relaxation-tracker.md` with:
- Per-task scorecard (5 tasks)
- Per-phase compliance tracking
- Decision criteria (>=90% to SOFT permanent, 70-89% to revert, <70% to revert)

### Piece 3: Enhanced status line (Option D)

Enhance `workflow-status-line.sh` to read ALL evidence fields and show:
- Which evidence backs each completed phase (abbreviated)
- What the current phase still needs
- Key counters: turns, tests_written, spec/plan basenames
- Deviation warnings

**Trade-offs:**
- Longer status line — acceptable, user explicitly chose completeness
- More jq calls in hook — negligible performance impact (~10ms)

**Alternatives descartadas:**
- A: No tracker — cannot evaluate. Descartada.
- B: Gate-by-gate relaxation — too slow. Descartada.
- C: JSON status line — over-engineering. Descartada.

## Existing Functionality Inventory

| Element | Status |
|---------|--------|
| workflow-engine.sh routing | Not changed (already routes to validators) |
| consult-validator.sh | Modified (exit 2 to exit 1) |
| brainstorm-validator.sh | Modified (exit 2 to exit 1) |
| planning-validator.sh | Modified (exit 2 to exit 1) |
| implementation-validator.sh | Modified (exit 2 to exit 1) |
| debug-validator.sh | Modified (exit 2 to exit 1) |
| verification-validator.sh | NOT modified (safety gate) |
| capture-validator.sh | NOT modified (already SOFT) |
| workflow-status-line.sh | Enhanced (evidence details) |
| CLAUDE.md harness assumptions table | Updated |

## Omission Decisions

No omissions — all inventory items addressed.
