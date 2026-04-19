import { useEffect, useRef, useState } from 'react';

const DEFAULT_BRIGHTNESS = 0.3;
const MIN_BRIGHTNESS = 0.15;
const DEBOUNCE_MS = 300;

/**
 * Measures the average luminance of the map canvas behind the sidebar
 * and returns an adjusted `brightness()` value for `backdrop-filter`.
 *
 * - Bright map background → lower brightness (more darkening) for readability
 * - Dark map background → higher brightness (less darkening)
 * - No map canvas / read fails → null (let the theme's --glass-brightness apply)
 */
export function useAdaptiveOpacity(isOpen: boolean): { brightnessValue: number | null } {
  const [brightnessValue, setBrightnessValue] = useState<number | null>(null);
  const timerRef = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);

  useEffect(() => {
    if (!isOpen) {
      setBrightnessValue(null);
      return;
    }

    const canvas = document.querySelector('.maplibregl-canvas') as HTMLCanvasElement | null;
    if (!canvas) {
      setBrightnessValue(null);
      return;
    }

    const measure = () => {
      try {
        const sidebarWidth = Math.min(window.innerWidth * 0.85, 288);
        const temp = document.createElement('canvas');
        const ctx = temp.getContext('2d');
        if (!ctx) return;

        // Downsample to 50x50 for performance
        temp.width = 50;
        temp.height = 50;
        ctx.drawImage(canvas, 0, 0, sidebarWidth, canvas.height, 0, 0, 50, 50);

        const data = ctx.getImageData(0, 0, 50, 50).data;
        let totalLuminance = 0;
        let count = 0;
        const step = 4; // Sample every 4th pixel

        for (let i = 0; i < data.length; i += 4 * step) {
          totalLuminance += 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
          count++;
        }

        const avgLuminance = totalLuminance / count; // 0–255

        // Map luminance to brightness value:
        // Dark map (avg ~30) → 0.30 (light touch)
        // Bright map (avg ~200) → 0.15 (heavy darkening)
        const ratio = Math.max(0, Math.min(avgLuminance / 255, 1));
        const adjusted = DEFAULT_BRIGHTNESS - ratio * (DEFAULT_BRIGHTNESS - MIN_BRIGHTNESS);
        setBrightnessValue(adjusted);
      } catch {
        // Canvas read failed (CORS, security) — keep CSS default
      }
    };

    measure();

    // Re-measure on map movements (debounced)
    const debouncedMeasure = () => {
      clearTimeout(timerRef.current);
      timerRef.current = setTimeout(measure, DEBOUNCE_MS);
    };

    // Listen for map moveend via the canvas's parent container
    const mapContainer = canvas.closest('.maplibregl-map');
    const observer = new MutationObserver(debouncedMeasure);
    if (mapContainer) {
      // MapLibre updates canvas on move — observe the container for attribute changes
      observer.observe(mapContainer, { attributes: true, subtree: false });
    }

    // Also listen for resize
    window.addEventListener('resize', debouncedMeasure);

    return () => {
      clearTimeout(timerRef.current);
      observer.disconnect();
      window.removeEventListener('resize', debouncedMeasure);
    };
  }, [isOpen]);

  return { brightnessValue };
}
