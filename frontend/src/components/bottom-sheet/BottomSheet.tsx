import type { ReactNode } from 'react';
import { useBottomSheet, type BottomSheetState } from './useBottomSheet';

interface BottomSheetProps {
  state: BottomSheetState;
  onStateChange: (s: BottomSheetState) => void;
  title: ReactNode;
  children: ReactNode;
  isLoading?: boolean;
  error?: Error | null;
  loadingText?: string;
}

export function BottomSheet({ state, onStateChange, title, children, isLoading, error, loadingText }: BottomSheetProps) {
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
        {isLoading ? (
          <div className="flex items-center justify-center py-12">
            <div className="text-center">
              <div className="animate-spin h-8 w-8 border-2 border-blue-500 border-t-transparent rounded-full mx-auto mb-4" />
              <p className="text-slate-400 text-sm">{loadingText ?? 'Loading...'}</p>
            </div>
          </div>
        ) : error ? (
          <div className="flex items-center justify-center py-12">
            <div className="text-red-400 text-center">
              <p className="text-lg font-medium mb-2">Error</p>
              <p className="text-sm text-red-500">{error.message}</p>
            </div>
          </div>
        ) : (
          children
        )}
      </div>
    </div>
  );
}

export type { BottomSheetState };
