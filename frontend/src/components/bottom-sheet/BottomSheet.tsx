import type { ReactNode } from 'react';
import { useBottomSheet, type BottomSheetState } from './useBottomSheet';

interface BottomSheetProps {
  state: BottomSheetState;
  onStateChange: (s: BottomSheetState) => void;
  title: ReactNode;
  children: ReactNode;
}

export function BottomSheet({ state, onStateChange, title, children }: BottomSheetProps) {
  const { handleProps, sheetStyle, heightPx } = useBottomSheet(state, onStateChange);

  // Content area height = sheet visible height - handle area (~64px)
  const contentMaxHeight = Math.max(0, heightPx - 64);

  return (
    <div
      className="fixed left-0 right-0 top-0 z-40 flex flex-col bg-slate-900 rounded-t-2xl border-t border-slate-700 shadow-2xl"
      style={sheetStyle}
    >
      {/* Drag handle */}
      <div
        className="flex-shrink-0 py-3 px-4 cursor-grab active:cursor-grabbing touch-none select-none"
        {...handleProps}
      >
        <div className="w-10 h-1 bg-slate-600 rounded-full mx-auto mb-2" />
        <h2 className="text-sm font-semibold text-slate-200 text-center">
          {title}
        </h2>
      </div>

      {/* Scrollable content */}
      <div
        className="flex-1 overflow-y-auto overflow-x-hidden overscroll-contain"
        style={{ maxHeight: contentMaxHeight }}
      >
        {children}
      </div>
    </div>
  );
}

export type { BottomSheetState };
