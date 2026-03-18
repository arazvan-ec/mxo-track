import { useEffect, useState } from 'react';

interface Props {
  sseConnected: boolean;
}

export function HeaderBar({ sseConnected }: Props) {
  const [time, setTime] = useState(formatTime());

  useEffect(() => {
    const id = setInterval(() => setTime(formatTime()), 1000);
    return () => clearInterval(id);
  }, []);

  return (
    <div
      className="absolute top-0 left-80 right-0 z-10 h-14 flex items-center justify-end px-4"
      style={{
        background:
          'linear-gradient(to bottom, rgba(15,23,42,0.85) 0%, rgba(15,23,42,0.4) 70%, transparent 100%)',
      }}
    >
      <div className="flex items-center gap-4">
        <a
          href="/admin/routes"
          className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-amber-500/20 text-amber-300 hover:bg-amber-500/30 transition-colors border border-amber-500/30"
          title="Go to routes"
        >
          <svg
            className="w-3.5 h-3.5"
            fill="none"
            viewBox="0 0 24 24"
            strokeWidth={2}
            stroke="currentColor"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 010 1.972l-11.54 6.347a1.125 1.125 0 01-1.667-.986V5.653z"
            />
          </svg>
          Demo
        </a>

        <span className="text-slate-400 text-sm font-mono">{time}</span>

        <div className="flex items-center gap-1.5">
          <span className="relative flex h-2.5 w-2.5">
            <span
              className={`absolute inline-flex h-full w-full rounded-full opacity-75 animate-ping ${
                sseConnected ? 'bg-emerald-400' : 'bg-red-400'
              }`}
            />
            <span
              className={`relative inline-flex rounded-full h-2.5 w-2.5 ${
                sseConnected ? 'bg-emerald-500' : 'bg-red-500'
              }`}
            />
          </span>
          <span
            className={`text-xs ${sseConnected ? 'text-emerald-400' : 'text-red-400'}`}
          >
            {sseConnected ? 'Live' : 'Disconnected'}
          </span>
        </div>
      </div>
    </div>
  );
}

function formatTime(): string {
  return new Date().toLocaleTimeString('en-GB', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  });
}
