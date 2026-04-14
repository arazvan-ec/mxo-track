# Spec — Improve iOS Liquid Glass Effect

**Fecha:** 2026-04-14
**Tipo:** UI enhancement — mejorar autenticidad Liquid Glass
**Branch:** `claude/improve-ios-glass-theme-4FChy`

## Alternativas evaluadas

- **Approach A — Enhanced CSS Liquid Glass (structural effects).** **Seleccionado.** Noise texture via SVG feTurbulence, specular reflection gradient, rim glow. Zero JS, ~80 líneas CSS.
- **Approach B — Canvas/WebGL shader para refracción real.** Descartado. 200-300 líneas JS, render loop compite con MapLibre WebGL, Apple mismo no usa refracción en web.
- **Approach C — Toggle de intensidad.** **Seleccionado como add-on.** Clase `.glass-enhanced` que pushea valores (blur, saturate, opacity). Toggle en ThemeSwitcher, persiste en localStorage.

**Decisión del usuario:** Combinar A + C con toggle para desactivar C desde la app.

## Existing Functionality Inventory

| Elemento | Ubicación | Decisión | Justificación |
|---|---|---|---|
| `.preset-ios` tokens light | `index.css:421-487` | **Transform** | Añadir noise/reflection vars, ajustar glass-bg opacity |
| `.preset-ios.dark` tokens | `index.css:490-528` | **Transform** | Añadir noise/reflection vars dark |
| `.glass-overlay` utility | `index.css:312-317` | **Transform** | Añadir `position: relative` para pseudo-elements |
| `.theme-card` | `index.css:283-294` | **Transform** | Añadir `position: relative; overflow: hidden` para pseudo-elements |
| `ThemeProvider.tsx` | `frontend/src/context/ThemeProvider.tsx` | **Transform** | Añadir estado `glassEnhanced` + localStorage + clase en `<html>` |
| `ThemeSwitcher.tsx` | `frontend/src/components/ui/ThemeSwitcher.tsx` | **Transform** | Añadir toggle visible solo con preset iOS |

## Omission Decisions

| Elemento | Decisión | Justificación |
|---|---|---|
| Refracción WebGL | **Omitir** | Performance cost vs. visual gain no justifica |
| Toggle para otros presets | **Omitir** | Solo aplica a iOS preset, otros no tienen glass enhanced |
| Nuevo componente para toggle | **Omitir** | Inline en ThemeSwitcher existente |

## Design — CSS Effects

### Noise texture (pseudo-element `::before`)
```css
.preset-ios .glass-overlay::before,
.preset-ios .theme-card::before {
  content: "";
  position: absolute;
  inset: 0;
  background-image: var(--glass-noise);
  opacity: 0.035;
  mix-blend-mode: overlay;
  pointer-events: none;
  border-radius: inherit;
}
```

### Specular reflection (pseudo-element `::after`)
```css
.preset-ios .glass-overlay::after,
.preset-ios .theme-card::after {
  content: "";
  position: absolute;
  inset: 0;
  background: var(--glass-reflection);
  pointer-events: none;
  border-radius: inherit;
}
```

### Glass enhanced override
```css
.preset-ios.glass-enhanced {
  --glass-blur: 40px;
  --glass-brightness: 1.15;
  --glass-saturate: 2.2;
  --glass-bg: rgba(255, 255, 255, 0.55);
  --glass-border: rgba(255, 255, 255, 0.75);
}
```

### Card shadow enhancement (both modes)
```css
--card-shadow:
  0 0 0 0.5px rgba(0, 0, 0, 0.04),
  0 1px 2px rgba(0, 0, 0, 0.04),
  0 8px 24px rgba(0, 0, 0, 0.06),
  inset 0 1px 1px rgba(255, 255, 255, 0.8),
  inset 0 -1px 1px rgba(0, 0, 0, 0.02);
```

## Design — Toggle UX

- Switch en ThemeSwitcher, visible solo cuando `preset === 'ios'`
- Label: "Liquid Glass"
- Estado persistido en `localStorage` key `mxo-glass-enhanced`
- Clase `glass-enhanced` en `<html>` elemento
- Default: **off** (valores base conservadores)
