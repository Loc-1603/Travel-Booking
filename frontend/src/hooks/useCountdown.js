import { useEffect, useState } from 'react';

function computeCountdown(target) {
  if (!target) return { total: 0, minutes: 0, seconds: 0, isExpired: false };
  const targetTime = new Date(target).getTime();
  const diff = targetTime - Date.now();
  const total = Math.max(0, Math.floor(diff / 1000));
  return {
    total,
    minutes: Math.floor(total / 60),
    seconds: total % 60,
    isExpired: diff <= 0,
  };
}

/**
 * Countdown to an ISO date target.
 * Returns { total, minutes, seconds, isExpired }.
 * Re-renders on each `interval` tick; the timer stops once expired.
 */
export function useCountdown(target, options = {}) {
  const { interval = 1000 } = options;
  const [, setTick] = useState(0);

  useEffect(() => {
    if (!target) return undefined;
    if (computeCountdown(target).isExpired) return undefined;
    const id = setInterval(() => {
      if (computeCountdown(target).isExpired) {
        clearInterval(id);
      }
      setTick((n) => n + 1);
    }, interval);
    return () => clearInterval(id);
  }, [target, interval]);

  return computeCountdown(target);
}

export default useCountdown;
