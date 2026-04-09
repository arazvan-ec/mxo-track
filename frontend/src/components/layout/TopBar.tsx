import { SearchBar } from './SearchBar';
import { LanguageSwitcher } from './LanguageSwitcher';
import { NotificationBell } from './NotificationBell';
import { UserDropdown } from './UserDropdown';
import { ThemeSwitcher } from '@/components/ui/ThemeSwitcher';

interface TopBarProps {
  compact?: boolean;
  onMenuClick: () => void;
  /** Optional extra controls (e.g. data sidebar toggle) rendered after hamburger */
  extraControls?: React.ReactNode;
}

export function TopBar({ compact = false, onMenuClick, extraControls }: TopBarProps) {

  return (
    <div
      className="glass-overlay sticky top-0 z-20 flex h-16 shrink-0 items-center gap-x-4 border-b px-4 shadow-sm sm:gap-x-6 sm:px-6 lg:px-8"
      style={{ borderColor: 'var(--color-border)' }}
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
          <ThemeSwitcher mode="inline" />
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
