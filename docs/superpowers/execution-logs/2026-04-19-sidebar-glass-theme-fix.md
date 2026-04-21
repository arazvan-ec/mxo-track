---
type: bugfix
tags: [glass-overlay, sidebar, theme]
files_touched: [docs/knowledge/ui-frontend.md, frontend/src/components/layout/NavigationSidebar.tsx, frontend/src/hooks/useAdaptiveOpacity.ts]
patterns: []
outcome: null
outcome_verified_at: null
regressions_later: []
pr_number: null
estimated_lines: null
actual_lines: 11
duration_minutes: null
consulted_in_future: []
---

# Execution Log — 2026-04-19 — Sidebar Glass Theme Fix

**Type:** debug (bug fix)
**Branch:** `claude/fix-ios-menu-background-LEV7f`
**Report:** Usuario reporta que en tema iOS el menú mobile tiene fondo blanco opaco que oculta la vista de fondo (screenshot adjunto). Tras investigación, el usuario confirma que ocurre en todos los temas.

## Root cause

`NavigationSidebar.tsx:173-178` sobrescribía incondicionalmente dos variables CSS en modo overlay (mobile):
- `--glass-brightness: String(brightnessValue)` — valor del hook `useAdaptiveOpacity`, que devolvía `DEFAULT_BRIGHTNESS = 0.3` cuando no había canvas MapLibre.
- `--glass-saturate: '0.3'` — hardcoded.

Ambos valores estaban diseñados para oscurecer/desaturar el backdrop cuando el sidebar aparece sobre un mapa brillante (legibilidad). En páginas **sin mapa** (dashboard, listados, menú), estos overrides seguían aplicando:

- `backdrop-filter: brightness(0.3) saturate(0.3)` dimea y desatura lo que haya detrás.
- Encima, `background: rgba(255,255,255, ~0.7)` del preset activo pinta un blanco translúcido.
- Resultado visual: blanco grisáceo cercano a opaco (se pierde la transparencia porque el backdrop está tan dimeado que no aporta contraste).

Afectaba a todos los presets porque todos definen `--color-surface-glass` o `--glass-bg` cercano a blanco translúcido, y el filtro oscurece cualquier backdrop.

## Why it wasn't caught earlier

El commit original (`8ac47c3 feat: add adaptive glass effect to NavigationSidebar`) fue probado sobre páginas de mapa, donde los valores 0.3/0.3 son correctos. El componente, sin embargo, se usa en toda la app. El hook `useAdaptiveOpacity` tenía un "fallback" (`DEFAULT_BRIGHTNESS`) que en realidad replicaba el comportamiento map-oriented en vez de ser neutro.

## Fix

**`frontend/src/hooks/useAdaptiveOpacity.ts`:**
- Cambiar retorno a `{ brightnessValue: number | null }`.
- Devolver `null` cuando `isOpen === false` o no hay canvas MapLibre.
- Conservar el cálculo adaptativo cuando sí hay canvas (comportamiento original sobre mapas).

**`frontend/src/components/layout/NavigationSidebar.tsx`:**
- Eliminar `--glass-blur: 24px` (que el tema decida).
- Eliminar `--glass-saturate: '0.3'` hardcoded.
- Inyectar `--glass-brightness` solo cuando el hook reportó un valor real (spread condicional).
- Conservar `--glass-border: var(--color-border-accent)` (choice de diseño del sidebar).

## Files changed

- `frontend/src/components/layout/NavigationSidebar.tsx` (+2 / -4)
- `frontend/src/hooks/useAdaptiveOpacity.ts` (+9 / -4)

## Verification

- `cd frontend && npm run build` (= `tsc -b && vite build`): ✅ 241 módulos, 6.48s, sin errores.
- Pattern-wide: `BottomSheet.tsx` y `TopBar.tsx` no sobrescriben `--glass-brightness`/`--glass-saturate` — el patrón correcto ya existía, el sidebar era el único fuera de línea.

## Lessons

1. **Hooks adaptativos deben tener un "off" literal, no un default que active el modo.** `DEFAULT_BRIGHTNESS=0.3` era un default map-oriented que se aplicaba fuera de contexto map. Correcto: `null` cuando no aplica.
2. **Tercer fix iOS-theme-interference.** Previous: `e5cc076` (overflow:hidden), `a029e14` (absolute positioning). Si aparece un cuarto, promover a `docs/knowledge/ui-frontend.md` la regla: "no sobrescribir `--glass-*` salvo que la feature lo requiera Y esté activa en ese momento".
3. **Componentes compartidos entre páginas de mapa y no-mapa** necesitan que cualquier override condicional al contexto (brightness adaptativa, canvas sampling) se active **solo cuando el contexto existe**, no como default.
