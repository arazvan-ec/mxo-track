# Spec — iOS Liquid Glass Preset (blanco + transiciones)

**Fecha:** 2026-04-13
**Tipo:** UI redesign — nuevo preset visual + motion
**Branch:** `claude/ios-style-transfers-whTh4`

## Resumen

Añadir un nuevo preset `ios` al sistema de theming existente que reproduzca el lenguaje visual **Liquid Glass de iOS 26** (blanco translúcido, hairlines, radios grandes, highlights especulares) **junto con un sistema de transiciones spring** estilo iOS aplicable a page transitions, BottomSheet, NavigationSidebar, popovers y microinteracciones.

El preset debe aplicarse tanto al **React SPA** (`/app/*`) como a las **páginas Twig legacy** (admin CRUD, customer portal) — ambos ya consumen `frontend/src/index.css` y las CSS variables.

## Existing Functionality Inventory

| Elemento | Ubicación | Decisión | Justificación |
|---|---|---|---|
| ThemeProvider (`mode` + `preset`) | `frontend/src/context/ThemeProvider.tsx` | **Transform** | Añadir `'ios'` a tipo `ThemePreset` y a `ALL_PRESETS`. Lógica inalterada |
| ThemeSwitcher popover | `frontend/src/components/ui/ThemeSwitcher.tsx` | **Transform** | Añadir entrada `ios` a `PRESET_META` con label "iOS" y colores del swatch |
| CSS vars `:root` / `.dark` | `frontend/src/index.css:22-90` | **Include** | No modificar — el preset añade su propia capa `.preset-ios` |
| Presets existentes (default/glass/command/bento/dense) | `index.css:93-192` | **Include** | Cero cambios. El nuevo preset se añade al final |
| `.theme-card` / `.theme-card-overlay` | `index.css:283-308` | **Include** | Se beneficia automáticamente de los tokens del preset vía CSS vars |
| `.glass-overlay` utility | `index.css:312-317` | **Include** | Base del efecto Liquid — el preset solo ajusta `--glass-blur/brightness/saturate/bg/border` |
| Badges (12 colores) | `index.css:194-267` | **Transform** | Añadir overrides dentro de `.preset-ios` para que usen tonos iOS más suaves (bg translúcido) |
| Animations (`fade-in-up`, `pulse-glow`, etc.) | `index.css:319-355` | **Include** | Conservar. El preset añade nuevas (`ios-sheet-rise`, `ios-push`, `ios-scale-in`) |
| BottomSheet | `frontend/src/components/bottom-sheet/` | **Transform** | Aplicar easing `--ease-ios` y duración `--dur-sheet` cuando el preset esté activo |
| NavigationSidebar | `frontend/src/components/layout/NavigationSidebar.tsx` | **Transform** | Usar `--ease-ios` y scrim con mayor translucidez cuando preset activo |
| TopBar | `frontend/src/components/layout/TopBar.tsx` | **Include** | Ya usa `.glass-overlay` — hereda el look automáticamente vía vars |
| MapLibre popups/controls | `index.css:357-400` | **Transform** | Añadir overrides bajo `.preset-ios` para hairlines y radio 14px |
| React Router (page transitions) | `frontend/src/router.tsx` | **Transform** | Envolver `<Outlet/>` en un wrapper `IOSPageTransition` que aplica animación cuando preset activo |
| Toast/alerts | `index.css:264-267` | **Include** | Heredan del preset vía vars |
| Plantillas Twig | `backend/templates/**` | **Include** | No tocar — ya usan CSS vars (log 2026-04-08). El preset funciona automáticamente |
| Tailwind CDN v3 | `base.html.twig` | **Include** | No afecta — las clases Tailwind no se alteran |

## Omission Decisions

