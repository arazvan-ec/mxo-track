import { SearchBar } from './SearchBar';
import { LanguageSwitcher } from './LanguageSwitcher';
import { NotificationBell } from './NotificationBell';
import { UserDropdown } from './UserDropdown';

interface TopBarProps {
  compact?: boolean;
  onHamburgerClick: () => void;
}

export function TopBar({ compact = false, onHamburgerClick }: TopBarProps) {
  return (
    <div className="sticky top-0 z-20 flex h-16 shrink-0 items-center gap-x-4 border-b border-gray-200 bg-white px-4 shadow-sm sm:gap-x-6 sm:px-6 lg:px-8">
      {/* Hamburger button */}
      <button
        type="button"
        onClick={onHamburgerClick}
        className="-m-2.5 p-2.5 text-gray-500 hover:text-gray-900 transition-colors"
      >
        <span className="sr-only">Abrir menu</span>
        <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
          <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
        </svg>
      </button>

      <div className="flex flex-1 gap-x-4 self-stretch items-center lg:gap-x-6">
        {/* Search */}
        <div className="flex flex-1 items-center">
          <SearchBar compact={compact} />
        </div>

        {/* Right section */}
        <div className="flex items-center gap-x-4 lg:gap-x-6">
          <LanguageSwitcher />
          <NotificationBell />
          <div className="hidden lg:block lg:h-6 lg:w-px lg:bg-gray-200" aria-hidden="true" />
          <UserDropdown />
        </div>
      </div>
    </div>
  );
}
