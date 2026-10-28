import { useEffect, useRef, useState } from 'react';
import { Minus, Plus, Search, SlidersHorizontal, Users } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { DatePicker } from './DatePicker';
import { cn } from '../lib/utils';
import { toISODateLocal, parseISODateLocal, addDaysLocal } from '../lib/dates';

const ADULTS_MIN = 1;
const ADULTS_MAX = 20;
const ROOMS_MIN = 1;
const ROOMS_MAX = 10;

function Stepper({ label, value, min, max, onChange, decreaseLabel, increaseLabel }) {
  return (
    <div className="flex items-center justify-between gap-4 py-2">
      <span className="text-sm font-medium text-[#1a1a1a]">{label}</span>
      <div className="flex items-center gap-3">
        <button
          type="button"
          onClick={() => onChange(Math.max(min, value - 1))}
          disabled={value <= min}
          aria-label={decreaseLabel}
          className="w-8 h-8 rounded-full border border-[#e8e4dd] inline-flex items-center justify-center hover:bg-[#faf8f5] disabled:opacity-40 transition-colors"
        >
          <Minus className="w-4 h-4" />
        </button>
        <span className="w-6 text-center text-sm font-semibold text-[#1a1a1a]" aria-live="polite">{value}</span>
        <button
          type="button"
          onClick={() => onChange(Math.min(max, value + 1))}
          disabled={value >= max}
          aria-label={increaseLabel}
          className="w-8 h-8 rounded-full border border-[#e8e4dd] inline-flex items-center justify-center hover:bg-[#faf8f5] disabled:opacity-40 transition-colors"
        >
          <Plus className="w-4 h-4" />
        </button>
      </div>
    </div>
  );
}

/**
 * Room search filter bar for the HotelDetail "Phòng" tab (redesign FE-3).
 * Check-in / check-out dates, adults-only guest selector, search + filter buttons.
 */
