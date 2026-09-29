import { describe, it, expect } from 'vitest';
import {
  createDefaultRoomFilters,
  countActiveFilters,
  filterRooms,
  sortRooms,
  groupRoomsByType,
  calculatePriceSummary,
} from './rooms';

// Fixture: mirrors the RoomResource shape (room_type relation, amenities with slug)
const rooms = [
  {
    id: 101,
    name: 'Standard',
    capacity: 2,
    base_price: 500000,
    bed_type: 'double',
    view_type: 'city',
    room_type: { id: 1, name: 'Standard' },
    amenities: [
      { id: 1, slug: 'wifi' },
      { id: 2, slug: 'air_conditioning' },
    ],
  },
  {
    id: 102,
    name: 'Deluxe',
    capacity: 4,
    base_price: 1500000,
    bed_type: 'king',
    view_type: 'sea',
    room_type: { id: 2, name: 'Deluxe' },
    amenities: [{ id: 1, slug: 'wifi' }],
  },
  {
    id: 103,
    name: 'Suite',
    capacity: 6,
    base_price: 900000,
    bed_type: 'queen',
    view_type: 'sea',
    room_type: { id: 2, name: 'Deluxe' },
    amenities: [
      { id: 1, slug: 'wifi' },
      { id: 3, slug: 'bathtub' },
    ],
  },
  {
    id: 104,
    name: 'No type',
    capacity: 2,
    base_price: 700000,
    bed_type: 'double',
    room_type: null,
    amenities: [],
  },
];

describe('createDefaultRoomFilters', () => {
  it('returns the full empty filter shape', () => {
    expect(createDefaultRoomFilters()).toEqual({
      capacity: '',
      price: { min: '', max: '' },
      roomTypes: [],
      bedTypes: [],
      viewTypes: [],
      amenities: [],
    });
  });

  it('returns a fresh mutable copy each call (no shared references)', () => {
    const a = createDefaultRoomFilters();
    const b = createDefaultRoomFilters();
    a.price.min = '100';
    a.roomTypes.push(9);
    expect(b.price.min).toBe('');
    expect(b.roomTypes).toEqual([]);
  });
});

describe('countActiveFilters', () => {
  it('is 0 for the default filters', () => {
    expect(countActiveFilters(createDefaultRoomFilters())).toBe(0);
  });

  it('counts capacity and price-range as 1 chip each', () => {
    expect(
      countActiveFilters({ capacity: '2', price: { min: '100000', max: '' } })
    ).toBe(2);
  });

  it('sums every selected value inside multi-select groups', () => {
    expect(
      countActiveFilters({
        roomTypes: [1, 2],
        bedTypes: ['king'],
        viewTypes: ['sea', 'city', 'mountain'],
        amenities: ['wifi', 'bathtub'],
      })
    ).toBe(8);
  });

  it('treats price 0 as unset (slider floor), empty strings stay inactive', () => {
    // 0 is the PriceRangeSlider floor — not a real constraint (matches HotelDetail semantics)
    expect(countActiveFilters({ price: { min: 0, max: '' } })).toBe(0);
    expect(countActiveFilters({ price: { min: '', max: '' } })).toBe(0);
  });
});

describe('filterRooms', () => {
  it('returns all rooms when no filters are set', () => {
    expect(filterRooms(rooms, createDefaultRoomFilters())).toHaveLength(4);
  });

  it('keeps rooms whose capacity is at least the requested minimum', () => {
    const result = filterRooms(rooms, { capacity: '4' });
    expect(result.map((r) => r.id)).toEqual([102, 103]);
  });

  it('filters by price min and max (inclusive bounds)', () => {
    expect(filterRooms(rooms, { price: { min: '600000', max: '' } }).map((r) => r.id)).toEqual([102, 103, 104]);
    expect(filterRooms(rooms, { price: { min: '', max: '700000' } }).map((r) => r.id)).toEqual([101, 104]);
    expect(filterRooms(rooms, { price: { min: '500000', max: '900000' } }).map((r) => r.id)).toEqual([101, 103, 104]);
  });

  it('treats null price bounds as unset', () => {
    expect(filterRooms(rooms, { price: { min: null, max: null } })).toHaveLength(4);
  });

  it('treats a room without base_price as price 0', () => {
    const freeRoom = { id: 1, capacity: 1, base_price: null, amenities: [] };
    expect(filterRooms([freeRoom], { price: { min: '1', max: '' } })).toHaveLength(0);
    expect(filterRooms([freeRoom], { price: { min: '', max: '1' } })).toHaveLength(1);
  });

  it('filters by room_type id (not name)', () => {
    const result = filterRooms(rooms, { roomTypes: [2] });
    expect(result.map((r) => r.id)).toEqual([102, 103]);
  });

  it('excludes rooms without a room_type when roomTypes is set', () => {
    const result = filterRooms(rooms, { roomTypes: [1] });
    expect(result.map((r) => r.id)).toEqual([101]);
  });

  it('filters by bed type and view type (exact match)', () => {
    expect(filterRooms(rooms, { bedTypes: ['king'] }).map((r) => r.id)).toEqual([102]);
    expect(filterRooms(rooms, { viewTypes: ['sea'] }).map((r) => r.id)).toEqual([102, 103]);
  });

  it('amenities use AND semantics — room must have every selected slug', () => {
    expect(filterRooms(rooms, { amenities: ['wifi'] }).map((r) => r.id)).toEqual([101, 102, 103]);
    expect(filterRooms(rooms, { amenities: ['wifi', 'bathtub'] }).map((r) => r.id)).toEqual([103]);
    expect(filterRooms(rooms, { amenities: ['wifi', 'pool'] })).toHaveLength(0);
  });

  it('combines all groups with AND semantics', () => {
    const result = filterRooms(rooms, {
      capacity: '3',
      price: { min: '600000', max: '1000000' },
      viewTypes: ['sea'],
      amenities: ['wifi'],
    });
    expect(result.map((r) => r.id)).toEqual([103]);
  });
});

