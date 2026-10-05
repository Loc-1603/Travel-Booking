import { AlertTriangle, CheckCircle2, Info, X, XCircle } from 'lucide-react';
import { cn } from '../lib/utils';

const ICONS = {
  success: CheckCircle2,
  error: XCircle,
  warning: AlertTriangle,
  info: Info,
};

const VARIANTS = {
  success: 'border-success/30 bg-success-50 text-neutral-900',
  error: 'border-error/30 bg-error-50 text-neutral-900',
  warning: 'border-warning/30 bg-amber-50 text-neutral-900',
  info: 'border-neutral-200 bg-white text-neutral-900',
};

const ICON_COLORS = {
  success: 'text-success',
  error: 'text-error',
  warning: 'text-warning',
  info: 'text-accent',
};

export function Toast({ message, type = 'info', onDismiss, dismissLabel }) {
  const Icon = ICONS[type] ?? Info;
  const role = type === 'error' || type === 'warning' ? 'alert' : 'status';

  return (
    <div
      role={role}
      className={cn(
        'pointer-events-auto flex items-start gap-3 rounded-xl border p-4 shadow-lg',
        VARIANTS[type] ?? VARIANTS.info
      )}
    >
      <Icon className={cn('mt-0.5 h-5 w-5 shrink-0', ICON_COLORS[type] ?? ICON_COLORS.info)} aria-hidden="true" />
      <p className="flex-1 text-sm leading-snug">{message}</p>
      <button
        type="button"
        onClick={() => onDismiss?.()}
        aria-label={dismissLabel}
        className="rounded-md p-1 text-neutral-500 transition-colors hover:bg-black/5 hover:text-neutral-900"
      >
        <X className="h-4 w-4" />
      </button>
    </div>
  );
}

export function ToastViewport({ toasts = [], onDismiss, regionLabel, dismissLabel }) {
  return (
    <div
      role="region"
      aria-label={regionLabel}
      className="pointer-events-none fixed inset-x-4 top-4 z-[100] flex flex-col gap-2 sm:left-auto sm:right-4 sm:w-full sm:max-w-sm"
    >
      {toasts.map((toast) => (
        <Toast
          key={toast.id}
          message={toast.message}
          type={toast.type}
          onDismiss={() => onDismiss?.(toast.id)}
          dismissLabel={dismissLabel}
        />
      ))}
    </div>
  );
}

export default Toast;
