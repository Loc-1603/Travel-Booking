/**
 * Parse Laravel paginated guide list: { success, data: { data: guides[], meta: { total, ... } } }
 */
export function parseGuideSearchResponse(apiBody) {
  if (!apiBody?.data) {
    return { guides: [], meta: {}, total: 0 };
  }
  const payload = apiBody.data;
  const guides = Array.isArray(payload) ? payload : (payload?.data ?? []);
  const meta = payload?.meta ?? {};
  const raw = meta.total;
  const n = typeof raw === 'number' ? raw : Number(raw);
  const total = Number.isFinite(n) ? n : guides.length;
  return { guides, meta, total };
}

/** Ranking score: stars x review count (backend `score`, fallback to local calc). */
export function getGuideScore(guide) {
  if (!guide) return 0;
  if (guide.score != null) return Number(guide.score) || 0;
  return (Number(guide.average_rating) || 0) * (Number(guide.review_count) || 0);
}

export function toISODate(d) {
  const c = new Date(d);
  c.setMinutes(c.getMinutes() - c.getTimezoneOffset());
  return c.toISOString().split('T')[0];
}

export function todayISO() {
  return toISODate(new Date());
}
