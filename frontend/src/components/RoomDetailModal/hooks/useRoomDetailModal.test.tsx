import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, fireEvent, cleanup, waitFor } from '@testing-library/react';
import { useRoomDetailModal } from './useRoomDetailModal';

function ModalHost({ isOpen, onClose }: { isOpen: boolean; onClose: () => void }) {
  const { modalRef, handleBackdropClick } = useRoomDetailModal({ isOpen, onClose });
  if (!isOpen) return null;
  return (
    <div ref={modalRef} data-testid="modal" onClick={handleBackdropClick}>
      <button>inside-first</button>
      <button>inside-last</button>
      <div data-modal-scroll>
        <p>scrollable content</p>
      </div>
    </div>
  );
}

beforeEach(() => {
  document.body.style.overflow = '';
});

afterEach(() => {
  cleanup();
  document.body.style.overflow = '';
});

describe('useRoomDetailModal', () => {
  it('locks body scroll while open and restores it when closed', () => {
    const onClose = vi.fn();
    const { rerender } = render(<ModalHost isOpen onClose={onClose} />);
    expect(document.body.style.overflow).toBe('hidden');

    rerender(<ModalHost isOpen={false} onClose={onClose} />);
    expect(document.body.style.overflow).toBe('');
  });

  it('closes on Escape', () => {
    const onClose = vi.fn();
    render(<ModalHost isOpen onClose={onClose} />);
    fireEvent.keyDown(document, { key: 'Escape' });
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('traps Tab focus inside the modal (wrap from last to first)', () => {
    const onClose = vi.fn();
    const { getByText } = render(<ModalHost isOpen onClose={onClose} />);
    // real .focus() — fireEvent.focus only dispatches an event without moving focus
    (getByText('inside-last') as HTMLButtonElement).focus();
    fireEvent.keyDown(document, { key: 'Tab' });
    expect(document.activeElement).toBe(getByText('inside-first'));

    fireEvent.keyDown(document, { key: 'Tab', shiftKey: true });
    expect(document.activeElement).toBe(getByText('inside-last'));
  });

  it('closes when clicking the backdrop but not inner content', () => {
    const onClose = vi.fn();
    const { getByTestId, getByText } = render(<ModalHost isOpen onClose={onClose} />);
    fireEvent.click(getByText('inside-first'));
    expect(onClose).not.toHaveBeenCalled();
    fireEvent.click(getByTestId('modal'));
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('restores focus to the element that was focused before opening', async () => {
    const outside = document.createElement('button');
    outside.textContent = 'outside-trigger';
    document.body.appendChild(outside);
    outside.focus();

    const { rerender } = render(<ModalHost isOpen onClose={vi.fn()} />);
    // the 50ms auto-focus delay inside the hook must run before closing
    await waitFor(() => expect(document.activeElement?.textContent).toBe('inside-first'));

    rerender(<ModalHost isOpen={false} onClose={vi.fn()} />);
    expect(document.activeElement).toBe(outside);
    document.body.removeChild(outside);
  });
});
