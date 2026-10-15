/**
 * Parse Laravel paginated tour list: { success, data: { data: tours[], meta: { total, ... } } }
 */
export function parseTourSearchResponse(apiBody) {
  if (!apiBody?.data) {
    return { tours: [], meta: {}, total: 0 };
  }
  const payload = apiBody.data;
  const tours = Array.isArray(payload) ? payload : (payload?.data ?? []);
  const meta = payload?.meta ?? {};
  const raw = meta.total;
  const n = typeof raw === 'number' ? raw : Number(raw);
  const total = Number.isFinite(n) ? n : tours.length;
  return { tours, meta, total };
}

/** Starting price display: fixed fee + cheapest hourly rate */
export function getTourPrice(tour) {
  if (!tour) return null;
  const fixed = Number(tour.base_fixed) || 0;
  const hourly = Number(tour.base_price_hourly) || 0;
  return fixed + hourly;
}

/**
 * Unit-aware rate parts (FE-4.1): totals already include the fixed fee so
 * labels can show the correct unit ("1 giờ" / "1 ngày").
 */
export function getTourPriceParts(tour) {
  if (!tour) return null;
  const fixed = Number(tour.base_fixed) || 0;
  const hourly = Number(tour.base_price_hourly) || 0;
  const daily = Number(tour.base_price_daily) || 0;
  return { fixed, hourly, daily, hourTotal: fixed + hourly, dayTotal: fixed + daily };
}

/** Cheapest bookable starting total ("Từ ..."), or null when no rate set. */
export function getTourFromPrice(tour) {
  const parts = getTourPriceParts(tour);
  if (!parts) return null;
  const totals = [parts.hourTotal, parts.dayTotal].filter((v) => v > 0);
  return totals.length > 0 ? Math.min(...totals) : null;
}

function pad2(n) {
  return String(n).padStart(2, '0');
}

function parseISODate(iso) {
  const [y, m, d] = String(iso).split('-').map(Number);
  return { y, m: m || 1, d: d || 1 };
}

/** ISO date plus n days (string-safe, no TZ shift). */
export function addDaysISO(iso, n) {
  const { y, m, d } = parseISODate(iso);
  const dt = new Date(y, m - 1, d);
  dt.setDate(dt.getDate() + n);
  return `${dt.getFullYear()}-${pad2(dt.getMonth() + 1)}-${pad2(dt.getDate())}`;
}

/**
 * Expected trip end from a slot + duration (FE-4.3).
 * hour mode: start + N hours (may cross midnight); day mode: start + N days.
 * Returns { date: 'YYYY-MM-DD', time: 'HH:mm' } or null.
 */
export function computeTourEnd(slot, pricingMode, durationValue) {
  if (!slot?.date || !slot?.start_time) return null;
  const { y, m, d } = parseISODate(slot.date);
  const [hh, mm] = String(slot.start_time).split(':').map(Number);
  const start = new Date(y, m - 1, d, hh || 0, mm || 0);
  if (Number.isNaN(start.getTime())) return null;
  const end = new Date(start);
  const n = Number(durationValue) || 0;
  if (pricingMode === 'day') end.setDate(end.getDate() + n);
  else end.setHours(end.getHours() + n);
  return {
    date: `${end.getFullYear()}-${pad2(end.getMonth() + 1)}-${pad2(end.getDate())}`,
    time: `${pad2(end.getHours())}:${pad2(end.getMinutes())}`,
  };
}

/**
 * Days in [fromISO, toISO] with no available slot (FE-4.3 day-mode warning).
 */
export function getMissingAvailableDays(slots, fromISO, toISO) {
  const available = new Set(
    (slots || []).filter((s) => s?.status === 'available' && s.date).map((s) => s.date)
  );
  const missing = [];
  for (let cur = fromISO; cur <= toISO; cur = addDaysISO(cur, 1)) {
    if (!available.has(cur)) missing.push(cur);
  }
  return missing;
}

export function getTourBanner(tour) {
  return tour?.banner_image || tour?.images?.[0] || null;
}
