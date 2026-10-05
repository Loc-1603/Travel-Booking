import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ToastViewport } from '../components/Toast';
import { ToastContext } from './useToast';

let toastCounter = 0;

export function ToastProvider({ children }) {
  const { t } = useTranslation();
  const [toasts, setToasts] = useState([]);
  const timersRef = useRef(new Map());

  const dismiss = useCallback((id) => {
    const timer = timersRef.current.get(id);
    if (timer) {
      clearTimeout(timer);
      timersRef.current.delete(id);
    }
    setToasts((list) => list.filter((item) => item.id !== id));
  }, []);

  const toast = useCallback(
    (message, options = {}) => {
      const id = ++toastCounter;
      const type = options.type ?? 'info';
      const duration = options.duration ?? (type === 'error' ? 6000 : 4000);
      setToasts((list) => [...list, { id, message, type }]);

      if (duration > 0) {
        const timer = setTimeout(() => dismiss(id), duration);
        timersRef.current.set(id, timer);
      }
      return id;
    },
    [dismiss]
  );

  useEffect(
    () => () => {
      timersRef.current.forEach((timer) => clearTimeout(timer));
      timersRef.current.clear();
    },
    []
  );

  const value = useMemo(() => ({ toasts, toast, dismiss }), [toasts, toast, dismiss]);

  return (
    <ToastContext.Provider value={value}>
      {children}
      <ToastViewport
        toasts={toasts}
        onDismiss={dismiss}
        regionLabel={t('common.notifications')}
        dismissLabel={t('common.close')}
      />
    </ToastContext.Provider>
  );
}