describe('sortRooms', () => {
  it('recommended keeps the input order', () => {
    expect(sortRooms(rooms, 'recommended').map((r) => r.id)).toEqual([101, 102, 103, 104]);
  });

  it('sorts by price ascending / descending (missing price treated as 0)', () => {
    expect(sortRooms(rooms, 'price_asc').map((r) => r.id)).toEqual([101, 104, 103, 102]);
    expect(sortRooms(rooms, 'price_desc').map((r) => r.id)).toEqual([102, 103, 104, 101]);
  });

  it('sorts by capacity descending', () => {
    expect(sortRooms(rooms, 'capacity').map((r) => r.id)).toEqual([103, 102, 101, 104]);
  });

  it('does not mutate the input array', () => {
    const input = [...rooms];
    sortRooms(input, 'price_asc');
    expect(input.map((r) => r.id)).toEqual([101, 102, 103, 104]);
  });

  it('falls back to stable order for an unknown sort key', () => {
    expect(sortRooms(rooms, 'nonsense').map((r) => r.id)).toEqual([101, 102, 103, 104]);
  });
});

describe('groupRoomsByType', () => {
  it('groups by room_type name preserving first-appearance order', () => {
    const groups = groupRoomsByType(sortRooms(rooms, 'price_asc'));
    expect(groups.map((g) => g.type)).toEqual(['Standard', 'Khác', 'Deluxe']);
    expect(groups.find((g) => g.type === 'Deluxe')?.rooms.map((r) => r.id)).toEqual([103, 102]);
  });

  it('puts rooms without a room_type into the "other" label', () => {
    const groups = groupRoomsByType([rooms[3]], 'Other');
    expect(groups).toEqual([{ type: 'Other', rooms: [rooms[3]] }]);
  });

  it('returns an empty array for empty input', () => {
    expect(groupRoomsByType([])).toEqual([]);
  });
});

describe('calculatePriceSummary', () => {
  it('returns null when there is no room price or no nights', () => {
    expect(calculatePriceSummary({ basePrice: null, nights: 2 })).toBeNull();
    expect(calculatePriceSummary({ basePrice: 100, nights: 0 })).toBeNull();
    expect(calculatePriceSummary({ basePrice: 100, nights: -1 })).toBeNull();
    expect(calculatePriceSummary({})).toBeNull();
  });

  it('multiplies base price by nights and quantity', () => {
    expect(calculatePriceSummary({ basePrice: 1500000, nights: 2, quantity: 1 })).toEqual({
      subTotal: 3000000,
      taxAmount: 0,
      taxRatePct: 0,
      total: 3000000,
    });
    expect(calculatePriceSummary({ basePrice: '500000', nights: 3, quantity: 2, taxRate: 0 }).subTotal).toBe(3000000);
  });

  it('adds tax on top of the subtotal unless tax-inclusive', () => {
    const exclusive = calculatePriceSummary({ basePrice: 1000000, nights: 2, quantity: 1, taxRate: 0.1 });
    expect(exclusive).toEqual({ subTotal: 2000000, taxAmount: 200000, taxRatePct: 10, total: 2200000 });

    const inclusive = calculatePriceSummary({ basePrice: 1000000, nights: 2, taxRate: 0.1, taxInclusive: true });
    expect(inclusive).toEqual({ subTotal: 2000000, taxAmount: 0, taxRatePct: 10, total: 2000000 });
  });
});
