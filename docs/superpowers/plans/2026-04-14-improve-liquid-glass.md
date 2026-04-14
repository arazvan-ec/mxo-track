# Plan — Improve iOS Liquid Glass Effect

**Spec:** `docs/superpowers/specs/2026-04-14-improve-liquid-glass-design.md`
**Branch:** `claude/improve-ios-glass-theme-4FChy`
**Estimado:** ~125 líneas netas, 3 archivos

## Fase 1 (v0): Working implementation

### Wave 1: CSS structural effects (Approach A)

**Tarea 1a — Noise + specular pseudo-elements en `index.css`**
- Añadir `position: relative` a `.glass-overlay` y `.theme-card`
- Añadir `overflow: hidden` a `.theme-card` (para contener pseudo-elements)
- Añadir `--glass-noise` y `--glass-reflection` vars a `.preset-ios` light y dark
- Crear reglas `::before` (noise) y `::after` (specular) para `.preset-ios .glass-overlay` y `.preset-ios .theme-card`
- Enhance card shadows con inset bottom edge
- → produce: efectos estructurales Liquid Glass visibles

**Tarea 1b — `.glass-enhanced` CSS override block en `index.css`**
- Bloque `.preset-ios.glass-enhanced` con blur, saturate, brightness, opacity pushados
- Bloque `.preset-ios.glass-enhanced.dark` con variantes dark
- → produce: CSS ready para toggle

### Wave 2: Toggle React (Approach C) — depende de Wave 1

**Tarea 2a — ThemeProvider: estado `glassEnhanced`**
- Añadir estado `glassEnhanced` con `useState` + `localStorage`
- Aplicar/remover clase `glass-enhanced` en `<html>` junto al preset
- Exponer `glassEnhanced` y `setGlassEnhanced` en context
- → produce: toggle funcional vía context

**Tarea 2b — ThemeSwitcher: UI del toggle**
- Añadir switch/checkbox visible solo cuando `preset === 'ios'`
- Label "Liquid Glass"
- Consumir `glassEnhanced`/`setGlassEnhanced` del context
- → produce: UX completa

### Wave 3: Verification

- `cd frontend && npm run build` (exact deploy command)
- Verificar 0 errores TypeScript
- Verificar CSS bundle size delta razonable

## Fase 2 (Mature): N/A — scope is small enough for single phase

## Success Criteria
- [ ] Noise texture visible en glass surfaces con preset iOS
- [ ] Specular highlight en borde superior de cards/overlays
- [ ] Toggle "Liquid Glass" en ThemeSwitcher activa/desactiva valores enhanced
- [ ] Toggle persiste en localStorage entre recargas
- [ ] Dark mode funciona correctamente con ambos modos
- [ ] `npm run build` pasa sin errores
