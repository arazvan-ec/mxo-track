# Plan — I10: Retrospective_shown Verbal-Approval Gate

**Spec:** `docs/superpowers/specs/2026-04-29-retrospective-shown-verbal-approval-gate-design.md`

## Phase 1: edit + verify

### Wave 1: user-prompt-state.sh approval block
- **1:** Insert parallel approval block after the existing
  `user_approved` block (line ~94). Gate on
  `current_phase=retrospective`. Reuse approval regex. Set
  `retrospective_shown=true` if approval matches and current is
  false. Sync snapshot via `cp`.
  → files: `.claude/hooks/user-prompt-state.sh`

### Wave 2: phase-transition-controller.sh revert block
- **2:** Insert parallel revert block after existing `user_approved`
  check. Same OLD/NEW/COMMAND pattern, target field
  `retrospective_shown`.
  → files: `.claude/hooks/phase-transition-controller.sh`

### Wave 3: CLAUDE.md autonomy contract update
- **3:** Add a sentence noting `retrospective_shown` is hook-managed
  (parallel to existing `user_approved` exception).
  → files: `CLAUDE.md`

### Wave 4: Verification
- **4a:** `bash -n` clean.
- **4b:** 31 existing tests pass (no regression).
- **4c:** Smoke: present this interaction's retro, attempt finalize
  WITHOUT verbal approval → blocked.
- **4d:** Smoke: user approval → hook sets flag → finalize passes.
- **4e:** Counter-smoke: direct jq write of
  `retrospective_shown=true` while OLD=false → controller reverts.

## Estimación

| Métrica | Estimación |
|---|---|
| user-prompt-state.sh | +12 lines |
| phase-transition-controller.sh | +12 lines |
| CLAUDE.md | +2 lines |
| Total net | ~26 lines |
| Files (incl artefactos) | 5 |

## Done criteria

- [ ] Hook detects retro approval in correct phase
- [ ] Controller reverts direct jq writes
- [ ] CLAUDE.md updated
- [ ] 31/31 tests pass
- [ ] Smoke: retro requires verbal approval
- [ ] Smoke: jq cheating reverted
- [ ] Commit + push
