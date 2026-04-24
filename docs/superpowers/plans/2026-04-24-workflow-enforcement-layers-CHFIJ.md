---
type: plan
feature: workflow-enforcement-layers-CHFIJ
date: 2026-04-24
spec: docs/superpowers/specs/2026-04-24-workflow-enforcement-layers-CHFIJ-design.md
---

# Plan — Workflow Enforcement Layers (C+H+F+I+J) + Agent Permission Correction

## Estimate

- **Files touched:** ~18 (3 new validators, 3 new tests, 1 new YAML, several extensions)
- **Lines:** ~700 total (code + tests + doc updates)
- **Waves:** 5
- **Agents dispatched:** 4 in Wave 1

## Phase 1 (v0) — Working implementation

### Wave 0 — Documentation correction (main session, sequential)

Objectives: fix the misdiagnosed "sandbox blocks `.claude/**`" narrative.

- **0a** — Rewrite the "Agent Permission Model" section of `AGENTS.md`:
  diagnosis is `classify-validator` blocking framework paths when
  `interaction_classification ∉ {full, debug}`; agents inherit via
  `session-state.json`.
- **0b** — Rewrite the adjacent-to-Layer-A subsection in
  `docs/knowledge/workflow-engine.md` with the corrected diagnosis.
- **0c** — Extend `pre-agent-check.sh`: if the current classification is
  not `full`/`debug` AND the agent prompt matches `.claude/` path keywords,
  emit a warning (non-blocking for now, documenting intent).

### [parallel] Wave 1 — Four agents, disjoint file surfaces

All four run as concurrent agents. Main session drives but does not edit
during this wave (checks progress only).

- **1a — Agent: C core (socratic_review phase)** · → files: `.claude/hooks/lib/flow-phases.sh`, `.claude/hooks/phase-advance.sh`, `.claude/hooks/validators/socratic-review-validator.sh`, `.claude/hooks/test-socratic-review-validator.sh`, `.claude/hooks/user-prompt-state.sh`, `.claude/hooks/workflow-status-line.sh`
  - TDD: write test first (~5 cases: 3-question floor, architectural keyword requirement, templated-answer detection, opt-out flag, soft success).
  - Insert `socratic_review` in FULL and DEBUG arrays.
  - Implement validator reading `evidence.socratic_questions`.
  - Update status-line renderers to display `(X/9)` for full and `(X/8)` for debug (arrays grow by 1).

- **1b — Agent: F core (edit-time DDD boundary)** · → files: `docs/knowledge/_ddd-boundaries.yaml`, `.claude/hooks/ddd-boundary-check.sh`, `.claude/hooks/test-ddd-boundary-check.sh`, `.claude/settings.json`
  - Create YAML per spec design.
  - New hook reads YAML, matches file_path against critical contexts, emits warning for new `createQueryBuilder` usage, blocks on edge cases (per spec).
  - Register hook in `settings.json` PreToolUse Edit|Write chain.
  - Test with fixtures: writing to known-violation file (allowed, flagged as legacy), writing to critical-context controller with new createQueryBuilder (blocked without spec audit), writing to non-critical path (allowed silently).

- **1c — Agent: H + J combined (brainstorm-validator extensions)** · → files: `.claude/hooks/validators/brainstorm-validator.sh`, `.claude/hooks/test-brainstorm-validator.sh`
  - **H:** detect critical-path mentions in spec, require `## Prior Art Audit` section.
  - **J:** load `_graduations.yaml` and soft-warn when spec mentions a non-graduated pattern name.
  - Test fixtures: spec without audit (H blocks), spec with empty audit table (H blocks), spec with valid audit (H passes), spec mentioning ungraduated pattern (J warns), spec mentioning graduated pattern (J silent).
  - Both extensions in one commit because they share the same file; sequential within this agent's execution.

- **1d — Agent: I (retrospective content gate)** · → files: `.claude/hooks/validators/retrospective-validator.sh`, `.claude/hooks/test-retrospective-validator.sh`
  - Extend validator with the architectural-keyword OR `evidence.retrospective_no_architectural_concerns=true` check.
  - Test: retrospective without keywords and no opt-out (blocks), retrospective with keyword (passes), retrospective with opt-out flag (passes).

### Wave 2 — Main integration (sequential)

After all 4 agents return, main session:

- **2a** — Merge any cross-file conflicts (if e.g. multiple agents update `workflow-engine.md` — unlikely given disjoint file surfaces).
- **2b** — Update `CLAUDE.md` 8-shortcuts table (add rows for C/H/F/I/J).
- **2c** — Update `.claude/README.md` phase evidence matrix (add `socratic_review` row, note H/F/I/J extensions).
- **2d** — Regenerate codebase manifest (`make manifest`).
- **2e** — Run full validator test suite, fix any cross-integration break.

### Wave 3 — Verification + Self-applied socratic_review

- **3a** — Run all validator tests (`test-flow-phases.sh`,
  `test-brainstorm-validator.sh`, `test-phase-advance-entry.sh`,
  `test-phase-advance.sh`, `test-phase-transition-controller.sh`,
  `test-enforcement-layers.sh`, new `test-socratic-review-validator.sh`,
  new `test-ddd-boundary-check.sh`, extended `test-retrospective-validator.sh`).
- **3b** — Run backend phpunit (regression: nothing in backend should be
  affected, but verify).
- **3c** — Run frontend `npm run build`.
- **3d** — **First application of `socratic_review` phase on itself.**
  Enter the phase, populate `evidence.socratic_questions` with ≥3
  architecturally-grounded questions about this very PR, verify the new
  validator accepts them. This is recursive dogfooding.

### Wave 4 — Capture + retro + finalize

- **4a** — Execution log `docs/superpowers/execution-logs/2026-04-24-workflow-enforcement-layers-CHFIJ.md`.
- **4b** — Retrospective with architectural-concern section (I will enforce this on itself).
- **4c** — Finalize: branch_strategy=keep, push.

## Phase 2 (Mature) — Not planned in this iteration

Phase 2 candidates (tracked, not implemented):
- AST-level PHP analysis for F (replace grep heuristics)
- Automatic decision-log template generation on bypass env var use
- Extend socratic_review to `light` flow when touching critical paths

## Acceptance checklist

- [ ] Wave 0 docs rewritten; old misdiagnosis replaced.
- [ ] `socratic_review` phase callable via `phase-advance.sh` in full and debug flows.
- [ ] All 5 new/extended validators pass their tests.
- [ ] No regression in the 6 existing validator test suites.
- [ ] Backend phpunit 677/677 (no regression; validators don't touch backend).
- [ ] Frontend build green.
- [ ] `socratic_review` successfully applied on this PR as first real use.
- [ ] Manifest updated.
- [ ] Retrospective contains architectural concerns section (I enforces).

## Capture plan

- Execution log per spec; includes per-wave retrospective rollup.
- Decision log entries in `docs/decisions/log.md`:
  - "Defense in depth chosen over single audit phase" (layered vs monolith).
  - "Grep heuristics for F over AST analysis" (pragmatic vs thorough).
  - "Correction of agent permission model misdiagnosis" (what changed and why).
- Graduation candidates tracking: split-by-path dispatch now at 4th
  occurrence (this PR) → graduate to knowledge module convention.
