import { useState, useEffect, useRef } from 'react';
import { useMe } from '@/api/hooks/useMe';

const roleLabels: Record<string, string> = {
  ROLE_ADMIN: 'Administrador',
  ROLE_CUSTOMER: 'Cliente',
  ROLE_DRIVER: 'Conductor',
  ROLE_OPERATOR: 'Operador',
};

export function UserDropdown() {
  const { data: me } = useMe();
  const [open, setOpen] = useState(false);
  const wrapperRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    function handleClickOutside(e: MouseEvent) {
      if (wrapperRef.current && !wrapperRef.current.contains(e.target as Node)) {
        setOpen(false);
      }
    }
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  if (!me) return null;

  return (
    <div ref={wrapperRef} className="relative">
      <button
        type="button"
        onClick={() => setOpen(!open)}
        className="-m-1.5 flex items-center p-1.5"
      >
        <span className="sr-only">Menu de usuario</span>
        <span className="flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-sm font-semibold text-white">
          {(me.email ?? '?')[0].toUpperCase()}
        </span>
        <span className="hidden lg:flex lg:items-center">
          <span className="ml-3 text-sm font-semibold leading-6 text-gray-900">
            {me.email}
          </span>
          <svg className="ml-2 h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
            <path
              fillRule="evenodd"
              d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
              clipRule="evenodd"
            />
          </svg>
        </span>
      </button>

      {open && (
        <div className="absolute right-0 z-10 mt-2.5 w-48 origin-top-right rounded-md bg-white py-2 shadow-lg ring-1 ring-gray-900/5">
          <div className="px-4 py-2 border-b border-gray-100">
            <p className="text-xs text-gray-500">Conectado como</p>
            <p className="text-sm font-medium text-gray-900 truncate">{me.email}</p>
            <p className="text-xs text-gray-400">{roleLabels[me.role] ?? me.role}</p>
          </div>
          <a
            href="/logout"
            className="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors"
          >
            Cerrar sesion
          </a>
        </div>
      )}
    </div>
  );
}
