import { useCallback, useEffect, useId, useRef } from 'react';
import { createPortal } from 'react-dom';
import { useTranslation } from 'react-i18next';
import { X } from 'lucide-react';
import { cn } from '../lib/utils';

const FOCUSABLE_SELECTOR = [
  'a[href]',
  'button:not([disabled])',
  'textarea:not([disabled])',
  'input:not([disabled])',
  'select:not([disabled])',
  '[tabindex]:not([tabindex="-1"])',
].join(', ');

const SIZE_CLASSES = {
  sm: 'max-w-sm',
  md: 'max-w-lg',
  lg: 'max-w-2xl',
  xl: 'max-w-4xl',
};

/**
 * Accessible modal dialog: role="dialog" + aria-modal, ESC to close,
 * backdrop click to close, focus trap, focus restore and body scroll lock.
 */
export function Modal({
  open,
  onClose,
  title,
  children,
  footer,
  size = 'md',
  className = '',
  hideClose = false,
  closeLabel,
  initialFocusRef,
}) {
  const { t } = useTranslation();
  const titleId = useId();
  const panelRef = useRef(null);
  const previouslyFocusedRef = useRef(null);
  const resolvedCloseLabel = closeLabel ?? t('common.close');

  const handleClose = useCallback(() => {
    onClose?.();
  }, [onClose]);

  useEffect(() => {
    if (!open) return undefined;

    previouslyFocusedRef.current = document.activeElement;
    const originalOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';

    const focusTarget = initialFocusRef?.current ?? panelRef.current;
    focusTarget?.focus?.();

    const onKeyDown = (event) => {
      if (event.key === 'Escape') {
        event.stopPropagation();
        handleClose();
        return;
      }
      if (event.key !== 'Tab') return;

      const node = panelRef.current;
      const focusables = node ? Array.from(node.querySelectorAll(FOCUSABLE_SELECTOR)) : [];
      if (focusables.length === 0) {
        event.preventDefault();
        node?.focus?.();
        return;
      }
      const first = focusables[0];
      const last = focusables[focusables.length - 1];
      const active = document.activeElement;

      if (event.shiftKey && (active === first || active === node)) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && active === last) {
        event.preventDefault();
        first.focus();
      }
    };

    document.addEventListener('keydown', onKeyDown);
    return () => {
      document.removeEventListener('keydown', onKeyDown);
      document.body.style.overflow = originalOverflow;
      previouslyFocusedRef.current?.focus?.();
    };
  }, [open, handleClose, initialFocusRef]);

  if (!open) return null;

  return createPortal(
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div
        data-testid="modal-backdrop"
        className="absolute inset-0 bg-black/60 backdrop-blur-sm"
        onClick={handleClose}
        aria-hidden="true"
      />
      <div
        ref={panelRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby={title ? titleId : undefined}
        aria-label={title ? undefined : resolvedCloseLabel}
        tabIndex={-1}
        className={cn(
          'relative z-10 flex w-full flex-col overflow-hidden rounded-2xl bg-white shadow-xl outline-none',
          SIZE_CLASSES[size] ?? SIZE_CLASSES.md,
          className
        )}
      >
        {(title || !hideClose) && (
          <div className="flex items-start justify-between gap-4 border-b border-neutral-200 p-5">
            {title ? (
              <h2 id={titleId} className="text-lg font-semibold text-neutral-900">
                {title}
              </h2>
            ) : (
              <span />
            )}
            {!hideClose && (
              <button
                type="button"
                onClick={handleClose}
                aria-label={resolvedCloseLabel}
                className="rounded-lg p-2 text-neutral-500 transition-colors hover:bg-neutral-100 hover:text-neutral-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-accent"
              >
                <X className="h-5 w-5" />
              </button>
            )}
          </div>
        )}
        <div className="overflow-y-auto p-5">{children}</div>
        {footer && (
          <div className="flex flex-wrap items-center justify-end gap-3 border-t border-neutral-200 bg-neutral-50 p-5">
            {footer}
          </div>
        )}
      </div>
    </div>,
    document.body
  );
}

export default Modal;