| Elemento | Decisión | Justificación |
|---|---|---|
| Cambiar stack tipográfico global | **Omitir** | `-apple-system, BlinkMacSystemFont` ya está presente vía Tailwind reset. Añadir `font-feature-settings: 'ss01'` dentro del preset es suficiente para el toque iOS sin romper otras fuentes |
| Soporte dark para preset `ios` | **Incluir** (parcial) | iOS 26 tiene variante dark Liquid Glass — incluir `.preset-ios.dark` con tokens oscuros translúcidos. Pero el foco del usuario es **blanco**, así que la variante dark es "nice to have" y usará los mismos tokens base ajustados |
| Reemplazar SF Symbols icons | **Omitir** | Heroicons actuales son compatibles visualmente. Cambiar el set icónico es una feature aparte |
| Haptics / sound feedback | **Omitir** | Web no tiene haptics sólidos y es fuera de alcance visual |
| Modificar acento global fuera del preset | **Omitir** | El `#007AFF` aplica **solo dentro** de `.preset-ios` — preservando la identidad teal del proyecto cuando otro preset está activo |
| Añadir nueva librería de animaciones (Framer Motion, etc.) | **Omitir** | CSS transitions + keyframes cubren todo lo necesario. Menos peso de bundle |

## Decisión de contexto

**Bounded context:** Pragmático (UI / Infrastructure). No toca dominio. Las reglas DDD de `backend/CLAUDE.md` no aplican.

## Design Tokens — Preset `.preset-ios`

### Colores (light)

```css
.preset-ios {
  /* Surfaces — blanco translúcido capas */
  --color-surface: #f2f2f7;                          /* iOS grouped bg */
  --color-surface-elevated: rgba(255, 255, 255, 0.72);
  --color-surface-glass: rgba(255, 255, 255, 0.68);
  --color-surface-overlay: rgba(0, 0, 0, 0.35);

  /* Text — iOS SF Pro hierarchy */
  --color-text-primary: #000000;
  --color-text-secondary: rgba(60, 60, 67, 0.60);     /* secondaryLabel */
  --color-text-muted: rgba(60, 60, 67, 0.30);         /* tertiaryLabel */

  /* Hairlines — 0.5px separadores iOS */
  --color-border: rgba(60, 60, 67, 0.18);             /* separator */
  --color-border-subtle: rgba(60, 60, 67, 0.12);
  --color-border-accent: rgba(0, 122, 255, 0.35);

  /* Acento — iOS System Blue */
  --color-accent: #007AFF;
  --color-accent-bg: #007AFF;
  --color-accent-hover: #0051D5;
  --color-accent-muted: rgba(0, 122, 255, 0.12);

  /* Semantic iOS colors */
  --color-success: #34C759;   /* iOS systemGreen */
  --color-error: #FF3B30;     /* iOS systemRed */
  --color-warning: #FF9500;   /* iOS systemOrange */

  /* Glass tuning — Liquid Glass */
  --glass-blur: 28px;
  --glass-brightness: 1.08;
  --glass-saturate: 1.8;
  --glass-bg: rgba(255, 255, 255, 0.72);
  --glass-border: rgba(255, 255, 255, 0.6);

  /* Card tokens — radios iOS */
  --card-radius: 1.25rem;             /* 20px — cards */
  --card-border: 0.5px solid var(--color-border);
  --card-bg: rgba(255, 255, 255, 0.72);
  --card-blur: blur(24px) saturate(180%);
  --card-shadow:
    0 0 0 0.5px rgba(0, 0, 0, 0.04),
    0 1px 2px rgba(0, 0, 0, 0.04),
    0 8px 24px rgba(0, 0, 0, 0.06),
    inset 0 1px 0 rgba(255, 255, 255, 0.7);
  --card-hover-shadow:
    0 0 0 0.5px rgba(0, 0, 0, 0.06),
    0 2px 4px rgba(0, 0, 0, 0.06),
    0 12px 32px rgba(0, 0, 0, 0.10),
    inset 0 1px 0 rgba(255, 255, 255, 0.8);
  --card-padding: 1.25rem;
  --section-gap: 1.5rem;

  /* Motion tokens — iOS spring curves */
  --ease-ios: cubic-bezier(0.32, 0.72, 0, 1);
  --ease-ios-emphasized: cubic-bezier(0.2, 0, 0, 1);
  --dur-fast: 250ms;
  --dur-std: 400ms;
  --dur-sheet: 500ms;

  /* Typography tweak */
  font-feature-settings: 'ss01', 'ss02';
  letter-spacing: -0.01em;
}
```

