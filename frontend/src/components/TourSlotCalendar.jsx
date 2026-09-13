import { useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { ChevronLeft, ChevronRight, Loader2 } from 'lucide-react';
import { api } from '../lib/api';
import { cn } from '../lib/utils';

function toISODate(d) {
  return d.toISOString().split('T')[0];
}

function addDays(d, n) {
  const c = new Date(d);
  c.setDate(c.getDate() + n);
  return c;
}

/**
 * Slot picker for 1vs1 tours. Capacity is always 1: a slot is either
 * available (selectable) or not shown/disabled.
 */
export function TourSlotCalendar({ tourUuid, selectedSlotId, onSelect }) {
  const { t } = useTranslation();
  const [weekOffset, setWeekOffset] = useState(0);

  const today = useMemo(() => {
    const d = new Date();
    d.setHours(0, 0, 0, 0);
    return d;
  }, []);

  const from = toISODate(addDays(today, weekOffset * 7));
  const to = toISODate(addDays(today, weekOffset * 7 + 6));

  const { data, isLoading, isError } = useQuery({
    queryKey: ['tour-availability', tourUuid, from, to],
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

  const days = useMemo(() => Array.from({ length: 7 }, (_, i) => addDays(today, weekOffset * 7 + i)), [today, weekOffset]);

  return (
    <div className="rounded-2xl border border-[#e8e4dd] bg-white p-4 sm:p-5">
      <div className="flex items-center justify-between mb-4">
        <h3 className="font-semibold text-[#1a1a1a]">{t('tours.slots.title')}</h3>
        <div className="flex items-center gap-1">
          <button
            type="button"
            onClick={() => setWeekOffset((o) => Math.max(0, o - 1))}
            disabled={weekOffset <= 0}
            className="p-2 rounded-lg border border-[#e8e4dd] hover:bg-[#faf8f5] disabled:opacity-40"
            aria-label={t('tours.slots.prevWeek')}
          >
            <ChevronLeft className="w-4 h-4" />
          </button>
          <button
            type="button"
            onClick={() => setWeekOffset((o) => Math.min(7, o + 1))}
            disabled={weekOffset >= 7}
            className="p-2 rounded-lg border border-[#e8e4dd] hover:bg-[#faf8f5] disabled:opacity-40"
            aria-label={t('tours.slots.nextWeek')}
          >
            <ChevronRight className="w-4 h-4" />
          </button>
        </div>
      </div>

      {isLoading && (
        <div className="flex items-center justify-center py-8">
          <Loader2 className="w-6 h-6 animate-spin text-[#b8860b]" />
        </div>
      )}
      {isError && <p className="text-sm text-red-600">{t('tours.slots.couldNotLoad')}</p>}

      {!isLoading && !isError && (
        <div className="space-y-3">
          {days.map((d) => {
            const key = toISODate(d);
            const daySlots = (byDate[key] ?? []).filter((s) => s.status === 'available');
            return (
              <div key={key} className="border-b border-[#f0ede8] last:border-0 pb-3 last:pb-0">
                <p className="text-sm font-medium text-[#45423d] mb-2">
                  {d.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short' })}
                </p>
                {daySlots.length === 0 ? (
                  <p className="text-sm text-[#a39e94]">{t('tours.slots.none')}</p>
                ) : (
                  <div className="flex flex-wrap gap-2">
                    {daySlots.map((s) => (
                      <button
                        key={s.id}
                        type="button"
                        onClick={() => onSelect?.(s)}
                        className={cn(
                          'px-3 py-2 rounded-xl border text-sm font-medium transition-colors',
                          selectedSlotId === s.id
                            ? 'border-[#b8860b] bg-[#f9edd1] text-[#1a1a1a]'
                            : 'border-[#e8e4dd] hover:border-[#b8860b] hover:bg-[#faf8f5] text-[#45423d]'
                        )}
                      >
                        {s.start_time} – {s.end_time}
                      </button>
                    ))}
                  </div>
                )}
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
