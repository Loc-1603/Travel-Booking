/**
 * Local-timezone ISO date helpers (YYYY-MM-DD) for room search.
 * Date.toISOString() is UTC-based and shifts the calendar day in +07:00 —
 * always construct/format in local time instead.
 */

export function pad2(n) {
  return String(n).padStart(2, '0');
}

export function toISODateLocal(d) {
  return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;
}

export function parseISODateLocal(s) {
  const [y, m, day] = String(s || '').split('-').map(Number);
  return new Date(y, (m || 1) - 1, day || 1);
}

export function addDaysLocal(date, n) {
  const d = new Date(date);
  d.setDate(d.getDate() + n);
  return d;
}

export function isValidISODate(s) {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(s || '')) return false;
  return !Number.isNaN(parseISODateLocal(s).getTime());
}
