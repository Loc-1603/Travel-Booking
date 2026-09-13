import { useMemo, useState } from 'react';
import { useQueries } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { ChevronLeft, ChevronRight, Loader2 } from 'lucide-react';
import { api } from '../lib/api';
import { cn } from '../lib/utils';
import { todayISO } from '../lib/guideSearch';

function addDaysISO(iso, n) {
  const d = new Date(`${iso}T00:00:00`);
  d.setDate(d.getDate() + n);
  const c = new Date(d);
  c.setMinutes(c.getMinutes() - c.getTimezoneOffset());
  return c.toISOString().split('T')[0];
}

/**
 * Merged week calendar across all published tours of one guide.
 * Capacity is always 1: only `available` slots are selectable.
 * Selection is { slot, tour } so booking can route to the right wizard.
 */
export function GuideAvailability({ tours = [], selected, onSelect }) {
  const { t } = useTranslation();
  const [weekOffset, setWeekOffset] = useState(0);

  const today = useMemo(() => todayISO(), []);
  const from = addDaysISO(today, weekOffset * 7);
  const to = addDaysISO(today, weekOffset * 7 + 6);

  const results = useQueries({
    queries: tours.map((tour) => ({
      queryKey: ['tour-availability', tour.uuid, from, to],
      queryFn: async () => {
        const res = await api.get(`/tours/${tour.uuid}/availability`, { params: { from, to } });
        if (!res.data?.success) throw new Error(res.data?.message || 'Failed to load availability');
        return { tourUuid: tour.uuid, slots: res.data?.data ?? [] };
      },
      enabled: !!tour.uuid,
      staleTime: 30_000,
    })),
  });

  const isLoading = results.some((r) => r.isLoading);
  const isError = results.some((r) => r.isError);

  const byDate = useMemo(() => {
    const map = {};
    for (const r of results) {
      const tour = tours.find((x) => x.uuid === r.data?.tourUuid);
      if (!tour || !r.data) continue;
      for (const s of r.data.slots) {
        if (s.status !== 'available') continue;
        if (!map[s.date]) map[s.date] = [];
        map[s.date].push({ slot: s, tour });
      }
    }
    // Stable order: by start time.
    for (const k of Object.keys(map)) {
      map[k].sort((a, b) => String(a.slot.start_time).localeCompare(String(b.slot.start_time)));
    }
    return map;
  }, [results, tours]);

  const days = useMemo(
    () => Array.from({ length: 7 }, (_, i) => addDaysISO(today, weekOffset * 7 + i)),
    [today, weekOffset]
  );
  const multiTour = tours.length > 1;

  return (
    <div className="rounded-2xl border border-[#e8e4dd] bg-white p-4 sm:p-5">
      <div className="flex items-center justify-between mb-4">
        <h3 className="font-semibold text-[#1a1a1a]">{t('tours.guides.availability')}</h3>
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
          {days.map((key) => {
            const items = byDate[key] ?? [];
            const label = new Date(`${key}T00:00:00`).toLocaleDateString(undefined, {
              weekday: 'short',
              day: 'numeric',
              month: 'short',
            });
            return (
              <div key={key} className="border-b border-[#f0ede8] last:border-0 pb-3 last:pb-0">
                <p className="text-sm font-medium text-[#45423d] mb-2">{label}</p>
                {items.length === 0 ? (
                  <p className="text-sm text-[#a39e94]">{t('tours.slots.none')}</p>
                ) : (
                  <div className="flex flex-wrap gap-2">
                    {items.map(({ slot: s, tour }) => {
                      const active = selected?.slot?.id === s.id && selected?.tour?.uuid === tour.uuid;
                      return (
                        <button
                          key={`${tour.uuid}-${s.id}`}
                          type="button"
                          title={multiTour ? tour.title : undefined}
                          onClick={() => onSelect?.({ slot: s, tour })}
                          className={cn(
                            'px-3 py-2 rounded-xl border text-sm font-medium transition-colors',
                            active
                              ? 'border-[#b8860b] bg-[#f9edd1] text-[#1a1a1a]'
                              : 'border-[#e8e4dd] hover:border-[#b8860b] hover:bg-[#faf8f5] text-[#45423d]'
                          )}
                        >
                          {s.start_time} – {s.end_time}
                          {multiTour && <span className="block text-[11px] font-normal text-[#7a756d]">{tour.title}</span>}
                        </button>
                      );
                    })}
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
