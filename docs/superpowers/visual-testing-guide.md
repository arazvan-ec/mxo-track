# Visual Testing Guide — 5 Theme Presets

## Setup

```bash
cd frontend && npm run dev    # Starts Vite at http://localhost:5173
# Backend must be running at http://localhost:8000
```

Navigate to `/app/admin/dashboard` to start testing. The **ThemeSwitcher** is in the TopBar (palette icon, right side).

---

## Presets Overview

| Preset | Light Mode | Dark Mode | Key Visual Feature |
|--------|-----------|-----------|-------------------|
| **Default** | White cards, teal accent | Dark slate cards, teal accent | Clean, professional |
| **Glass** | Translucent white cards, blur | Translucent dark cards, blur | Frosted glass effect |
| **Command** | Dark navy (ignores light) | Deeper dark, cyan neon accents | Always dark, sci-fi feel |
| **Bento** | White cards, purple accent, large radius | Dark purple tint, large radius | Playful, rounded, warm |
| **Dense** | Compact spacing, monospace numbers | Compact dark, monospace | Maximum data density |

> **Note:** Command Center preset forces dark-like colors regardless of light/dark toggle. This is intentional for the "control room" aesthetic.

---

## Test Matrix (10 combinations)

For each combination below, verify the checklist on each page.

### 1. Default + Light
- [ ] Cards have white background, subtle shadow
- [ ] Text is dark on white — good contrast
- [ ] Teal accent on KPI bars, links, buttons

### 2. Default + Dark
- [ ] Cards have dark slate background
- [ ] Text is light on dark — good contrast
- [ ] Teal accent visible against dark

### 3. Glass + Light
- [ ] Cards have translucent white background
- [ ] `backdrop-filter: blur` visible (background bleeds through slightly)
- [ ] Card borders are subtle white/transparent
- [ ] Page background is slightly blue-gray (#e8edf5)

### 4. Glass + Dark
- [ ] Cards have translucent dark background
- [ ] Blur effect visible through cards
- [ ] Very subtle white border on cards (rgba 0.08)

### 5. Command + Light (same as dark)
- [ ] Deep navy background (#060a14)
- [ ] Cyan/sky blue accents
- [ ] All text is light (sky-50 to sky-400 range)
- [ ] Cards have subtle gradient and glow shadow
- [ ] Monospace font on KPI values and data

### 6. Command + Dark
- [ ] Even deeper background (#020408)
- [ ] Stronger cyan glow on card shadows
- [ ] Same overall feel as Command + Light

### 7. Bento + Light
- [ ] Warm off-white background (#faf8f6)
- [ ] Cards have large border-radius (1.5rem)
- [ ] Purple accent color
- [ ] Subtle purple-tinted borders (#f0eaff)
- [ ] Larger card padding (1.75rem)

### 8. Bento + Dark
- [ ] Dark purple-tinted background (#0c0a14)
- [ ] Cards have purple glow on hover
- [ ] Purple accent visible

### 9. Dense + Light
- [ ] Light gray background (#f1f3f5)
- [ ] Smaller card padding, tighter spacing
- [ ] Monospace font on all numbers
- [ ] Small border-radius (0.5rem)
- [ ] More data visible per screen

### 10. Dense + Dark
- [ ] Very dark background (#0a0c10)
- [ ] Compact cards with minimal shadow
- [ ] Maximum information density

---

## Per-Page Checklist

### Admin Dashboard (`/app/admin/dashboard`)
- [ ] Greeting header shows correct time-of-day greeting
- [ ] 4 KPI cards animate counters on load
- [ ] System health radial gauges show correct colors (green/amber/red)
- [ ] 7-day delivery bars animate upward with stagger
- [ ] Top drivers list shows progress bars
- [ ] Infrastructure cards show progress bars
- [ ] Reports banner gradient is visible
- [ ] All sections have staggered fade-in entrance

### Fleet Map (`/app/admin/fleet-map`)
- [ ] FleetSidebar background adapts to theme
- [ ] KPI pills use `.theme-card-overlay` (translucent over sidebar)
- [ ] Vehicle/Route list items use overlay styling when unselected
- [ ] Selected items have blue highlight regardless of preset
- [ ] Tab switcher (Vehicles/Routes) is theme-aware
- [ ] Search input adapts to theme
- [ ] Map popups (vehicle, stop) are readable

### Operator Dashboard (`/app/admin/operator-dashboard`)
- [ ] Bottom sheet widgets adapt to theme
- [ ] MetricPairs use `.theme-card-overlay`
- [ ] Map legend uses `.theme-card-overlay`

### Route Planner (`/app/admin/route-planner`)
- [ ] Form inputs adapt to theme colors
- [ ] Preview route cards are theme-aware
- [ ] Map overlay buttons adapt

---

## Known Issues

1. **Command Center ignores light/dark toggle** — by design, always dark
2. **Glass preset on low-end devices** — `backdrop-filter: blur` can be GPU-intensive
3. **Dense preset may clip long text** — reduced padding means less room for labels

---

## How to Report Issues

For each visual bug found:
1. Note the **preset + mode** combination (e.g., "Bento + Dark")
2. Note the **page** and **component** (e.g., "Fleet Map → VehicleList")
3. Describe the issue (e.g., "text unreadable — white on light background")
4. Screenshot if possible
