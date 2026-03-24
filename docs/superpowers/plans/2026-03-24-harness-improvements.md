# Plan: Harness Design Improvements

**Goal:** Implementar las 7 mejoras al harness basadas en el artículo de Anthropic sobre harness design.
**Spec:** `docs/superpowers/specs/2026-03-24-harness-improvements-design.md`
**Branch:** `claude/review-harness-design-patterns-ISG57`

---

## File Structure

```
Files to modify:
  CLAUDE.md                                          # 6 edits (A-F)
  .claude/hooks/workflow-engine.sh                   # 1 edit (G)
  .claude/hooks/validators/brainstorm-validator.sh   # 1 edit (H)
```

## Parallelization Strategy

All 8 edits touch 3 files. Edits within the same file MUST be sequential. Edits across different files CAN be parallel.

```
PARALLEL GROUP 1 (different files):
  ├── Agent 1: Edit G (workflow-engine.sh) + Edit H (brainstorm-validator.sh)
  └── Agent 2: Edits A, B, C, D, E, F (all CLAUDE.md edits, sequential)

SEQUENTIAL after both complete:
  └── Final: verify consistency, commit, push
```

**Effective: 2 parallel agents → 1 final step**

---

## Tasks

### - [ ] Task 1: Edit G — Relax Scope Change gate (workflow-engine.sh)

**File:** `.claude/hooks/workflow-engine.sh`
**Lines:** 84-86

**Find:**
```bash
    */backend/src/*|*/frontend/src/*|*/backend/tests/*|*/frontend/tests/*)
      deny "WORKFLOW ENGINE: Scope change detectado (interaction_id: $CURRENT_INTERACTION, evidence: $EVIDENCE_INTERACTION). Resetea evidence.interaction_id=$CURRENT_INTERACTION y completa las fases requeridas."
      ;;
```

**Replace with:**
```bash
    */backend/src/*|*/frontend/src/*|*/backend/tests/*|*/frontend/tests/*)
      warn "WORKFLOW ENGINE (SOFT): Scope change detectado (interaction_id: $CURRENT_INTERACTION, evidence: $EVIDENCE_INTERACTION). Resetea evidence.interaction_id=$CURRENT_INTERACTION y completa las fases requeridas."
      ;;
```

**Verify:** `grep -n "SOFT.*Scope change" .claude/hooks/workflow-engine.sh`

---

### - [ ] Task 2: Edit H — Relax brainstorm user_turns (brainstorm-validator.sh)

**File:** `.claude/hooks/validators/brainstorm-validator.sh`

**Find (lines 16-20):**
```bash
ERRORS=""

if [ "$USER_TURNS" -lt 3 ]; then
  ERRORS="${ERRORS}- Brainstorming requiere >= 3 turnos de dialogo (actual: $USER_TURNS)\n"
fi
```

**Replace with:**
```bash
ERRORS=""
WARNINGS=""

if [ "$USER_TURNS" -lt 1 ]; then
  ERRORS="${ERRORS}- Brainstorming requiere al menos 1 turno de dialogo con el usuario (actual: $USER_TURNS)\n"
fi

# Soft warning for < 3 turns (relaxed from HARD >= 3 per harness evolution 2026-03-24)
if [ "$USER_TURNS" -ge 1 ] && [ "$USER_TURNS" -lt 3 ]; then
  WARNINGS="${WARNINGS}- SOFT: Brainstorming con $USER_TURNS turno(s). Considera mas dialogo si el scope es complejo.\n"
fi
```

**Also find (at end of file, lines 67-74):**
```bash
if [ -n "$ERRORS" ]; then
  echo "BLOCKED: Brainstorming incompleto:"
  echo -e "$ERRORS"
  echo "Completa el brainstorming (Skill 2) antes de continuar."
  exit 2
fi

exit 0
```

**Replace with:**
```bash
if [ -n "$ERRORS" ]; then
  echo "BLOCKED: Brainstorming incompleto:"
  echo -e "$ERRORS"
  echo "Completa el brainstorming (Skill 2) antes de continuar."
  exit 2
fi

# Soft warnings (exit 1 = warn but allow)
if [ -n "$WARNINGS" ]; then
  echo -e "$WARNINGS"
  exit 1
fi

exit 0
```

**Verify:** `bash -n .claude/hooks/validators/brainstorm-validator.sh` (syntax check) + `grep "user_turns" .claude/hooks/validators/brainstorm-validator.sh`

---

### - [ ] Task 3: Edit A — Add "Harness Assumptions & Evolution" section (CLAUDE.md)

