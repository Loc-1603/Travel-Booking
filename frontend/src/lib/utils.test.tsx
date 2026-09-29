import { describe, it, expect } from 'vitest';
import { calculateNights, formatPrice, getRatingLabel, cn } from './utils';

describe('calculateNights', () => {
  it('counts nights between two ISO dates', () => {
    expect(calculateNights('2026-10-01', '2026-10-03')).toBe(2);
    expect(calculateNights('2026-10-01', '2026-10-02')).toBe(1);
    expect(calculateNights('2026-10-01', '2026-10-01')).toBe(0);
  });

  it('works across month and year boundaries', () => {
    expect(calculateNights('2026-09-30', '2026-10-02')).toBe(2);
    expect(calculateNights('2026-12-30', '2027-01-02')).toBe(3);
  });

  it('is symmetric when the arguments are swapped', () => {
    expect(calculateNights('2026-10-03', '2026-10-01')).toBe(2);
  });
});

describe('formatPrice', () => {
  it('formats VND with Vietnamese thousand separators and no decimals', () => {
    expect(formatPrice(1500000)).toMatch(/1\.500\.000/);
    expect(formatPrice(1500000)).toMatch(/₫/);
    expect(formatPrice(0)).toMatch(/^0/);
  });

  it('supports another currency', () => {
    expect(formatPrice(99, 'USD')).toMatch(/99/);
    expect(formatPrice(99, 'USD')).toMatch(/US\$|\$/);
  });
});

describe('getRatingLabel', () => {
  it('maps score ranges to labels', () => {
    expect(getRatingLabel(9.6)).toBe('Exceptional');
    expect(getRatingLabel(8.2)).toBe('Great');
    expect(getRatingLabel(7)).toBe('Good');
    expect(getRatingLabel(6.4)).toBe('Pleasant');
    expect(getRatingLabel('9')).toBe('Exceptional');
  });
});

describe('cn', () => {
  it('merges class names and lets later Tailwind utilities win', () => {
    expect(cn('px-4', 'px-6')).toBe('px-6');
    expect(cn('a b', false && 'c', 'd')).toBe('a b d');
  });
});
