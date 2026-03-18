import { Link, useLocation } from 'react-router';

interface NavItem {
  label: string;
  path: string;
}

const ADMIN_NAV: NavItem[] = [
  { label: 'Fleet Map', path: '/app/admin/fleet-map' },
];

export function Sidebar() {
  const location = useLocation();

  return (
    <aside className="w-16 bg-gray-900 text-white flex flex-col items-center py-4 gap-2">
      {/* Logo */}
      <div className="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center mb-6 text-xs font-bold">
        MXO
      </div>

      {/* Nav items */}
      {ADMIN_NAV.map((item) => {
        const isActive = location.pathname === item.path;
        return (
          <Link
            key={item.path}
            to={item.path}
            title={item.label}
            className={`w-10 h-10 rounded-lg flex items-center justify-center text-xs ${
              isActive
                ? 'bg-blue-600 text-white'
                : 'text-gray-400 hover:bg-gray-800 hover:text-white'
            }`}
          >
            {item.label.charAt(0)}
          </Link>
        );
      })}
    </aside>
  );
}