**File:** `CLAUDE.md`
**Location:** After line 672 (end of "Anti-patterns" list in Workflow Engine section), before line 674 ("## Automatic Session Context")

**Insert this section between the two:**

```markdown
## Harness Assumptions & Evolution (mandatory)

**Principio:** "Every component in a harness encodes assumptions about model limitations worth stress-testing." — Anthropic, 2025

Cada mecanismo del harness codifica una asunción sobre limitaciones del modelo. Estas asunciones deben revisarse con cada cambio de modelo.

### Inventario de asunciones

| Componente | Asunción | Nivel | Última validación |
|---|---|---|---|
| Workflow engine HARD gates | Claude se salta fases sin enforcement mecánico | HARD | 2026-03-24 (baseline) |
| Anti-rationalization tables | Claude inventa excusas para saltarse pasos | Docs | 2026-03-24 (consolidated) |
| Brainstorm `user_turns ≥ 1` + SOFT `< 3` | Claude puede no conversar suficiente | SOFT | 2026-03-24 (relaxed from HARD ≥ 3) |
| `session-state.json` evidencia granular | Estado externo necesario cross-session | HARD | 2026-03-24 (validated: necessary) |
| Subagent output limits (300 líneas) | Subagentes producen output excesivo | Docs | 2026-03-24 (pending stress-test) |
| Pre-Exploration Gate | Claude explora redundantemente sin manifest | Docs | 2026-03-24 (validated: saves tool calls) |
| Scope Change Detection | Claude mezcla tareas sin detectar scope change | SOFT | 2026-03-24 (relaxed from HARD) |
| Atomic commits | Se pierde trabajo en sesiones largas | Docs | 2026-03-24 (validated: safety reason) |

### Niveles de enforcement

- **HARD** — Bloquea la acción (exit 2). Para asunciones validadas como necesarias.
- **SOFT** — Warning pero permite continuar (exit 1). Para asunciones en transición.
- **Docs** — Best practice documentada, sin enforcement mecánico.
- **Removed** — Asunción obsoleta, mecanismo eliminado.

### Modelo de evolución

```
HARD → (stress-test: 5 tareas, ≥90% compliance) → SOFT → (10 tareas, ≥95%) → Docs → Remove
```

### Schedule de review

- **Trigger:** Cada cambio de modelo base (e.g., Opus 4.6 → 5.0)
- **Proceso:** 5 tareas reales con gate relajado un nivel, medir compliance
- **Registro:** Actualizar columna "Última validación" con fecha y resultado
```

**Verify:** `grep -n "Harness Assumptions" CLAUDE.md`

---

### - [ ] Task 4: Edit B — Add "Context Hygiene" section (CLAUDE.md)

**File:** `CLAUDE.md`
**Location:** After the "Automatic Session Context" section ends (after the "Regla:" line ~699), before "## Automatic Status Line"

**Insert:**

```markdown
## Context Hygiene (mandatory)

**Principio:** Las sesiones largas degradan calidad. Los checkpoints estructurados preservan el progreso.

### Reglas

1. **Checkpoint en sesiones largas:** Después de ~50 tool calls o al notar compactación, hacer checkpoint: commit + push + actualizar session-state.
2. **División de tareas grandes:** Si una tarea tiene más de ~8 pasos de implementación, considerar dividir en sesiones separadas.
3. **Post-compactación:** Verificar acceso a spec (`evidence.spec_path`), plan (`evidence.plan_path`), y estado de tareas. Si no accesible → releer antes de continuar.
4. **Handoff estructurado:** Al sugerir nueva sesión, documentar en session-state qué tareas están completadas, cuál está en progreso, y qué decisiones se tomaron.
```

**Verify:** `grep -n "Context Hygiene" CLAUDE.md`

---

### - [ ] Task 5: Edit C — Reduce flow anti-rationalization table (CLAUDE.md)

**File:** `CLAUDE.md`
**Lines:** 511-520

**Find:**
```markdown
### Anti-racionalizaciones

| Pensamiento | Realidad |
|-------------|----------|
| "Es un cambio de una línea" | Los cambios de una línea rompen producción. Full-flow. |
| "Ya sé la respuesta" | La consulta revela lo que no sabes que no sabes. |
| "El micro-flow es overkill para esta pregunta" | 10 segundos de consulta nunca son overkill. |
| "Saltemos brainstorming, la solución es obvia" | Las soluciones "obvias" que saltan brainstorming son las que pierden edge cases. |
| "Nadie va a leer la retrospectiva" | Las futuras instancias de Claude sí la leerán. Ese es el learning loop. |
| "Es solo una extensión del feature actual" | Si no está en el plan, es scope change. Incrementar interaction_id. |
```

