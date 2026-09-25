import React, { useEffect, useMemo, useState } from 'react';
import { X, SlidersHorizontal } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { FilterSection } from './FilterSection';
import { CheckboxGroup } from './CheckboxGroup';
import { PriceRangeSlider } from './PriceRangeSlider';
import { cn } from '../../lib/utils';

export function RoomFilterPanel({
  rooms = [],
  filters,
  onFiltersChange,
  onClose,
  isMobile = false,
}) {
  const { t } = useTranslation();
  const [openMobile, setOpenMobile] = useState(false);

  // Derive options from rooms
  const roomTypes = useMemo(() => {
    const set = new Map();
    rooms.forEach((r) => {
      const rt = r.room_type;
      if (rt?.id) set.set(rt.id, { id: rt.id, label: rt.name, value: rt.id });
    });
    return Array.from(set.values()).sort((a, b) => a.label.localeCompare(b.label));
  }, [rooms]);

  const bedTypes = useMemo(() => {
    const set = new Set();
    rooms.forEach((r) => r.bed_type && set.add(r.bed_type));
    return Array.from(set).map((v) => ({ value: v, label: v }));
  }, [rooms]);

  const viewTypes = useMemo(() => {
    const set = new Set();
    rooms.forEach((r) => r.view_type && set.add(r.view_type));
    return Array.from(set).map((v) => ({ value: v, label: v }));
  }, [rooms]);

  const amenitiesList = useMemo(() => {
    const map = new Map();
    rooms.forEach((r) => {
      (r.amenities || []).forEach((a) => {
        if (!map.has(a.id)) map.set(a.id, { id: a.id, label: a.name || a.slug, value: a.slug });
      });
    });
    return Array.from(map.values()).sort((a, b) => a.label.localeCompare(b.label));
  }, [rooms]);

  const priceRange = useMemo(() => {
    const prices = rooms.map((r) => Number(r.base_price || 0)).filter((p) => p > 0);
    if (prices.length === 0) return { min: 0, max: 0 };
    return { min: Math.min(...prices), max: Math.max(...prices) };
  }, [rooms]);

  const filterCount = useMemo(() => {
    let c = 0;
    if (filters.capacity) c++;
    if (filters.price?.min || filters.price?.max) c++;
    if (filters.roomTypes?.length) c += filters.roomTypes.length;
    if (filters.bedTypes?.length) c += filters.bedTypes.length;
    if (filters.viewTypes?.length) c += filters.viewTypes.length;
    if (filters.amenities?.length) c += filters.amenities.length;
    return c;
  }, [filters]);

  useEffect(() => {
    if (isMobile) setOpenMobile(true);
  }, [isMobile]);

  const update = (key, value) => {
    onFiltersChange?.({ ...filters, [key]: value });
  };

  const clearAll = () => {
    onFiltersChange?.({
      capacity: '',
      price: { min: '', max: '' },
      roomTypes: [],
      bedTypes: [],
      viewTypes: [],
      amenities: [],
    });
  };

  const panelContent = (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h2 className="text-lg font-semibold text-[#1a1a1a] flex items-center gap-2">
          <SlidersHorizontal className="w-5 h-5" />
          Bộ lọc phòng
          {filterCount > 0 && (
            <span className="text-xs px-2 py-0.5 rounded-full bg-[#b8860b] text-white">{filterCount}</span>
          )}
        </h2>
        <button onClick={clearAll} className="text-sm text-[#b8860b]600 hover:underline">Xóa tất cả</button>
      </div>

      <FilterSection title="Sức chứa">
        <select
          value={filters.capacity || ''}
          onChange={(e) => update('capacity', e.target.value)}
          className="w-full rounded-xl border border-[#e8e4dd] px-4 py-2.5 text-sm bg-white"
        >
          <option value="">Bất kỳ</option>
          {[1,2,3,4,5,6,8,10].map(n => (
            <option key={n} value={n}>{n} khách+</option>
          ))}
        </select>
      </FilterSection>

      <FilterSection title="Giá/đêm">
        <PriceRangeSlider
          min={priceRange.min}
          max={priceRange.max}
          value={{ min: filters.price?.min || priceRange.min, max: filters.price?.max || priceRange.max }}
          onChange={(v) => update('price', v)}
        />
      </FilterSection>

      {roomTypes.length > 0 && (
        <FilterSection title="Loại phòng" count={filters.roomTypes?.length}>
          <CheckboxGroup
            options={roomTypes}
            selected={filters.roomTypes || []}
            onChange={(v) => update('roomTypes', v)}
            optionLabel={(o) => o.label}
            optionValue={(o) => o.value}
          />
        </FilterSection>
      )}

      {bedTypes.length > 0 && (
        <FilterSection title="Loại giường" count={filters.bedTypes?.length}>
          <CheckboxGroup
            options={bedTypes}
            selected={filters.bedTypes || []}
            onChange={(v) => update('bedTypes', v)}
          />
        </FilterSection>
      )}

      {viewTypes.length > 0 && (
        <FilterSection title="View" count={filters.viewTypes?.length}>
          <CheckboxGroup
            options={viewTypes}
            selected={filters.viewTypes || []}
            onChange={(v) => update('viewTypes', v)}
          />
        </FilterSection>
      )}

      {amenitiesList.length > 0 && (
        <FilterSection title="Tiện nghi" count={filters.amenities?.length}>
          <CheckboxGroup
            options={amenitiesList}
            selected={filters.amenities || []}
            onChange={(v) => update('amenities', v)}
            optionLabel={(o) => o.label}
            optionValue={(o) => o.value}
          />
        </FilterSection>
      )}
    </div>
  );

  if (isMobile) {
    return (
      <div className={cn('fixed inset-0 z-50', openMobile ? 'block' : 'hidden')}>
        <div className="absolute inset-0 bg-black/50" onClick={onClose} />
        <div className="absolute bottom-0 left-0 right-0 max-h-[85vh] bg-white rounded-t-3xl shadow-2xl p-6 overflow-y-auto">
          <div className="w-12 h-1.5 bg-[#e8e4dd] rounded-full mx-auto mb-4" />
          <div className="flex items-center justify-between mb-4">
            <h3 className="text-lg font-semibold">Bộ lọc</h3>
            <button onClick={onClose} className="p-2 rounded-full hover:bg-[#f5f2ed]">
              <X className="w-5 h-5" />
            </button>
          </div>
          {panelContent}
          <div className="sticky bottom-0 pt-4 bg-white border-t border-[#e8e4dd] mt-6">
            <button
              onClick={onClose}
              className="w-full py-3 rounded-xl bg-[#b8860b] text-white font-semibold"
            >
              Áp dụng bộ lọc
            </button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <aside className="w-[280px] shrink-0">
      <div className="rounded-2xl border border-[#e8e4dd] bg-white p-5 shadow-sm sticky top-24">
        {panelContent}
      </div>
    </aside>
  );
}
