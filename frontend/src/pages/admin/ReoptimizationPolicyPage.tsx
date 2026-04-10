import { useState } from 'react';
import {
  useReoptimizationPolicies,
  useCreatePolicy,
  useUpdatePolicy,
  useDeletePolicy,
} from '@/api/hooks/useReoptimizationPolicy';
import type { ReoptimizationPolicy } from '@/api/types';

/* ── Trigger definitions ──────────────────────────────────────────── */

const AVAILABLE_TRIGGERS = [
  { key: 'on_exception', label: 'Excepción' },
  { key: 'on_skip', label: 'Salto' },
  { key: 'on_delay', label: 'Retraso' },
] as const;

/* ── Empty form state ─────────────────────────────────────────────── */

function emptyForm(): Omit<ReoptimizationPolicy, 'public_id'> {
  return {
    triggers: [],
    delay_threshold_minutes: 30,
    cooldown_minutes: 15,
    consecutive_exception_threshold: 3,
    enabled: true,
  };
}

/* ── Inline policy card ───────────────────────────────────────────── */

interface PolicyCardProps {
  policy: ReoptimizationPolicy;
  onSave: (p: ReoptimizationPolicy) => void;
  onDelete: (publicId: string) => void;
  saving: boolean;
  deleting: boolean;
}

