import { useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { ChevronLeft, ChevronRight, Loader2 } from 'lucide-react';
import { api } from '../lib/api';
import { cn, formatPrice } from '../lib/utils';

const MAX_WEEK_OFFSET = 11; // 12 weeks — extended past the old 8-week cap
const MAX_MONTH_OFFSET = 5; // current month + 5 ahead

function toISODate(d) {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function addDays(d, n) {
  const c = new Date(d);
  c.setDate(c.getDate() + n);
  return c;
}

function startOfMonth(d) {
  return new Date(d.getFullYear(), d.getMonth(), 1);
}

function addMonths(d, n) {
  return new Date(d.getFullYear(), d.getMonth() + n, 1);
}

function endOfMonth(d) {
  return new Date(d.getFullYear(), d.getMonth() + 1, 0);
}

function minOverride(slots) {
  const values = (slots ?? [])
    .map((s) => (s.price_override != null ? Number(s.price_override) : null))
    .filter((v) => v != null && Number.isFinite(v));
  return values.length > 0 ? Math.min(...values) : null;
}

function SlotButton({ slot, selected, onSelect }) {
  return (
    <button
      type="button"
      onClick={() => onSelect?.(slot)}
      className={cn(
        'px-3 py-2 rounded-xl border text-sm font-medium transition-colors text-left',
        selected
          ? 'border-accent bg-accent-100 text-neutral-900'
          : 'border-neutral-200 hover:border-accent hover:bg-neutral-50 text-neutral-700'
      )}
    >
      <span className="block">
        {slot.start_time} – {slot.end_time}
      </span>
      {slot.price_override != null && (
        <span data-testid="slot-price-badge" className="mt-0.5 block text-xs font-semibold text-accent-600">
          {formatPrice(Number(slot.price_override), 'VND')}
        </span>
      )}
    </button>
  );
}

/**
 * Slot picker for 1vs1 tours. Capacity is always 1: a slot is either
 * available (selectable) or not shown/disabled.
 *
 * Week view pages 7 days at a time (up to 12 weeks out); month view shows a
 * full calendar grid — today is highlighted, past days are locked, and days
 * with a `price_override` slot carry a price badge.
 */
export function TourSlotCalendar({ tourUuid, selectedSlotId, onSelect }) {
  const { t, i18n } = useTranslation();
  const [view, setView] = useState('week');
  const [weekOffset, setWeekOffset] = useState(0);
  const [monthOffset, setMonthOffset] = useState(0);
  const [selectedDate, setSelectedDate] = useState(null);
  const locale = i18n.language?.startsWith('vi') ? 'vi-VN' : 'en-US';

  const today = useMemo(() => {
    const d = new Date();
    d.setHours(0, 0, 0, 0);
    return d;
  }, []);
  const todayKey = toISODate(today);

  const { from, to } = useMemo(() => {
    if (view === 'month') {
      const first = startOfMonth(addMonths(today, monthOffset));
      const last = endOfMonth(addMonths(today, monthOffset));
      return {
        from: toISODate(first < today ? today : first),
        to: toISODate(last),
      };
    }
    return {
      from: toISODate(addDays(today, weekOffset * 7)),
      to: toISODate(addDays(today, weekOffset * 7 + 6)),
    };
  }, [view, today, weekOffset, monthOffset]);

  const { data, isLoading, isError } = useQuery({
    queryKey: ['tour-availability', tourUuid, view, from, to],
    queryFn: async () => {
      const res = await api.get(`/tours/${tourUuid}/availability`, { params: { from, to } });
      if (!res.data?.success) throw new Error(res.data?.message || 'Failed to load availability');
      return res.data;
    },
    enabled: !!tourUuid,
    staleTime: 30_000,
  });

  const byDate = useMemo(() => {
    const slots = data?.data ?? [];
    const map = {};
    for (const s of slots) {
      if (!map[s.date]) map[s.date] = [];
      map[s.date].push(s);
    }
    return map;
  }, [data]);

  const availableByDate = useMemo(() => {
    const map = {};
    for (const [date, slots] of Object.entries(byDate)) {
      map[date] = slots.filter((s) => s.status === 'available');
    }
    return map;
  }, [byDate]);

  const weekDays = useMemo(
    () => Array.from({ length: 7 }, (_, i) => addDays(today, weekOffset * 7 + i)),
    [today, weekOffset]
  );

  const monthCells = useMemo(() => {
    if (view !== 'month') return [];
    const first = startOfMonth(addMonths(today, monthOffset));
    // Monday-first grid.
    const blanks = (first.getDay() + 6) % 7;
    const daysInMonth = endOfMonth(first).getDate();
    const cells = [];
    for (let i = 0; i < blanks; i += 1) cells.push(null);
    for (let d = 1; d <= daysInMonth; d += 1) {
      cells.push(new Date(first.getFullYear(), first.getMonth(), d));
    }
    return cells;
  }, [view, today, monthOffset]);

  const monthTitle = useMemo(() => {
    const first = startOfMonth(addMonths(today, monthOffset));
    return first.toLocaleDateString(locale, { month: 'long', year: 'numeric' });
  }, [today, monthOffset, locale]);

  const weekdayNames = useMemo(() => {
    // Monday-first short names derived from a known Monday (2026-10-05).
    const monday = new Date(2026, 9, 5);
    return Array.from({ length: 7 }, (_, i) =>
      addDays(monday, i).toLocaleDateString(locale, { weekday: 'short' })
    );
  }, [locale]);

  const activeDateSlots = selectedDate ? (availableByDate[selectedDate] ?? []) : [];

  return (
    <div className="rounded-2xl border border-neutral-200 bg-white p-4 sm:p-5">
      <div className="flex items-center justify-between gap-2 mb-4">
        <h3 className="font-semibold text-neutral-900">{t('tours.slots.title')}</h3>
        <div className="flex items-center gap-1" role="group" aria-label={t('tours.slots.title')}>
          <button
            type="button"
            onClick={() => setView('week')}
            aria-pressed={view === 'week'}
            className={cn(
              'px-3 py-1.5 rounded-lg border text-sm font-medium',
              view === 'week'
                ? 'border-accent bg-accent-100 text-neutral-900'
                : 'border-neutral-200 hover:bg-neutral-50 text-neutral-600'
            )}
          >
            {t('tours.slots.weekView')}
          </button>
          <button
            type="button"
            onClick={() => setView('month')}
            aria-pressed={view === 'month'}
            className={cn(
              'px-3 py-1.5 rounded-lg border text-sm font-medium',
              view === 'month'
                ? 'border-accent bg-accent-100 text-neutral-900'
                : 'border-neutral-200 hover:bg-neutral-50 text-neutral-600'
            )}
          >
            {t('tours.slots.monthView')}
          </button>
        </div>
      </div>

      {view === 'week' ? (
        <div className="flex items-center justify-between mb-4">
          <span className="text-sm text-neutral-600">
            {weekDays[0].toLocaleDateString(locale, { day: 'numeric', month: 'short' })} –{' '}
            {weekDays[6].toLocaleDateString(locale, { day: 'numeric', month: 'short' })}
          </span>
          <div className="flex items-center gap-1">
            <button
              type="button"
              onClick={() => setWeekOffset((o) => Math.max(0, o - 1))}
              disabled={weekOffset <= 0}
              className="p-2 rounded-lg border border-neutral-200 hover:bg-neutral-50 disabled:opacity-40"
              aria-label={t('tours.slots.prevWeek')}
            >
              <ChevronLeft className="w-4 h-4" />
            </button>
            <button
              type="button"
              onClick={() => setWeekOffset((o) => Math.min(MAX_WEEK_OFFSET, o + 1))}
              disabled={weekOffset >= MAX_WEEK_OFFSET}
              className="p-2 rounded-lg border border-neutral-200 hover:bg-neutral-50 disabled:opacity-40"
              aria-label={t('tours.slots.nextWeek')}
            >
              <ChevronRight className="w-4 h-4" />
            </button>
          </div>
        </div>
      ) : (
        <div className="flex items-center justify-between mb-4">
          <span className="text-sm font-medium text-neutral-700 capitalize">{monthTitle}</span>
          <div className="flex items-center gap-1">
            <button
              type="button"
              onClick={() => setMonthOffset((o) => Math.max(0, o - 1))}
              disabled={monthOffset <= 0}
              className="p-2 rounded-lg border border-neutral-200 hover:bg-neutral-50 disabled:opacity-40"
              aria-label={t('tours.slots.prevMonth')}
            >
              <ChevronLeft className="w-4 h-4" />
            </button>
            <button
              type="button"
              onClick={() => setMonthOffset((o) => Math.min(MAX_MONTH_OFFSET, o + 1))}
              disabled={monthOffset >= MAX_MONTH_OFFSET}
              className="p-2 rounded-lg border border-neutral-200 hover:bg-neutral-50 disabled:opacity-40"
              aria-label={t('tours.slots.nextMonth')}
            >
              <ChevronRight className="w-4 h-4" />
            </button>
          </div>
        </div>
      )}

      {isLoading && (
        <div className="flex items-center justify-center py-8">
          <Loader2 className="w-6 h-6 animate-spin text-accent" />
        </div>
      )}
      {isError && <p className="text-sm text-red-600">{t('tours.slots.couldNotLoad')}</p>}

      {!isLoading && !isError && view === 'week' && (
        <div className="space-y-3">
          {weekDays.map((d) => {
            const key = toISODate(d);
            const daySlots = availableByDate[key] ?? [];
            const isToday = key === todayKey;
            return (
              <div key={key} className="border-b border-neutral-100 last:border-0 pb-3 last:pb-0">
                <p className="text-sm font-medium text-neutral-700 mb-2 flex items-center gap-2">
                  {d.toLocaleDateString(locale, { weekday: 'short', day: 'numeric', month: 'short' })}
                  {isToday && (
                    <span
                      data-testid="today-badge"
                      aria-current="date"
                      className="text-xs px-2 py-0.5 rounded-full bg-accent-100 text-accent-600 font-semibold"
                    >
                      {t('tours.slots.today')}
                    </span>
                  )}
                </p>
                {daySlots.length === 0 ? (
                  <p className="text-sm text-neutral-500">{t('tours.slots.none')}</p>
                ) : (
                  <div className="flex flex-wrap gap-2">
                    {daySlots.map((s) => (
                      <SlotButton key={s.id} slot={s} selected={selectedSlotId === s.id} onSelect={onSelect} />
                    ))}
                  </div>
                )}
              </div>
            );
          })}
        </div>
      )}

      {!isLoading && !isError && view === 'month' && (
        <div>
          <div className="grid grid-cols-7 gap-1 mb-1" aria-hidden="true">
            {weekdayNames.map((name) => (
              <div key={name} className="text-center text-xs font-medium text-neutral-500 py-1">
                {name}
              </div>
            ))}
          </div>
          <div className="grid grid-cols-7 gap-1" role="group" aria-label={monthTitle}>
            {monthCells.map((d, i) => {
              if (!d) return <div key={`blank-${i}`} />;
              const key = toISODate(d);
              const isPast = key < todayKey;
              const isToday = key === todayKey;
              const count = (availableByDate[key] ?? []).length;
              const dayMin = minOverride(availableByDate[key]);
              const isSelected = selectedDate === key;
              return (
                <button
                  key={key}
                  type="button"
                  disabled={isPast || count === 0}
                  onClick={() => setSelectedDate(key)}
                  aria-pressed={isSelected}
                  aria-label={t('tours.slots.daySlots', { date: key, count })}
                  className={cn(
                    'min-h-12 rounded-xl border px-1 py-1.5 text-center transition-colors flex flex-col items-center justify-center gap-0.5',
                    isPast && 'border-transparent text-neutral-300 cursor-not-allowed',
                    !isPast && count === 0 && 'border-transparent text-neutral-500',
                    !isPast &&
                      count > 0 &&
                      !isSelected &&
                      'border-neutral-200 hover:border-accent hover:bg-neutral-50 text-neutral-900',
                    isSelected && 'border-accent bg-accent-100 text-neutral-900',
                    isToday && !isPast && 'ring-2 ring-accent ring-offset-1'
                  )}
                >
                  <span className={cn('text-sm leading-none', (isToday || isSelected) && 'font-bold')}>
                    {d.getDate()}
                  </span>
                  {isToday && (
                    <span data-testid="today-badge" aria-current="date" className="text-[10px] leading-none font-semibold text-accent-600">
                      {t('tours.slots.today')}
                    </span>
                  )}
                  {count > 0 && (
                    <span className="text-[10px] leading-none text-neutral-600">
                      {count}
                    </span>
                  )}
                  {dayMin != null && (
                    <span data-testid="day-price-badge" className="text-[10px] leading-none font-semibold text-accent-600">
                      {formatPrice(dayMin, 'VND')}
                    </span>
                  )}
                </button>
              );
            })}
          </div>

          {selectedDate && (
            <div className="mt-4 border-t border-neutral-100 pt-3">
              <p className="text-sm font-medium text-neutral-700 mb-2">{selectedDate}</p>
              {activeDateSlots.length === 0 ? (
                <p className="text-sm text-neutral-500">{t('tours.slots.none')}</p>
              ) : (
                <div className="flex flex-wrap gap-2">
                  {activeDateSlots.map((s) => (
                    <SlotButton key={s.id} slot={s} selected={selectedSlotId === s.id} onSelect={onSelect} />
                  ))}
                </div>
              )}
            </div>
          )}
        </div>
      )}
    </div>
  );
}
