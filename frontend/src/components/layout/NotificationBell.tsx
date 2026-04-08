import { useNotifications } from '@/api/hooks/useNotifications';

export function NotificationBell() {
  const { unreadCount } = useNotifications();

  return (
    <a
      href="/notifications"
      className="relative -m-2.5 p-2.5 transition-colors"
      style={{ color: 'var(--color-text-muted)' }}
    >
      <span className="sr-only">Ver notificaciones</span>
      <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"
        />
      </svg>
      {unreadCount > 0 && (
        <span className="absolute -top-1 -right-1 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white">
          {unreadCount > 99 ? '99+' : unreadCount}
        </span>
      )}
    </a>
  );
}
