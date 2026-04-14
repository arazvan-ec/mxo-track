import { createContext, useContext, useEffect, useState, useCallback, type ReactNode } from 'react';

type ThemeMode = 'light' | 'dark' | 'system';
type ResolvedTheme = 'light' | 'dark';
type ThemePreset = 'default' | 'glass' | 'command' | 'bento' | 'dense' | 'ios';

interface ThemeContextValue {
  mode: ThemeMode;
  resolved: ResolvedTheme;
  preset: ThemePreset;
  glassEnhanced: boolean;
  setMode: (mode: ThemeMode) => void;
  setPreset: (preset: ThemePreset) => void;
  setGlassEnhanced: (v: boolean) => void;
  toggle: () => void;
}

const ThemeContext = createContext<ThemeContextValue | null>(null);

const STORAGE_KEY = 'mxo-theme';
const PRESET_KEY = 'mxo-theme-preset';
const GLASS_KEY = 'mxo-glass-enhanced';
const ALL_PRESETS: ThemePreset[] = ['default', 'glass', 'command', 'bento', 'dense', 'ios'];

function getSystemTheme(): ResolvedTheme {
  if (typeof window === 'undefined') return 'dark';
  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function resolveTheme(mode: ThemeMode): ResolvedTheme {
  return mode === 'system' ? getSystemTheme() : mode;
}

export function ThemeProvider({ children }: { children: ReactNode }) {
  const [mode, setModeState] = useState<ThemeMode>(() => {
    if (typeof window === 'undefined') return 'system';
    return (localStorage.getItem(STORAGE_KEY) as ThemeMode) ?? 'system';
  });

  const [preset, setPresetState] = useState<ThemePreset>(() => {
    if (typeof window === 'undefined') return 'default';
    const stored = localStorage.getItem(PRESET_KEY) as ThemePreset | null;
    return stored && ALL_PRESETS.includes(stored) ? stored : 'default';
  });

  const [glassEnhanced, setGlassEnhancedState] = useState<boolean>(() => {
    if (typeof window === 'undefined') return false;
    return localStorage.getItem(GLASS_KEY) === 'true';
  });

  const resolved = resolveTheme(mode);

  const setMode = useCallback((m: ThemeMode) => {
    setModeState(m);
    localStorage.setItem(STORAGE_KEY, m);
  }, []);

  const setPreset = useCallback((p: ThemePreset) => {
    setPresetState(p);
    localStorage.setItem(PRESET_KEY, p);
  }, []);

  const setGlassEnhanced = useCallback((v: boolean) => {
    setGlassEnhancedState(v);
    localStorage.setItem(GLASS_KEY, String(v));
  }, []);

  const toggle = useCallback(() => {
    setMode(resolved === 'dark' ? 'light' : 'dark');
  }, [resolved, setMode]);

  // Apply dark class, preset class, and glass-enhanced on <html>
  useEffect(() => {
    const root = document.documentElement;

    // Dark mode
    if (resolved === 'dark') {
      root.classList.add('dark');
    } else {
      root.classList.remove('dark');
    }

    // Preset class — remove all, then add current
    ALL_PRESETS.forEach((p) => root.classList.remove(`preset-${p}`));
    if (preset !== 'default') {
      root.classList.add(`preset-${preset}`);
    }

    // Glass enhanced — only meaningful with iOS preset
    if (preset === 'ios' && glassEnhanced) {
      root.classList.add('glass-enhanced');
    } else {
      root.classList.remove('glass-enhanced');
    }
  }, [resolved, preset, glassEnhanced]);

  // Listen for system theme changes when in 'system' mode
  useEffect(() => {
    if (mode !== 'system') return;
    const mq = window.matchMedia('(prefers-color-scheme: dark)');
    const handler = () => setModeState((prev) => prev === 'system' ? 'system' : prev);
    mq.addEventListener('change', handler);
    return () => mq.removeEventListener('change', handler);
  }, [mode]);

  return (
    <ThemeContext.Provider value={{ mode, resolved, preset, glassEnhanced, setMode, setPreset, setGlassEnhanced, toggle }}>
      {children}
    </ThemeContext.Provider>
  );
}

export function useTheme(): ThemeContextValue {
  const ctx = useContext(ThemeContext);
  if (!ctx) throw new Error('useTheme must be used within ThemeProvider');
  return ctx;
}

export { ALL_PRESETS, type ThemePreset, type ThemeMode, type ResolvedTheme };
