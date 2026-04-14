import { useState, useEffect } from 'react';
import { useUserPreferences, useUpdateUserPreferences } from '../api/hooks/useUserPreferences';

export function ProfilePage() {
  const { data: preferences, isLoading } = useUserPreferences();
  const updateMutation = useUpdateUserPreferences();
  const [mode, setMode] = useState<'expanded' | 'collapsed'>('expanded');
  const [saved, setSaved] = useState(false);

  useEffect(() => {
    if (preferences) {
      setMode(preferences.widget_default_mode);
    }
  }, [preferences]);

  const handleSave = () => {
    setSaved(false);
    updateMutation.mutate(
      { widget_default_mode: mode },
      {
        onSuccess: () => {
          setSaved(true);
          setTimeout(() => setSaved(false), 3000);
        },
      },
    );
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-full">
        <p style={{ color: 'var(--color-text-secondary)' }}>Cargando preferencias...</p>
      </div>
    );
  }

  return (
    <div className="max-w-xl mx-auto px-4 py-8">
      <h1
        className="text-2xl font-bold mb-6"
        style={{ color: 'var(--color-text-primary)' }}
      >
        Perfil y Preferencias
      </h1>

      <div className="theme-card p-6">
        <h2
          className="text-sm font-semibold uppercase tracking-wider mb-4"
          style={{ color: 'var(--color-text-muted)' }}
        >
          Modo de widgets por defecto
        </h2>

        <div className="space-y-3">
          <label
            className="flex items-center gap-3 cursor-pointer rounded-lg p-3 transition-colors"
            style={{
              backgroundColor:
                mode === 'expanded' ? 'var(--color-accent-muted)' : 'transparent',
              border: `1px solid ${mode === 'expanded' ? 'var(--color-accent)' : 'var(--color-border)'}`,
            }}
          >
            <input
              type="radio"
              name="widget_mode"
              value="expanded"
              checked={mode === 'expanded'}
              onChange={() => setMode('expanded')}
              className="accent-[var(--color-accent)]"
            />
            <div>
              <p
                className="text-sm font-medium"
                style={{ color: 'var(--color-text-primary)' }}
              >
                Modo expandido
              </p>
              <p
                className="text-xs"
                style={{ color: 'var(--color-text-secondary)' }}
              >
                Los widgets se muestran abiertos por defecto
              </p>
            </div>
          </label>

          <label
            className="flex items-center gap-3 cursor-pointer rounded-lg p-3 transition-colors"
            style={{
              backgroundColor:
                mode === 'collapsed' ? 'var(--color-accent-muted)' : 'transparent',
              border: `1px solid ${mode === 'collapsed' ? 'var(--color-accent)' : 'var(--color-border)'}`,
            }}
          >
            <input
              type="radio"
              name="widget_mode"
              value="collapsed"
              checked={mode === 'collapsed'}
              onChange={() => setMode('collapsed')}
              className="accent-[var(--color-accent)]"
            />
            <div>
              <p
                className="text-sm font-medium"
                style={{ color: 'var(--color-text-primary)' }}
              >
                Modo compacto
              </p>
              <p
                className="text-xs"
                style={{ color: 'var(--color-text-secondary)' }}
              >
                Los widgets se muestran colapsados por defecto
              </p>
            </div>
          </label>
        </div>

        <div className="mt-6 flex items-center gap-3">
          <button
            type="button"
            onClick={handleSave}
            disabled={updateMutation.isPending}
            className="px-4 py-2 rounded-lg text-sm font-medium text-white transition-opacity disabled:opacity-50"
            style={{ backgroundColor: 'var(--color-accent)' }}
          >
            {updateMutation.isPending ? 'Guardando...' : 'Guardar preferencias'}
          </button>

          {saved && (
            <span
              className="text-sm font-medium"
              style={{ color: 'var(--color-success, #22c55e)' }}
            >
              Preferencias guardadas
            </span>
          )}

          {updateMutation.isError && (
            <span
              className="text-sm font-medium"
              style={{ color: 'var(--color-error, #ef4444)' }}
            >
              Error al guardar
            </span>
          )}
        </div>
      </div>
    </div>
  );
}
