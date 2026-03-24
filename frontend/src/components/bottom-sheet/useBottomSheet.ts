import { useState, useRef, useCallback, useEffect, type CSSProperties, type PointerEvent as ReactPointerEvent } from 'react';

export type BottomSheetState = 'collapsed' | 'half' | 'full';

export const SHEET_HEIGHTS: Record<BottomSheetState, number> = {
  collapsed: 0.15,
  half: 0.50,
  full: 0.85,
};

const STATES_ORDERED: BottomSheetState[] = ['collapsed', 'half', 'full'];
const SPRING_EASING = 'cubic-bezier(0.32, 0.72, 0, 1)';
const TRANSITION_MS = 300;
const SWIPE_VELOCITY_THRESHOLD = 500; // px/s

interface UseBottomSheetReturn {
  state: BottomSheetState;
  setState: (s: BottomSheetState) => void;
  heightPx: number;
  heightPercent: number;
  handleProps: {
    onPointerDown: (e: ReactPointerEvent) => void;
  };
  sheetStyle: CSSProperties;
}

export function useBottomSheet(
  initialState: BottomSheetState = 'collapsed',
  onStateChange?: (s: BottomSheetState) => void,
): UseBottomSheetReturn {
  const [state, setStateInternal] = useState<BottomSheetState>(initialState);
  const [viewportHeight, setViewportHeight] = useState(() =>
    typeof window !== 'undefined' ? window.innerHeight : 800,
  );
  const [dragOffset, setDragOffset] = useState(0);
  const [isDragging, setIsDragging] = useState(false);

  const dragStartY = useRef(0);
  const dragStartTime = useRef(0);
  const lastY = useRef(0);

  useEffect(() => {
    const onResize = () => setViewportHeight(window.innerHeight);
    window.addEventListener('resize', onResize);
    return () => window.removeEventListener('resize', onResize);
  }, []);

  const setState = useCallback(
    (s: BottomSheetState) => {
      setStateInternal(s);
      onStateChange?.(s);
    },
    [onStateChange],
  );

  const heightPercent = SHEET_HEIGHTS[state];
  const heightPx = viewportHeight * heightPercent;

  const snapToNearest = useCallback(
    (currentHeightPx: number, velocity: number) => {
      const currentPercent = currentHeightPx / viewportHeight;
      const stateIdx = STATES_ORDERED.indexOf(state);

      // Fast swipe detection
      if (Math.abs(velocity) > SWIPE_VELOCITY_THRESHOLD) {
        if (velocity < 0 && stateIdx < STATES_ORDERED.length - 1) {
          // Swiping up (negative Y = up) → next larger state
          setState(STATES_ORDERED[stateIdx + 1]);
          return;
        }
        if (velocity > 0 && stateIdx > 0) {
          // Swiping down → next smaller state
          setState(STATES_ORDERED[stateIdx - 1]);
          return;
        }
      }

      // Snap to closest state by height
      let closestState: BottomSheetState = state;
      let closestDiff = Infinity;
      for (const s of STATES_ORDERED) {
        const diff = Math.abs(SHEET_HEIGHTS[s] - currentPercent);
        if (diff < closestDiff) {
          closestDiff = diff;
          closestState = s;
        }
      }
      setState(closestState);
    },
    [state, viewportHeight, setState],
  );

  const onPointerDown = useCallback(
    (e: ReactPointerEvent) => {
      e.preventDefault();
      (e.target as HTMLElement).setPointerCapture(e.pointerId);
      dragStartY.current = e.clientY;
      dragStartTime.current = Date.now();
      lastY.current = e.clientY;
      setIsDragging(true);
      setDragOffset(0);

      const onPointerMove = (ev: globalThis.PointerEvent) => {
        const deltaY = ev.clientY - dragStartY.current;
        lastY.current = ev.clientY;
        setDragOffset(deltaY);
      };

      const onPointerUp = (ev: globalThis.PointerEvent) => {
        window.removeEventListener('pointermove', onPointerMove);
        window.removeEventListener('pointerup', onPointerUp);
        setIsDragging(false);
        setDragOffset(0);

        const totalDeltaY = ev.clientY - dragStartY.current;
        const elapsed = (Date.now() - dragStartTime.current) / 1000;
        const velocity = elapsed > 0 ? totalDeltaY / elapsed : 0;

        // If barely moved, treat as tap → cycle states
        if (Math.abs(totalDeltaY) < 10) {
          const idx = STATES_ORDERED.indexOf(state);
          const nextIdx = (idx + 1) % STATES_ORDERED.length;
          setState(STATES_ORDERED[nextIdx]);
          return;
        }

        const currentHeightPx = heightPx - totalDeltaY;
        snapToNearest(currentHeightPx, velocity);
      };

      window.addEventListener('pointermove', onPointerMove);
      window.addEventListener('pointerup', onPointerUp);
    },
    [state, heightPx, setState, snapToNearest],
  );

  // Sheet is positioned from the bottom using transform
  // Full viewport height minus sheet height = translateY from top
  const targetTranslateY = viewportHeight - heightPx;
  const currentTranslateY = isDragging
    ? viewportHeight - heightPx + dragOffset
    : targetTranslateY;

  const sheetStyle: CSSProperties = {
    transform: `translateY(${currentTranslateY}px)`,
    transition: isDragging ? 'none' : `transform ${TRANSITION_MS}ms ${SPRING_EASING}`,
    height: `${viewportHeight}px`,
    willChange: isDragging ? 'transform' : 'auto',
  };

  return {
    state,
    setState,
    heightPx,
    heightPercent,
    handleProps: { onPointerDown },
    sheetStyle,
  };
}
