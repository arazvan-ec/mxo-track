# Plan — I5b': Remove Deviation + Sync-Validator Working-Tree Fallback

**Spec:** `docs/superpowers/specs/2026-04-29-remove-deviation-and-sync-fallback-design.md`

## Phase 1: edit → verify (atomic)

### [parallel] Wave 1: Documentation edits (independent)
- **1a:** `CLAUDE.md` — delete § "Deviation for Wiring-Only Changes" + clean line 420 reference
  → files: `CLAUDE.md`
- **1b:** `.claude/README.md` — delete § "Deviation Mode" + schema docs
  → files: `.claude/README.md`

### [parallel] Wave 2: Hook deletions (independent files)
- **2a:** `.claude/hooks/session-start.sh` — remove `deviation` field from default state
  → files: `.claude/hooks/session-start.sh`
- **2b:** `.claude/hooks/workflow-engine.sh` — delete Gate 2 + DEVIATION_ACTIVE
  → files: `.claude/hooks/workflow-engine.sh`
- **2c:** `.claude/hooks/workflow-status.sh` — delete display logic
  → files: `.claude/hooks/workflow-status.sh`
- **2d:** `.claude/hooks/workflow-status-line.sh` — clean STATE_SIG + remove DEV_ACTIVE
  → files: `.claude/hooks/workflow-status-line.sh`
- **2e:** `.claude/hooks/user-prompt-state.sh` — remove DEV_ACTIVE read
  → files: `.claude/hooks/user-prompt-state.sh`
- **2f:** `.claude/hooks/post-bash-validator.sh` — delete Check 3 deviation block
  → files: `.claude/hooks/post-bash-validator.sh`
- **2g:** `.claude/hooks/pre-push-gate.sh` — collapse gate_or_warn to unconditional DENY
  → files: `.claude/hooks/pre-push-gate.sh`

### Wave 3: Sync-validator changes (independent)
- **3:** `.claude/hooks/validators/sync-validator.sh` — add working-tree fallback branch + remove "deviation/light" comment
  → files: `.claude/hooks/validators/sync-validator.sh`

### Wave 4: Verification (depende de 1-3)
- **4a:** Run `test-brainstorm-validator.sh` → 19/19 pass.
- **4b:** Run `test-sync-validator.sh` → 6/6 pass.
- **4c:** Run `test-pre-agent-check.sh` → 6/6 pass.
- **4d:** `bash -n` syntax check on all 11 modified files.
- **4e:** Visual check: `grep -rE 'deviation' .claude/hooks/ CLAUDE.md .claude/README.md` → empty.
- **4f:** Smoke: run sync-validator BEFORE committing — should pass on this interaction's plan via working-tree fallback.

## Estimación

| Métrica | Estimación |
|---|---|
| CLAUDE.md | -65 lines (delete section) |
| .claude/README.md | -25 lines |
| session-start.sh | -10 lines (remove deviation field) |
| workflow-engine.sh | -15 lines (Gate 2 + reads) |
| workflow-status.sh | -10 lines |
| workflow-status-line.sh | -5 lines |
| user-prompt-state.sh | -3 lines |
| post-bash-validator.sh | -30 lines (Check 3 block) |
| pre-push-gate.sh | -10 lines (collapse helper) |
| sync-validator.sh | +12 lines (fallback) -1 line (comment) |
| Total | net ~-160 lines (mostly deletions) |
| Files (incl artefactos) | 14 (11 modificados + spec + plan + log) |

## Done criteria

- [ ] Tests 31/31 pasan
- [ ] `bash -n` clean en 11 archivos
- [ ] `grep -rE 'deviation' .claude/hooks/ CLAUDE.md .claude/README.md` retorna vacío
- [ ] Smoke: sync-validator pasa en este plan SIN haber committeado
- [ ] Commit + push
