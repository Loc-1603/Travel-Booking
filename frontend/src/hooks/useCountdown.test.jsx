import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, act } from '@testing-library/react';
import { useCountdown } from './useCountdown';

const createTestComponent = (target) => () => {
  const { minutes, seconds, isExpired } = useCountdown(target);
  return (
    <div>
      <span data-testid="minutes">{minutes}</span>
      <span data-testid="seconds">{seconds}</span>
      <span data-testid="expired">{isExpired ? 'true' : 'false'}</span>
    </div>
  );
};

describe('useCountdown', () => {
  beforeEach(() => {
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.useRealTimers();
    vi.clearAllMocks();
  });

  it('returns zero countdown for null target', () => {
    const Component = createTestComponent(null);
    render(<Component />);
    expect(screen.getByTestId('minutes')).toHaveTextContent('0');
    expect(screen.getByTestId('seconds')).toHaveTextContent('0');
    expect(screen.getByTestId('expired')).toHaveTextContent('false');
  });

  it('returns zero countdown for undefined target', () => {
    const Component = createTestComponent(undefined);
    render(<Component />);
    expect(screen.getByTestId('minutes')).toHaveTextContent('0');
    expect(screen.getByTestId('seconds')).toHaveTextContent('0');
    expect(screen.getByTestId('expired')).toHaveTextContent('false');
  });

  it('counts down to a future target', () => {
    const future = new Date(Date.now() + 90_000).toISOString(); // 90 seconds
    const Component = createTestComponent(future);
    render(<Component />);

    expect(screen.getByTestId('minutes')).toHaveTextContent('1');
    expect(screen.getByTestId('seconds')).toHaveTextContent('30');
    expect(screen.getByTestId('expired')).toHaveTextContent('false');

    // Advance 30 seconds
    act(() => {
      vi.advanceTimersByTime(30_000);
    });
    expect(screen.getByTestId('minutes')).toHaveTextContent('1');
    expect(screen.getByTestId('seconds')).toHaveTextContent('0');

    // Advance 1 minute
    act(() => {
      vi.advanceTimersByTime(60_000);
    });
    expect(screen.getByTestId('minutes')).toHaveTextContent('0');
    expect(screen.getByTestId('seconds')).toHaveTextContent('0');

    // Advance past expiry
    act(() => {
      vi.advanceTimersByTime(1_000);
    });
    expect(screen.getByTestId('expired')).toHaveTextContent('true');
  });

  it('handles past targets as expired', () => {
    const past = new Date(Date.now() - 10_000).toISOString(); // 10 seconds ago
    const Component = createTestComponent(past);
    render(<Component />);

    expect(screen.getByTestId('expired')).toHaveTextContent('true');
    expect(screen.getByTestId('minutes')).toHaveTextContent('0');
    expect(screen.getByTestId('seconds')).toHaveTextContent('0');
  });

  it('updates when target changes', () => {
    const Component = ({ target }) => {
      const { minutes, seconds } = useCountdown(target);
      return (
        <div>
          <span data-testid="minutes">{minutes}</span>
          <span data-testid="seconds">{seconds}</span>
        </div>
      );
    };

    const { rerender } = render(<Component target={null} />);
    expect(screen.getByTestId('minutes')).toHaveTextContent('0');

    const future = new Date(Date.now() + 60_000).toISOString();
    rerender(<Component target={future} />);
    expect(screen.getByTestId('minutes')).toHaveTextContent('1');
  });
});