function PolicyCard({ policy, onSave, onDelete, saving, deleting }: PolicyCardProps) {
  const [form, setForm] = useState<ReoptimizationPolicy>({ ...policy });

  const toggleTrigger = (key: string) => {
    setForm((prev) => ({
      ...prev,
      triggers: prev.triggers.includes(key)
        ? prev.triggers.filter((t) => t !== key)
        : [...prev.triggers, key],
    }));
  };

  const setField = <K extends keyof ReoptimizationPolicy>(key: K, value: ReoptimizationPolicy[K]) =>
    setForm((prev) => ({ ...prev, [key]: value }));

  return (
    <div
      className="rounded-lg border p-5 space-y-4"
      style={{ backgroundColor: 'var(--color-surface-elevated)', borderColor: 'var(--color-border)' }}
    >
      {/* Header: enabled toggle + ID */}
      <div className="flex items-center justify-between">
        <label className="flex items-center gap-2 cursor-pointer">
          <input
            type="checkbox"
            checked={form.enabled}
            onChange={(e) => setField('enabled', e.target.checked)}
            className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
          />
          <span className="text-sm font-medium" style={{ color: 'var(--color-text-primary)' }}>
            {form.enabled ? 'Habilitada' : 'Deshabilitada'}
          </span>
        </label>
        <span className="text-xs font-mono" style={{ color: 'var(--color-text-muted)' }}>
          {policy.public_id.slice(0, 8)}...
        </span>
      </div>

      {/* Triggers checkboxes */}
      <fieldset>
        <legend className="text-sm font-semibold mb-2" style={{ color: 'var(--color-text-secondary)' }}>
          Disparadores
        </legend>
        <div className="flex flex-wrap gap-4">
          {AVAILABLE_TRIGGERS.map(({ key, label }) => (
            <label key={key} className="flex items-center gap-2 cursor-pointer">
              <input
                type="checkbox"
                checked={form.triggers.includes(key)}
                onChange={() => toggleTrigger(key)}
                className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
              />
              <span className="text-sm" style={{ color: 'var(--color-text-primary)' }}>{label}</span>
            </label>
          ))}
        </div>
      </fieldset>

      {/* Number inputs */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
          <label className="block text-sm font-medium mb-1" style={{ color: 'var(--color-text-secondary)' }}>
            Umbral retraso (min)
          </label>
          <input
            type="number"
            min={0}
            value={form.delay_threshold_minutes}
            onChange={(e) => setField('delay_threshold_minutes', Number(e.target.value))}
            className="w-full rounded-md border px-3 py-2 text-sm"
            style={{
              backgroundColor: 'var(--color-surface)',
              borderColor: 'var(--color-border)',
              color: 'var(--color-text-primary)',
            }}
          />
        </div>
        <div>
          <label className="block text-sm font-medium mb-1" style={{ color: 'var(--color-text-secondary)' }}>
            Excepciones consecutivas
          </label>
          <input
            type="number"
            min={1}
            value={form.consecutive_exception_threshold}
            onChange={(e) => setField('consecutive_exception_threshold', Number(e.target.value))}
            className="w-full rounded-md border px-3 py-2 text-sm"
            style={{
              backgroundColor: 'var(--color-surface)',
              borderColor: 'var(--color-border)',
              color: 'var(--color-text-primary)',
            }}
          />
        </div>
        <div>
          <label className="block text-sm font-medium mb-1" style={{ color: 'var(--color-text-secondary)' }}>
            Cooldown (min)
          </label>
          <input
            type="number"
            min={0}
            value={form.cooldown_minutes}
            onChange={(e) => setField('cooldown_minutes', Number(e.target.value))}
            className="w-full rounded-md border px-3 py-2 text-sm"
            style={{
              backgroundColor: 'var(--color-surface)',
              borderColor: 'var(--color-border)',
              color: 'var(--color-text-primary)',
            }}
          />
        </div>
      </div>

      {/* Actions */}
      <div className="flex items-center gap-3 pt-2">
        <button
          type="button"
          disabled={saving}
          onClick={() => onSave(form)}
          className="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 disabled:opacity-50"
        >
          {saving ? 'Guardando...' : 'Guardar'}
        </button>
        <button
          type="button"
          disabled={deleting}
          onClick={() => onDelete(policy.public_id)}
          className="inline-flex items-center rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 disabled:opacity-50"
        >
          {deleting ? 'Eliminando...' : 'Eliminar'}
        </button>
      </div>
    </div>
  );
}

/* ── New policy form ──────────────────────────────────────────────── */

interface NewPolicyFormProps {
  onSubmit: (p: Omit<ReoptimizationPolicy, 'public_id'>) => void;
  onCancel: () => void;
  saving: boolean;
}

function NewPolicyForm({ onSubmit, onCancel, saving }: NewPolicyFormProps) {
  const [form, setForm] = useState(emptyForm());

  const toggleTrigger = (key: string) => {
    setForm((prev) => ({
      ...prev,
      triggers: prev.triggers.includes(key)
        ? prev.triggers.filter((t) => t !== key)
        : [...prev.triggers, key],
    }));
  };

  const setField = <K extends keyof ReturnType<typeof emptyForm>>(
    key: K,
    value: ReturnType<typeof emptyForm>[K],
  ) => setForm((prev) => ({ ...prev, [key]: value }));

  return (
    <div
      className="rounded-lg border-2 border-dashed p-5 space-y-4"
      style={{ borderColor: 'var(--color-accent)', backgroundColor: 'var(--color-surface-elevated)' }}
    >
      <h3 className="text-sm font-bold" style={{ color: 'var(--color-text-primary)' }}>
        Nueva Politica
      </h3>

      {/* Enabled toggle */}
      <label className="flex items-center gap-2 cursor-pointer">
        <input
          type="checkbox"
          checked={form.enabled}
          onChange={(e) => setField('enabled', e.target.checked)}
          className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
        />
        <span className="text-sm font-medium" style={{ color: 'var(--color-text-primary)' }}>
          {form.enabled ? 'Habilitada' : 'Deshabilitada'}
        </span>
      </label>

      {/* Triggers */}
      <fieldset>
        <legend className="text-sm font-semibold mb-2" style={{ color: 'var(--color-text-secondary)' }}>
          Disparadores
        </legend>
        <div className="flex flex-wrap gap-4">
          {AVAILABLE_TRIGGERS.map(({ key, label }) => (
            <label key={key} className="flex items-center gap-2 cursor-pointer">
              <input
                type="checkbox"
                checked={form.triggers.includes(key)}
                onChange={() => toggleTrigger(key)}
                className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
              />
              <span className="text-sm" style={{ color: 'var(--color-text-primary)' }}>{label}</span>
            </label>
          ))}
        </div>
      </fieldset>

      {/* Number inputs */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
          <label className="block text-sm font-medium mb-1" style={{ color: 'var(--color-text-secondary)' }}>
            Umbral retraso (min)
          </label>
          <input
            type="number"
            min={0}
            value={form.delay_threshold_minutes}
            onChange={(e) => setField('delay_threshold_minutes', Number(e.target.value))}
            className="w-full rounded-md border px-3 py-2 text-sm"
            style={{
              backgroundColor: 'var(--color-surface)',
              borderColor: 'var(--color-border)',
              color: 'var(--color-text-primary)',
            }}
          />
        </div>
        <div>
          <label className="block text-sm font-medium mb-1" style={{ color: 'var(--color-text-secondary)' }}>
            Excepciones consecutivas
          </label>
          <input
            type="number"
            min={1}
            value={form.consecutive_exception_threshold}
            onChange={(e) => setField('consecutive_exception_threshold', Number(e.target.value))}
            className="w-full rounded-md border px-3 py-2 text-sm"
            style={{
              backgroundColor: 'var(--color-surface)',
              borderColor: 'var(--color-border)',
              color: 'var(--color-text-primary)',
            }}
          />
        </div>
        <div>
          <label className="block text-sm font-medium mb-1" style={{ color: 'var(--color-text-secondary)' }}>
            Cooldown (min)
          </label>
          <input
            type="number"
            min={0}
            value={form.cooldown_minutes}
            onChange={(e) => setField('cooldown_minutes', Number(e.target.value))}
            className="w-full rounded-md border px-3 py-2 text-sm"
            style={{
              backgroundColor: 'var(--color-surface)',
              borderColor: 'var(--color-border)',
              color: 'var(--color-text-primary)',
            }}
          />
        </div>
      </div>

      {/* Actions */}
      <div className="flex items-center gap-3 pt-2">
        <button
          type="button"
          disabled={saving}
          onClick={() => onSubmit(form)}
          className="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 disabled:opacity-50"
        >
          {saving ? 'Creando...' : 'Crear'}
        </button>
        <button
          type="button"
          onClick={onCancel}
          className="inline-flex items-center rounded-md px-4 py-2 text-sm font-semibold shadow-sm border hover:opacity-80"
          style={{ borderColor: 'var(--color-border)', color: 'var(--color-text-secondary)' }}
        >
          Cancelar
        </button>
      </div>
    </div>
  );
}

/* ── Page component ───────────────────────────────────────────────── */

export function ReoptimizationPolicyPage() {
  const [showNew, setShowNew] = useState(false);
  const { data: policies, isLoading } = useReoptimizationPolicies();
  const createMutation = useCreatePolicy();
  const updateMutation = useUpdatePolicy();
  const deleteMutation = useDeletePolicy();

  const handleCreate = (payload: Omit<ReoptimizationPolicy, 'public_id'>) => {
    createMutation.mutate(payload, { onSuccess: () => setShowNew(false) });
  };

  const handleUpdate = (policy: ReoptimizationPolicy) => {
    updateMutation.mutate(policy);
  };

  const handleDelete = (publicId: string) => {
    deleteMutation.mutate(publicId);
  };

  return (
    <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 py-6">
      {/* Header */}
      <div className="mb-6 flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold" style={{ color: 'var(--color-text-primary)' }}>
            Politicas de Reoptimizacion
          </h1>
          <p className="mt-1 text-sm" style={{ color: 'var(--color-text-secondary)' }}>
            Configura cuando el sistema debe reoptimizar rutas automaticamente.
          </p>
        </div>
        {!showNew && (
          <button
            type="button"
            onClick={() => setShowNew(true)}
            className="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500"
          >
            Crear nueva politica
          </button>
        )}
      </div>

      {/* New policy form */}
      {showNew && (
        <div className="mb-6">
          <NewPolicyForm
            onSubmit={handleCreate}
            onCancel={() => setShowNew(false)}
            saving={createMutation.isPending}
          />
        </div>
      )}

      {/* Loading state */}
      {isLoading && (
        <p className="text-sm py-8 text-center" style={{ color: 'var(--color-text-muted)' }}>
          Cargando politicas...
        </p>
      )}

      {/* Empty state */}
      {!isLoading && policies && policies.length === 0 && (
        <p className="text-sm py-8 text-center" style={{ color: 'var(--color-text-muted)' }}>
          No hay politicas configuradas. Crea una para empezar.
        </p>
      )}

      {/* Policy list */}
      <div className="space-y-4">
        {policies?.map((policy) => (
          <PolicyCard
            key={policy.public_id}
            policy={policy}
            onSave={handleUpdate}
            onDelete={handleDelete}
            saving={updateMutation.isPending}
            deleting={deleteMutation.isPending}
          />
        ))}
      </div>
    </div>
  );
}
