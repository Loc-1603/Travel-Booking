/**
 * Pure room-domain logic for the HotelDetail "Phòng" tab + booking widget.
 * Extracted from HotelDetail.jsx (Phase 8) so filter/sort/price rules are unit-testable.
 * No React, no i18n, no DOM — keeps HotelDetail.jsx to orchestration only.
 */

export const DEFAULT_ROOM_FILTERS = Object.freeze({
  capacity: '',
  price: { min: '', max: '' },
  roomTypes: [],
  bedTypes: [],
  viewTypes: [],
  amenities: [],
});

export function createDefaultRoomFilters() {
  // Fresh mutable copy — consumers spread/merge into it via onFiltersChange
  return {
    capacity: DEFAULT_ROOM_FILTERS.capacity,
    price: { ...DEFAULT_ROOM_FILTERS.price },
    roomTypes: [],
    bedTypes: [],
    viewTypes: [],
    amenities: [],
  };
}

/** Number of active filter chips for the mobile "Bộ lọc" badge. */
export function countActiveFilters(filters = {}) {
  let count = 0;
  if (filters.capacity) count++;
  if (filters.price?.min || filters.price?.max) count++;
  if (filters.roomTypes?.length) count += filters.roomTypes.length;
  if (filters.bedTypes?.length) count += filters.bedTypes.length;
  if (filters.viewTypes?.length) count += filters.viewTypes.length;
  if (filters.amenities?.length) count += filters.amenities.length;
  return count;
}

/**
 * AND-semantics across filter groups, OR-semantics within a group.
 * `price.min/max` may be '' or null (unset) or number/string.
 */
export function filterRooms(rooms = [], filters = {}) {  const { capacity, price, roomTypes, bedTypes, viewTypes, amenities } = filters;
  return rooms.filter((room) => {
    if (capacity && Number(room.capacity) < Number(capacity)) return false;
    const roomPrice = Number(room.base_price || 0);
    if (price?.min !== '' && price?.min != null && roomPrice < Number(price.min)) return false;
    if (price?.max !== '' && price?.max != null && roomPrice > Number(price.max)) return false;
    if (roomTypes?.length && !roomTypes.includes(room.room_type?.id)) return false;
    if (bedTypes?.length && !bedTypes.includes(room.bed_type)) return false;
    if (viewTypes?.length && !viewTypes.includes(room.view_type)) return false;
    if (amenities?.length) {
      const slugs = (room.amenities || []).map((a) => a.slug);
      if (!amenities.every((slug) => slugs.includes(slug))) return false;
    }
    return true;
  });
}

/**
 * Availability + guest filter for the room search bar (redesign FE-8).
 * Note: the parameter is named `roomCount` (not `rooms`) because duplicate
 * parameter names are a SyntaxError in modules.
 *
 * - `capacity >= adults` always applies (client-side guest fit).
 * - `min_available_rooms >= roomCount` applies only when the API returned
 *   availability for the requested dates. A missing field (no dates, legacy
 *   payload) must NOT hide rooms — the server is the source of truth for
 *   date filtering (BE-1 already filters by quantity server-side).
 */
export function filterRoomsByAvailabilityAndGuests(roomList = [], checkIn, checkOut, adults, roomCount = 1) {
  const needAdults = Number(adults) || 0;
  const needRooms = Number(roomCount) || 1;
  const hasDates = Boolean(checkIn && checkOut && checkOut > checkIn);
  return (roomList || []).filter((room) => {
    if (needAdults > 0 && Number(room.capacity || 0) < needAdults) return false;
    if (
      hasDates &&
      room.min_available_rooms != null &&
      Number(room.min_available_rooms) < needRooms
    ) {
      return false;
    }
    return true;
  });
}

/** 'recommended' keeps the (already filtered) order stable — only explicit sorts mutate. */export function sortRooms(rooms = [], sortBy = 'recommended') {
  const list = [...rooms];
  if (sortBy === 'price_asc') {
    list.sort((a, b) => Number(a.base_price || 0) - Number(b.base_price || 0));
  } else if (sortBy === 'price_desc') {
    list.sort((a, b) => Number(b.base_price || 0) - Number(a.base_price || 0));
  } else if (sortBy === 'capacity') {
    list.sort((a, b) => Number(b.capacity || 0) - Number(a.capacity || 0));
  }
  return list;
}

/** Group rooms by room_type name (rooms without a type fall into `otherTypeLabel`). */
export function groupRoomsByType(rooms = [], otherTypeLabel = 'Khác') {
  const groups = new Map();
  rooms.forEach((room) => {
    const type = room.room_type?.name || otherTypeLabel;
    if (!groups.has(type)) groups.set(type, []);
    groups.get(type).push(room);
  });
  return Array.from(groups.entries()).map(([type, typeRooms]) => ({ type, rooms: typeRooms }));
}

/**
 * Booking widget price summary: base_price × nights × quantity, plus tax
 * unless the hotel price is already tax-inclusive.
 */
export function calculatePriceSummary({
  basePrice,
  nights,
  quantity = 1,
  taxRate = 0,
  taxInclusive = false,
} = {}) {
  if (basePrice == null || !nights || nights < 1) return null;
  const subTotal = Number(basePrice) * nights * quantity;
  const taxAmount = taxInclusive ? 0 : subTotal * taxRate;
  return {
    subTotal,
    taxAmount,
    taxRatePct: taxRate * 100,
    total: subTotal + taxAmount,
  };
}