**Replace with:**
```markdown
### Anti-racionalizaciones

| Pensamiento | Realidad |
|-------------|----------|
| "Es un cambio de una línea" | Los cambios de una línea rompen producción. Full-flow. |
| "Saltemos brainstorming, la solución es obvia" | Las soluciones "obvias" que saltan brainstorming son las que pierden edge cases. |
| "Es solo una extensión del feature actual" | Si no está en el plan, es scope change. Incrementar interaction_id. |
```

**Verify:** Count rows in the table — should be 3 (not 6).

---

### - [ ] Task 6: Edit D — Reduce Skill 1 Red Flags table (CLAUDE.md)

**File:** `CLAUDE.md`
**Lines:** 1019-1028

**Find:**
```markdown
#### Red Flags (rationalizations to STOP)

| Thought | Reality |
|---------|---------|
| "This is just a simple question" | Questions are tasks. Check for skills. |
| "I need more context first" | Skill check comes BEFORE clarifying questions. |
| "Let me explore the codebase first" | Skills tell you HOW to explore. Check first. |
| "This doesn't need a formal skill" | If a skill exists, use it. |
| "The skill is overkill" | Simple things become complex. Use it. |
| "I'll just do this one thing first" | Check BEFORE doing anything. |
```

**Replace with:**
```markdown
#### Red Flags (rationalizations to STOP)

| Thought | Reality |
|---------|---------|
| "This is just a simple question" | Questions are tasks. Check for skills. |
| "This doesn't need a formal skill" | If a skill exists, use it. |
| "I'll just do this one thing first" | Check BEFORE doing anything. |
```

**Verify:** Count rows — should be 3 (not 6).

---

### - [ ] Task 7: Edit E — Add Sprint Contract + Checkpoint Reviews to Skill 5 (CLAUDE.md)

**File:** `CLAUDE.md`
**Location:** After line 1247 (end of "Red Flags" in Skill 5), before line 1249 (`---` separator before Skill 6)

**Insert before the `---`:**

```markdown

#### Sprint Contract Pattern

Al despachar cada implementador, incluir acceptance criteria explícitos:

```
## Task: [nombre de la tarea del plan]
## Acceptance Criteria
- [ ] [Criterio específico y verificable extraído del plan]
- [ ] [Criterio específico y verificable]
- [ ] No introduce código innecesario para la tarea
- [ ] Tests cubren el comportamiento nuevo/modificado
```

Estos criteria se incluyen en el prompt del implementador Y del spec compliance reviewer. El reviewer verifica cada criterio y reporta PASS/FAIL por item.

#### Checkpoint Reviews (features XL)

Para features XL (>10 tareas o >5 archivos):

1. **Mid-implementation review:** Al ~50% de las tareas, despachar reviewer con el spec + archivos implementados. Pregunta: "¿Dirección coherente con spec? ¿Desvíos? ¿Calidad?"
2. **Acción sobre feedback:** Corregir desvíos antes de continuar
3. **No reemplaza** el review por tarea — es verificación adicional de coherencia global
```

**Verify:** `grep -n "Sprint Contract" CLAUDE.md` + `grep -n "Checkpoint Reviews" CLAUDE.md`

---

### - [ ] Task 8: Edit F — Update Validators table (CLAUDE.md)

**File:** `CLAUDE.md`
**Line:** 645

**Find:**
```markdown
| `brainstorming` | `user_turns ≥ 3` + `alternatives_proposed` + `user_approved` + `spec_path` (archivo ≥500B con keywords) | HARD |
```

**Replace with:**
```markdown
| `brainstorming` | `user_turns ≥ 1` (HARD) + SOFT warning si `< 3` + `alternatives_proposed` + `user_approved` + `spec_path` (archivo ≥500B con keywords) | MIXED |
```

**Verify:** `grep "user_turns" CLAUDE.md`

---

## Execution Order

```
Phase 1 (parallel — 2 agents):
  Agent A: Tasks 1, 2  →  workflow-engine.sh + brainstorm-validator.sh
  Agent B: Tasks 3, 4, 5, 6, 7, 8  →  all CLAUDE.md edits (sequential within agent)

Phase 2 (sequential):
  Final verification: grep all changes, syntax-check hooks
  Commit + push
```
