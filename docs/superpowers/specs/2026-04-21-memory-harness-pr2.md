# Spec — 2026-04-21 — Memory/Harness Workflow Improvements (PR2)

**Type:** process (workflow infrastructure)
**Branch:** `claude/improve-keyboard-shortcuts-Pnrqv`
**Phased:** sí — PR2 de 2. Consume el schema + consult.sh establecidos en PR1.
**Previous:** `docs/superpowers/specs/2026-04-19-memory-harness-improvements.md`

---

## Context

PR1 estableció el schema (YAML frontmatter) y `consult.sh`. PR2 construye los
consumidores de esa foundation: surfacing proactivo, pattern audit automatizado,
outcome tracking, regressions linking, y cierra la brecha detectada en la
retrospectiva de PR1 (user_approved no se preserva en new-day resume).

Aprobación del usuario (brainstorm 2026-04-21): D1a, D2a+b, D3b, D4a, D5b.

## Existing Functionality Inventory

| Elemento | Decisión | Justificación |
|---|---|---|
| `.claude/hooks/consult.sh` (PR1) | **Include** | Consumido por surfacing, pattern-audit, outcome tracking |
| `.claude/hooks/session-start.sh` | **Transform** | Extender con surfacing + user_approved restoration |
| `.claude/hooks/phase-advance.sh` | **Transform** | Hookear pattern-audit al transicionar a finalize |
| `.claude/hooks/post-commit-validator.sh` | **Transform** | Detectar `**Fixes previously:**` convención, llamar link-regression |
| Frontmatter schema (PR1) | **Include** | `outcome_verified_at` y `regressions_later` fields ya definidos |
| `docs/superpowers/templates/execution-log-template.md` | **Transform** | Añadir convención `**Fixes previously:** \`<log>\`` en retrospective |
| Execution logs backfilleados (PR1) | **Include** | Corpus sobre el que operan estos scripts |

## Omission Decisions

| Elemento considerado | Decisión | Justificación |
|---|---|---|
| GitHub Actions para auto-mark-verified | **Omit** | Requiere infra, fuera de scope local. El post-push hook local cubre el caso |
| Pattern-audit como HARD gate en finalize | **Omit** (D3b aprobado como SOFT) | Bloquear finalize por 3+ ocurrencias causa fricción; warning informa sin bloquear |
| Auto-tagging semántico | **Omit** | Sigue siendo YAGNI como en PR1 |
| Regressions link bidireccional inmediato | **Partial** | El commit añade `regressions_later` al log viejo; el `fixes_previously` queda implícito en el texto de la retro |
| Restaurar `user_turns` en new-day resume | **Omit** | Solo `user_approved` es la fricción real; `user_turns` se incrementa con el próximo mensaje del usuario |

## Design

### D1: Surfacing proactivo (session-start.sh)

**Trigger:** Cuando la branch actual ≠ main Y tiene ≤5 archivos modificados vs main.

**Lógica:**
1. `git diff --name-only main...HEAD` → lista de archivos tocados en el branch
2. Si count ≤ 5: para cada archivo, `consult.sh --quiet file <path>` (limitado a top 3 por archivo)
3. Deduplicar logs resultantes por filename
4. Emit sección `Related past logs (N):` en el output del hook si N > 0

**Threshold ≤5 archivos:** evita ruido en branches grandes. El modelo puede invocar `consult.sh file` on-demand para branches amplios.

**Ejemplo output:**
```
Related past logs for branch files (3):
  2026-04-14 | bugfix | success | fix-theme-switcher.md | Fix Theme Switcher
  2026-04-19 | bugfix | success | menu-scroll-overlay-fix.md | Menu Scroll + Overlay
  2026-04-13 | feature | success | ios-liquid-glass-preset.md | iOS Liquid Glass Preset
```

### D2: Outcome tracking (a + b)

**D2a — Manual script:** `scripts/mark-verified.sh <log-filename>`
- Setea `outcome_verified_at: <today>` en el frontmatter del log
- Idempotente: si ya tiene timestamp, no sobrescribe (a menos que `--force`)

**D2b — Auto-detection en session-start.sh:**
- Extiende la lógica que ya computa `merged_branches`
- Para cada merged branch, buscar log con ese branch en su body
- Si el log tiene `outcome: success` + `outcome_verified_at: null` + antigüedad ≥ 3 días desde merge → auto-setear timestamp
- Reporta en output: `Auto-verified N past logs (branches merged ≥3d ago)`

**Threshold 3 días:** margen para reportar regresiones antes de marcar "verified" automáticamente.

### D3: Pattern audit con hook a finalize

**Script:** `.claude/hooks/pattern-audit.sh`
- Wraps `consult.sh stats`, parsea las líneas con `⚠ PATTERN (≥3)`
- Cross-check contra knowledge modules existentes (grep tag en `docs/knowledge/*.md`)
- Output:
  - Tags con ≥3 logs NO mencionados en ningún knowledge module: "Candidate for graduation: `<tag>` (N logs)"
  - Tags con ≥3 logs ya graduados: silent (no-op)

**Hook en phase-advance.sh:** cuando la transición es `retrospective → finalize`, llama `pattern-audit.sh`. El output va a stderr como warning (no bloquea la transición).

### D4: Regressions link (post-commit hook)

**Convención:** en la sección retrospectiva del execution log nuevo, añadir línea:
```markdown
**Fixes previously:** `2026-04-12-xxx.md`
```

**Mecánica (D4a):**
- `scripts/link-regression.sh <new-log> <old-log>` — añade `<new-log>` al array `regressions_later` del `<old-log>` (idempotente)
- `.claude/hooks/post-commit-validator.sh` detecta nuevos execution logs con `**Fixes previously:**` en el commit, extrae el log referenciado, invoca `link-regression.sh`

**Resultado:** bidirectional link sin trabajo manual. `consult.sh` puede seguir la cadena de regresiones viejas → nuevas.

### D5: user_approved restoration (refactor)

**Problema:** el current `session-start.sh` tiene bloque inline que auto-restaura `user_approved` solo en same-day resume path.

**Refactor (D5b):**
- Extraer función `restore_approval_if_resumable()` que encapsula la lógica
- Invocar desde both same-day AND new-day paths
- Precondiciones: `spec_path` + `plan_path` existen + `current_phase` ∈ {implementation, verification, capture, retrospective, finalize}

Sin este cambio, cualquier pausa >1 día rompe el flow sin razón técnica.

## Aprobación

Usuario confirmó las 5 decisiones (D1a, D2a+b, D3b, D4a, D5b) en brainstorm 2026-04-21.

Paralelismo: Wave 1 dispatch 3 scripts independientes (pattern-audit, mark-verified, link-regression + template edit). Wave 2 secuencial (session-start.sh con #1+#5). Wave 3 paralelo (tests). Wave 4 wiring (hooks).