### Dark variant (`.preset-ios.dark`)

Tokens simétricos con tonos iOS dark Liquid Glass:
- `--color-surface: #000000`
- `--color-surface-elevated: rgba(28, 28, 30, 0.72)`
- `--glass-bg: rgba(28, 28, 30, 0.64)`
- `--color-accent: #0A84FF` (iOS dark systemBlue)
- Shadows sin inset highlight blanco; usa `rgba(255,255,255,0.08)` mínimo

### Overrides de componentes

**Hairlines (0.5px):** Utility `.ios-hairline { border-width: 0.5px; border-color: var(--color-border); }` para divisores iOS puros dentro del preset.

**Badges en `.preset-ios`:** ajustar `--badge-*-bg` a tonos más translúcidos `rgba(X, Y, Z, 0.15)` para encajar con la estética glass.

**MapLibre popups dentro de `.preset-ios`:** `border-radius: 14px !important`, border `0.5px`, blur `24px saturate(180%)`.

## Motion — Animaciones iOS

### Keyframes

```css
@keyframes ios-sheet-rise {
  from { transform: translateY(100%); }
  to   { transform: translateY(0); }
}
@keyframes ios-scale-in {
  from { transform: scale(0.96); opacity: 0; }
  to   { transform: scale(1); opacity: 1; }
}
@keyframes ios-push-in {
  from { transform: translateX(16px); opacity: 0; }
  to   { transform: translateX(0); opacity: 1; }
}
@keyframes ios-fade-in {
  from { opacity: 0; }
  to   { opacity: 1; }
}
```

### Aplicación

| Componente | Transición | Duración/easing |
|---|---|---|
| Page transition (React Router) | `ios-push-in` + fade | `--dur-std` + `--ease-ios` |
| BottomSheet (open/state change) | `translateY` + spring | `--dur-sheet` + `--ease-ios` |
| NavigationSidebar drawer (mobile) | `translateX(-100%)→0` | `--dur-std` + `--ease-ios` |
| Popover / dropdown (ThemeSwitcher, etc.) | `ios-scale-in` | `--dur-fast` + `--ease-ios` |
| Button active state | `transform: scale(0.97)` | 120ms `ease-out` |
| Card hover | shadow transition 200ms (ya existe) | — |
| Toast appearance | `ios-fade-in` + translateY(8→0) | `--dur-std` + `--ease-ios` |

### Wrapper `IOSPageTransition`

Componente React que envuelve `<Outlet/>` en `router.tsx`. Detecta cambio de location y aplica `animation: ios-push-in`. Solo activo si `preset === 'ios'` (consulta el context). Fallback: renderiza children sin wrapper.

## UI del selector

`ThemeSwitcher.tsx` recibe la nueva entrada:
```ts
ios: { label: 'iOS', colors: ['#007AFF', 'rgba(255,255,255,0.72)', 'rgba(60,60,67,0.18)'] }
```

## Aprobación

Este diseño requiere confirmación del usuario antes de pasar a planning. Secciones de aprobación:

1. **Paleta de colores iOS** (surface, text, hairlines, accent #007AFF, semantic)
2. **Glass tokens** (blur 28px, saturate 1.8, brightness 1.08)
3. **Card radii** (20px card, 28px sheet, 14px control)
4. **Motion system** (4 keyframes + `--ease-ios` + aplicación componente por componente)
5. **ThemeSwitcher entry** + dark variant incluida
