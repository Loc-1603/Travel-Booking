import { describe, it, expect } from 'vitest';
import {
  getTourPrice,
  getTourPriceParts,
  getTourFromPrice,
  addDaysISO,
  computeTourEnd,
  getMissingAvailableDays,
} from './tourSearch';

describe('getTourPriceParts / getTourFromPrice (FE-4.1)', () => {
  it('includes the fixed fee in each mode total', () => {
    const parts = getTourPriceParts({ base_fixed: 100000, base_price_hourly: 200000, base_price_daily: 1500000 });
    expect(parts).toMatchObject({ fixed: 100000, hourly: 200000, daily: 1500000 });
    expect(parts.hourTotal).toBe(300000);
    expect(parts.dayTotal).toBe(1600000);
  });

  it('returns the cheapest starting total', () => {
    expect(getTourFromPrice({ base_fixed: 100000, base_price_hourly: 200000, base_price_daily: 1500000 })).toBe(300000);
  });

  it('returns null when no rate is set', () => {
    expect(getTourFromPrice({ base_fixed: 0, base_price_hourly: 0, base_price_daily: 0 })).toBeNull();
    expect(getTourFromPrice(null)).toBeNull();
  });

  it('keeps the legacy helper working', () => {
    expect(getTourPrice({ base_fixed: 100000, base_price_hourly: 200000 })).toBe(300000);
  });
});

describe('computeTourEnd (FE-4.3)', () => {
  const slot = { date: '2026-10-15', start_time: '09:00' };

  it('adds hours in hour mode', () => {
    expect(computeTourEnd(slot, 'hour', 4)).toEqual({ date: '2026-10-15', time: '13:00' });
  });

  it('rolls over midnight in hour mode', () => {
    expect(computeTourEnd({ date: '2026-10-15', start_time: '22:00' }, 'hour', 4)).toEqual({
      date: '2026-10-16',
      time: '02:00',
    });
  });

  it('adds days in day mode', () => {
    expect(computeTourEnd(slot, 'day', 2)).toEqual({ date: '2026-10-17', time: '09:00' });
  });

  it('returns null without slot date/time', () => {
    expect(computeTourEnd(null, 'hour', 2)).toBeNull();
    expect(computeTourEnd({ date: '2026-10-15' }, 'hour', 2)).toBeNull();
  });
});

describe('addDaysISO / getMissingAvailableDays (FE-4.3)', () => {
  it('adds days across month boundaries', () => {
    expect(addDaysISO('2026-10-31', 1)).toBe('2026-11-01');
  });

  it('lists days with no available slot', () => {
    const slots = [
      { date: '2026-10-15', status: 'available' },
      { date: '2026-10-16', status: 'booked' },
    ];
    expect(getMissingAvailableDays(slots, '2026-10-15', '2026-10-17')).toEqual([
      '2026-10-16',
      '2026-10-17',
    ]);
  });

  it('returns empty when every day is covered', () => {
    const slots = [
      { date: '2026-10-15', status: 'available' },
      { date: '2026-10-16', status: 'available' },
    ];
    expect(getMissingAvailableDays(slots, '2026-10-15', '2026-10-16')).toEqual([]);
  });
});
