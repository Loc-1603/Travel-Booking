import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, fireEvent, cleanup } from '@testing-library/react';
import { useFocusTrap } from './useRoomDetailModal';

function Trap({ active }: { active: boolean }) {
  const ref = useFocusTrap(active);
  return (
    <div ref={ref} data-testid="trap">
      <button>first</button>
      <button>middle</button>
      <button>last</button>
    </div>
  );
}

beforeEach(() => {
  document.body.style.overflow = '';
});

afterEach(() => {
  cleanup();
});

describe('useFocusTrap', () => {
  it('focuses the first focusable element when activated', () => {
    render(<Trap active />);
    expect(document.activeElement?.textContent).toBe('first');
  });

  it('wraps Tab forward from the last element back to the first', () => {
    const { getByTestId, getByText } = render(<Trap active />);
    fireEvent.focus(getByText('last'));
    fireEvent.keyDown(getByTestId('trap'), { key: 'Tab' });
    expect(document.activeElement).toBe(getByText('first'));
  });

  it('wraps Shift+Tab from the first element back to the last', () => {
    const { getByTestId, getByText } = render(<Trap active />);
    fireEvent.focus(getByText('first'));
    fireEvent.keyDown(getByTestId('trap'), { key: 'Tab', shiftKey: true });
    expect(document.activeElement).toBe(getByText('last'));
  });

  it('lets regular Tab moves inside the trap pass through', () => {
    const { getByTestId, getByText } = render(<Trap active />);
    fireEvent.focus(getByText('first'));
    fireEvent.keyDown(getByTestId('trap'), { key: 'Tab' });
    // not on the last element → no wrap, no preventDefault
    expect(document.activeElement).toBe(getByText('first'));
  });

  it('does nothing while inactive', () => {
    const { getByTestId, getByText } = render(<Trap active={false} />);
    getByText('middle').focus();
    fireEvent.keyDown(getByTestId('trap'), { key: 'Tab' });
    expect(document.activeElement).toBe(getByText('middle'));
  });

  it('stops trapping after the trap container is unmounted', () => {
    const { getByTestId, unmount } = render(<Trap active />);
    const trap = getByTestId('trap');
    unmount();
    // listener removed: dispatching must not throw or move focus anywhere
    fireEvent.keyDown(trap, { key: 'Tab' });
    expect(document.activeElement).toBe(document.body);
  });
});
