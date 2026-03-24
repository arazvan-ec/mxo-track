import { SearchBar } from './SearchBar';
import { LanguageSwitcher } from './LanguageSwitcher';
import { NotificationBell } from './NotificationBell';
import { UserDropdown } from './UserDropdown';
import { useTheme } from '@/context/ThemeProvider';

interface TopBarProps {
  compact?: boolean;
  onMenuClick: () => void;
  /** Optional extra controls (e.g. data sidebar toggle) rendered after hamburger */
  extraControls?: React.ReactNode;
}

export function TopBar({ compact = false, onMenuClick, extraControls }: TopBarProps) {
  const { resolved, toggle } = useTheme();

  return (
    <div
      className="sticky top-0 z-20 flex h-16 shrink-0 items-center gap-x-4 border-b px-4 shadow-sm sm:gap-x-6 sm:px-6 lg:px-8"
      style={{
        backgroundColor: 'var(--color-surface-glass)',
        borderColor: 'var(--color-border)',
        backdropFilter: 'blur(16px)',
        WebkitBackdropFilter: 'blur(16px)',
      }}
    >
      {/* Hamburger button */}
      <button
        type="button"
        onClick={onMenuClick}
        className="-m-2.5 p-2.5 transition-colors"
        style={{ color: 'var(--color-text-secondary)' }}
      >
        <span className="sr-only">Abrir menu</span>
        <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
          <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
        </svg>
      </button>

      {extraControls}

      <div className="flex flex-1 gap-x-4 self-stretch items-center lg:gap-x-6">
        {/* Search */}
        <div className="flex flex-1 items-center">
          <SearchBar compact={compact} />
        </div>

        {/* Right section */}
        <div className="flex items-center gap-x-4 lg:gap-x-6">
          <button
            type="button"
            onClick={toggle}
            className="p-2 rounded-lg transition-colors hover:opacity-80"
            style={{ color: 'var(--color-text-secondary)' }}
            title={resolved === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'}
          >
            {resolved === 'dark' ? (
              <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
              </svg>
            ) : (
              <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
              </svg>
            )}
          </button>
          <LanguageSwitcher />
          <NotificationBell />
          <div
            className="hidden lg:block lg:h-6 lg:w-px"
            style={{ backgroundColor: 'var(--color-border)' }}
            aria-hidden="true"
          />
          <UserDropdown />
        </div>
      </div>
    </div>
  );
}
