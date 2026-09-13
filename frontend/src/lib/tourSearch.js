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

export function getTourBanner(tour) {
  return tour?.banner_image || tour?.images?.[0] || null;
}
