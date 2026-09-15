import { describe, it, expect, vi } from 'vitest';
import { getAmenityLabel } from '../lib/amenities';

// Mock t function for testing
const createMockT = (translations: Record<string, string>) => {
  return (key: string, options?: { defaultValue?: string }) => {
    return translations[key] || options?.defaultValue || key;
  };
};

describe('getAmenityLabel', () => {
  it('returns translated label for known slug in Vietnamese', () => {
    const t = createMockT({
      'amenities.wifi': 'Wi-Fi miễn phí',
      'amenities.pool': 'Hồ bơi',
    });

    expect(getAmenityLabel(t, { slug: 'wifi', name: 'Free Wi-Fi' })).toBe('Wi-Fi miễn phí');
    expect(getAmenityLabel(t, { slug: 'pool', name: 'Swimming Pool' })).toBe('Hồ bơi');
  });

  it('returns fallback name for unknown slug', () => {
    const t = createMockT({});

    expect(getAmenityLabel(t, { slug: 'unknown', name: 'Custom Amenity' })).toBe('Custom Amenity');
  });

  it('returns slug as last resort when name is missing', () => {
    const t = createMockT({});

    expect(getAmenityLabel(t, { slug: 'no-name' })).toBe('no-name');
  });

  it('handles string input (slug only)', () => {
    const t = createMockT({
      'amenities.wifi': 'Wi-Fi miễn phí',
    });

    expect(getAmenityLabel(t, 'wifi')).toBe('Wi-Fi miễn phí');
    expect(getAmenityLabel(t, 'unknown')).toBe('unknown');
  });

  it('handles null/undefined gracefully', () => {
    const t = createMockT({});

    expect(getAmenityLabel(t, null as any)).toBe('');
    expect(getAmenityLabel(t, undefined as any)).toBe('');
  });
});