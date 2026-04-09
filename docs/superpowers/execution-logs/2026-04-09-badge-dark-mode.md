# Execution Log — 2026-04-09 — Theme-Aware Badge Colors

**Type:** code change (UI/CSS)
**Branch:** `claude/improve-views-claude-md-NiE60`

## Brainstorming

- **Alternativas:** (A) CSS custom properties + utility classes, (B) Twig macro
- **Elegida:** Opción A — menor impacto en estructura HTML de templates
- **Complejidad estimada:** Media (muchos archivos, cambio mecánico repetitivo)

## Planning

- **Tareas:** 4 waves (CSS infra → bulk replace → edge cases → verify)
- **Archivos afectados:** 31 templates + 1 CSS
- **Estimación:** ~180 reemplazos en badges

## Implementation

- **Wave 1:** Agregados 12 badge tokens + 5 soft + 4 toast a `index.css` (75 líneas nuevas)
- **Wave 2:** Bulk replace con `perl` + negative lookbehind:
  - Step 1: `bg-COLOR-100` → `badge-COLOR` (skip `group-hover:` prefixes)
  - Step 2: Remove `text-COLOR-{shade}` condicionalmente en líneas con `badge-COLOR`
  - Resultado: 0 falsos positivos (3 `group-hover:bg-*-100` correctamente preservados)
- **Wave 3:** Edge cases manuales:
  - Toasts en `base.html.twig` → `.toast-*` classes con `--tw-ring-color`
  - Notification badges → `.badge-soft-*`
  - Driver, import, route_template badges → `.badge-soft-*`
- **Blocker:** perl `/pattern/s///` syntax causó "Illegal division by zero" — fix: usar `s///g if /pattern/` syntax

## Verification

- TypeScript: ✅ (tsc -b clean)
- Vite build: ✅ (6.93s, CSS 15 kB gzip)
- Remaining hardcoded: Solo 3 `group-hover:bg-*-100` (correct, son hover effects)

## Deviations from Plan

- **Spec y plan no fueron escritos antes de implementar** — se escribieron post-hoc en capture phase
- El flujo se ejecutó correctamente en sustancia pero se saltaron los artefactos formales

## Lessons Learned

1. **perl regex syntax matters:** `/pattern/s///` no funciona en `-e`, usar `s///g if /pattern/`
2. **Negative lookbehind es clave para bulk CSS replacement:** `(?<!:)bg-COLOR-100` evita romper `group-hover:` y `hover:` prefixes
3. **Los artefactos del flujo (spec/plan) deben escribirse ANTES de implementar**, no post-hoc — el usuario lo detectó y pidió completar los pasos faltantes
