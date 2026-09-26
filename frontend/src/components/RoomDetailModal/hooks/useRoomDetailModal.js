import { useEffect, useCallback, useRef } from 'react';

export function useRoomDetailModal({ isOpen, onClose }) {
  const modalRef = useRef(null);
  const previousActiveElement = useRef(null);
  const startY = useRef(0);
  const currentY = useRef(0);
  const isDragging = useRef(false);
  const canDrag = useRef(true);
  const startedInScrollArea = useRef(false);

  const getScrollContainer = useCallback(
    () => modalRef.current?.querySelector('[data-modal-scroll]'),
    []
  );

  const handleKeyDown = useCallback((e) => {
    if (!isOpen) return;

    if (e.key === 'Escape') {
      e.preventDefault();
      onClose();
      return;
    }

    if (e.key === 'Tab') {
      const focusableElements = modalRef.current?.querySelectorAll(
        'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
      );
      if (!focusableElements?.length) return;

      const firstElement = focusableElements[0];
      const lastElement = focusableElements[focusableElements.length - 1];

      if (e.shiftKey && document.activeElement === firstElement) {
        e.preventDefault();
        lastElement.focus();
      } else if (!e.shiftKey && document.activeElement === lastElement) {
        e.preventDefault();
        firstElement.focus();
      }
    }
  }, [isOpen, onClose]);

  const handleTouchStart = useCallback((e) => {
    if (!isOpen) return;
    startY.current = e.touches[0].clientY;
    currentY.current = startY.current;
    isDragging.current = true;
    // Swipe-dismiss is only allowed when the scrollable content is at the top,
    // or when the gesture starts outside of it (header/tabs/footer)
    startedInScrollArea.current = !!e.target.closest?.('[data-modal-scroll]');
    const scrollEl = getScrollContainer();
    canDrag.current = !startedInScrollArea.current || !scrollEl || scrollEl.scrollTop <= 0;
  }, [isOpen, getScrollContainer]);

  const handleTouchMove = useCallback((e) => {
    if (!isDragging.current || !isOpen) return;
    currentY.current = e.touches[0].clientY;

    if (!canDrag.current) {
      // Native scroll consumed the gesture; re-enable drag once it reaches the top
      const scrollEl = getScrollContainer();
      if (startedInScrollArea.current && scrollEl && scrollEl.scrollTop <= 0) {
        canDrag.current = true;
        startY.current = currentY.current;
      } else {
        return;
      }
    }

    const deltaY = currentY.current - startY.current;

    if (deltaY > 0 && modalRef.current) {
      modalRef.current.style.transform = `translateY(${Math.min(deltaY, 150)}px)`;
      modalRef.current.style.transition = 'none';
    }
  }, [isOpen, getScrollContainer]);

  const handleTouchEnd = useCallback(() => {
    if (!isDragging.current) return;
    isDragging.current = false;
    if (!canDrag.current) return;

    const deltaY = currentY.current - startY.current;
    const modal = modalRef.current;
    if (modal) {
      modal.style.transition = 'transform 0.2s ease-out';
      if (deltaY > 80) {
        modal.style.transform = 'translateY(100%)';
        setTimeout(() => onClose(), 200);
      } else {
        modal.style.transform = 'translateY(0)';
      }
    }
  }, [onClose]);

  useEffect(() => {
    const modal = modalRef.current;
    if (isOpen) {
      previousActiveElement.current = document.activeElement;
      document.body.style.overflow = 'hidden';
      document.addEventListener('keydown', handleKeyDown);

      if (modal) {
        modal.addEventListener('touchstart', handleTouchStart, { passive: true });
        modal.addEventListener('touchmove', handleTouchMove, { passive: true });
        modal.addEventListener('touchend', handleTouchEnd);

        setTimeout(() => {
          const firstFocusable = modal.querySelector(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
          );
          firstFocusable?.focus();
        }, 50);
      }
    }

    return () => {
      document.body.style.overflow = '';
      document.removeEventListener('keydown', handleKeyDown);
      if (modal) {
        modal.removeEventListener('touchstart', handleTouchStart);
        modal.removeEventListener('touchmove', handleTouchMove);
        modal.removeEventListener('touchend', handleTouchEnd);
        modal.style.transform = '';
        modal.style.transition = '';
      }
      if (previousActiveElement.current?.focus) {
        previousActiveElement.current.focus();
      }
    };
  }, [isOpen, handleKeyDown, handleTouchStart, handleTouchMove, handleTouchEnd]);

  const handleBackdropClick = useCallback((e) => {
    if (e.target === e.currentTarget) {
      onClose();
    }
  }, [onClose]);

  return {
    modalRef,
    handleBackdropClick,
  };
}

export function useFocusTrap(isActive) {
  const containerRef = useRef(null);

  useEffect(() => {
    if (!isActive || !containerRef.current) return;

    const container = containerRef.current;
    const focusableElements = container.querySelectorAll(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );

    const firstElement = focusableElements[0];
    const lastElement = focusableElements[focusableElements.length - 1];

    const handleTab = (e) => {
      if (e.key !== 'Tab') return;

      if (e.shiftKey) {
        if (document.activeElement === firstElement) {
          e.preventDefault();
          lastElement?.focus();
        }
      } else {
        if (document.activeElement === lastElement) {
          e.preventDefault();
          firstElement?.focus();
        }
      }
    };

    container.addEventListener('keydown', handleTab);
    firstElement?.focus();

    return () => container.removeEventListener('keydown', handleTab);
  }, [isActive]);

  return containerRef;
}