export function HotelRoomFilterBar({
  roomSearch,
  onSearchChange,
  onSearch,
  onFilterClick,
  isLoading = false,
  activeFilterCount = 0,
}) {
  const { t } = useTranslation();
  const [guestOpen, setGuestOpen] = useState(false);
  const guestRef = useRef(null);

  const checkIn = roomSearch?.checkIn ?? '';
  const checkOut = roomSearch?.checkOut ?? '';
  const adults = roomSearch?.adults ?? 1;
  const rooms = roomSearch?.rooms ?? 1;

  useEffect(() => {
    if (!guestOpen) return;
    const onPointerDown = (e) => {
      if (guestRef.current && !guestRef.current.contains(e.target)) setGuestOpen(false);
    };
    const onKeyDown = (e) => {
      if (e.key === 'Escape') setGuestOpen(false);
    };
    document.addEventListener('mousedown', onPointerDown);
    document.addEventListener('keydown', onKeyDown);
    return () => {
      document.removeEventListener('mousedown', onPointerDown);
      document.removeEventListener('keydown', onKeyDown);
    };
  }, [guestOpen]);

  const todayStr = toISODateLocal(new Date());
  const minCheckOut = checkIn ? toISODateLocal(addDaysLocal(parseISODateLocal(checkIn), 1)) : todayStr;
  const isValid = Boolean(checkIn && checkOut && checkOut > checkIn);

  const patch = (p) => onSearchChange?.(p);

  const handleCheckIn = (v) => {
    if (!v) {
      patch({ checkIn: v });
      return;
    }
    if (!checkOut || checkOut <= v) {
      patch({ checkIn: v, checkOut: toISODateLocal(addDaysLocal(parseISODateLocal(v), 1)) });
    } else {
      patch({ checkIn: v });
    }
  };

  const handleCheckOut = (v) => {
    patch({ checkOut: v });
  };

  const handleSubmit = () => {
    if (!isValid || isLoading) return;
    onSearch?.();
  };

  const guestSummary = t('hotelDetail.rooms.filterBar.guestSummary', { adults, rooms });

  const fieldClass = 'w-full rounded-xl border border-[#e8e4dd] bg-white px-3 py-2.5 text-sm text-[#1a1a1a] focus:outline-none focus:ring-2 focus:ring-[#b8860b]/40';

  return (
    <div className="rounded-2xl border border-[#e8e4dd] bg-white p-3 sm:p-4 shadow-sm">
      <div className="flex flex-col gap-3 md:flex-row md:items-end">
        <div className="flex-1 min-w-0">
          <label htmlFor="room-filter-checkin" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-[#5c5852]">
            {t('hotelDetail.rooms.filterBar.checkIn')}
          </label>
          <DatePicker
            id="room-filter-checkin"
            value={checkIn}
            min={todayStr}
            onChange={handleCheckIn}
            className={fieldClass}
            ariaLabel={t('hotelDetail.rooms.filterBar.checkIn')}
          />
        </div>

        <div className="flex-1 min-w-0">
          <label htmlFor="room-filter-checkout" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-[#5c5852]">
            {t('hotelDetail.rooms.filterBar.checkOut')}
          </label>
          <DatePicker
            id="room-filter-checkout"
            value={checkOut}
            min={minCheckOut}
            onChange={handleCheckOut}
            className={fieldClass}
            ariaLabel={t('hotelDetail.rooms.filterBar.checkOut')}
          />
        </div>

        <div className="flex-1 min-w-0 relative" ref={guestRef}>
          <span id="room-filter-guests-label" className="mb-1 block text-xs font-semibold uppercase tracking-wide text-[#5c5852]">
            {t('hotelDetail.rooms.filterBar.guests')}
          </span>
          <button
            type="button"
            onClick={() => setGuestOpen((o) => !o)}
            aria-haspopup="dialog"
            aria-expanded={guestOpen}
            aria-labelledby="room-filter-guests-label room-filter-guests-value"
            className={cn(fieldClass, 'flex items-center gap-2 text-left')}
          >
            <Users className="w-4 h-4 shrink-0 text-[#5c5852]" aria-hidden />
            <span id="room-filter-guests-value" className="truncate">{guestSummary}</span>
          </button>
          {guestOpen && (
            <div
              role="dialog"
              aria-label={t('hotelDetail.rooms.filterBar.guests')}
              className="absolute left-0 right-0 md:right-auto md:w-72 top-full z-30 mt-2 rounded-2xl border border-[#e8e4dd] bg-white p-4 shadow-xl"
            >
              <Stepper
                label={t('hotelDetail.rooms.filterBar.adults')}
                value={adults}
                min={ADULTS_MIN}
                max={ADULTS_MAX}
                onChange={(v) => patch({ adults: v })}
                decreaseLabel={t('hotelDetail.rooms.filterBar.decreaseAdults')}
                increaseLabel={t('hotelDetail.rooms.filterBar.increaseAdults')}
              />
              <div className="border-t border-[#e8e4dd]" />
              <Stepper
                label={t('hotelDetail.rooms.filterBar.rooms')}
                value={rooms}
                min={ROOMS_MIN}
                max={ROOMS_MAX}
                onChange={(v) => patch({ rooms: v })}
                decreaseLabel={t('hotelDetail.rooms.filterBar.decreaseRooms')}
                increaseLabel={t('hotelDetail.rooms.filterBar.increaseRooms')}
              />
            </div>
          )}
        </div>

        <div className="flex gap-2 md:shrink-0">
          <button
            type="button"
            onClick={handleSubmit}
            disabled={!isValid || isLoading}
            className="flex-1 md:flex-none inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-[#b8860b] text-white text-sm font-semibold hover:bg-[#996f09] disabled:opacity-50 transition-colors"
          >
            <Search className="w-4 h-4" aria-hidden />
            {t('hotelDetail.rooms.filterBar.search')}
          </button>
          <button
            type="button"
            onClick={onFilterClick}
            className="flex-1 md:flex-none inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl border border-[#e8e4dd] bg-white text-sm font-medium text-[#1a1a1a] hover:bg-[#faf8f5] transition-colors"
          >
            <SlidersHorizontal className="w-4 h-4" aria-hidden />
            {t('hotelDetail.rooms.filterBar.filter')}
            {activeFilterCount > 0 && (
              <span className="min-w-5 h-5 px-1.5 rounded-full bg-[#b8860b] text-white text-xs inline-flex items-center justify-center">
                {activeFilterCount}
              </span>
            )}
          </button>
        </div>
      </div>
    </div>
  );
}
