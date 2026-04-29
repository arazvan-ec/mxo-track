# Plan — Hito 3 Phase B: ULS Vocabulary Consumers

**Spec:** `docs/superpowers/specs/2026-04-29-uls-phase-b-design.md`

## Phase 1: edit + verify

### Wave 1: B-1 subagent vocab scan
- **1:** Extend `pre-agent-check.sh` Gate 3. After
  Norms/Safeguards check, scan prompt tokens against
  `_vocabulary.yaml` aliases; emit `systemMessage` WARN for
  deprecated aliases.
  → files: `.claude/hooks/pre-agent-check.sh`

### Wave 2: B-2 ddd-boundary vocab cross-ref
- **2:** Extend `ddd-boundary-check.sh`. Scan spec for canonical
  mentions; compare `bounded_context` to path-inferred context;
  WARN on mismatch.
  → files: `.claude/hooks/ddd-boundary-check.sh`

### Wave 3: B-3 pattern-audit deprecated-alias
- **3:** Extend `pattern-audit.sh`. Scan recent logs for terms
  matching aliases with `surface: deprecated`; surface in audit.
  → files: `.claude/hooks/pattern-audit.sh`

### Wave 4: Verification
- **4a:** `bash -n` clean.
- **4b:** 31 existing tests pass.
- **4c:** Smoke B-1: simulate agent prompt mentioning "tour" →
  WARN with canonical "Route".
- **4d:** Smoke B-2: simulate spec mentioning a canonical with
  context mismatch → WARN.
- **4e:** Smoke B-3: pattern-audit on recent logs with deprecated
  aliases → surface in output.

## Estimación

| Métrica | Estimación |
|---|---|
| pre-agent-check.sh | +60 lines (vocab scan block) |
| ddd-boundary-check.sh | +50 lines (cross-ref logic) |
| pattern-audit.sh | +40 lines (alias scan) |
| Total net | ~150 lines code + spec/plan/log artefacts |
| Files (incl artefacts) | 6 |

## Done criteria

- [ ] Three integrations land
- [ ] All WARN-only (no new BLOCK gates)
- [ ] 31/31 tests pass
- [ ] Three smoke scenarios produce WARN at the right moment
- [ ] Commit + push
