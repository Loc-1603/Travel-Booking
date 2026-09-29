import React, { useState } from 'react';

export function PriceRangeSlider({ min, max, value, onChange, step = 10000 }) {
  const [minVal, setMinVal] = useState(value?.min ?? min ?? 0);
  const [maxVal, setMaxVal] = useState(value?.max ?? max ?? 0);
  const [minInput, setMinInput] = useState('');
  const [maxInput, setMaxInput] = useState('');
  const [activeField, setActiveField] = useState(null);
  const [prevRange, setPrevRange] = useState({ min, max, value });

  if (prevRange.min !== min || prevRange.max !== max || prevRange.value !== value) {
    setPrevRange({ min, max, value });
    setMinVal(value?.min ?? min ?? 0);
    setMaxVal(value?.max ?? max ?? 0);
  }

  const range = (maxVal || 1) - (min || 0);
  const minPercent = range > 0 ? ((minVal - (min || 0)) / range) * 100 : 0;
  const maxPercent = range > 0 ? ((maxVal - (min || 0)) / range) * 100 : 100;

  const commit = (field) => {
    const num = Number(activeField === 'min' ? minInput : maxInput);
    if (Number.isNaN(num)) return;
    const clamped = Math.min(Math.max(num, min || 0), max || 1000000);
    if (field === 'min') {
      setMinVal(clamped);
      onChange?.({ min: clamped, max: maxVal });
    } else {
      setMaxVal(clamped);
      onChange?.({ min: minVal, max: clamped });
    }
    setActiveField(null);
  };

  return (
    <div className="space-y-4">
      <div className="relative h-2 bg-[#e8e4dd] rounded-full">
        <div
          className="absolute h-2 bg-[#b8860b]/30 rounded-full"
          style={{ left: `${minPercent}%`, right: `${100 - maxPercent}%` }}
        />
        {/* Visual-only dual-range track. The two labelled number inputs below are the
            keyboard-operable controls, so the hidden ranges are removed from the a11y tree
            (aria-hidden + tabIndex -1 keeps them off the tab order). */}
        <input
          type="range"
          min={min || 0}
          max={max || 1000000}
          step={step}
          value={minVal}
          aria-hidden="true"
          tabIndex={-1}
          onChange={(e) => {
            const v = Number(e.target.value);
            setMinVal(v);
            if (v > maxVal) setMaxVal(v);
            onChange?.({ min: v, max: maxVal });
          }}
          className="absolute inset-0 w-full appearance-none bg-transparent pointer-events-none"
          style={{ '--thumb': '#b8860b' }}
        />
        <input
          type="range"
          min={min || 0}
          max={max || 1000000}
          step={step}
          value={maxVal}
          aria-hidden="true"
          tabIndex={-1}
          onChange={(e) => {
            const v = Number(e.target.value);
            setMaxVal(v);
            if (v < minVal) setMinVal(v);
            onChange?.({ min: minVal, max: v });
          }}
          className="absolute inset-0 w-full appearance-none bg-transparent pointer-events-none"
        />
      </div>
      <div className="flex gap-3">
        <div>
          <label className="block text-xs text-[#5c5852] mb-1">Giá tối thiểu</label>
          <input
            type="number"
            value={activeField === 'min' ? minInput : minVal}
            onFocus={() => { setActiveField('min'); setMinInput(String(minVal)); }}
            onChange={(e) => setMinInput(e.target.value)}
            onBlur={() => commit('min')}
            onKeyDown={(e) => e.key === 'Enter' && commit('min')}
            aria-label="Giá tối thiểu"
            className="w-full rounded-xl border border-[#e8e4dd] px-3 py-2 text-sm"
            placeholder="0"
          />
        </div>
        <div>
          <label className="block text-xs text-[#5c5852] mb-1">Giá tối đa</label>
          <input
            type="number"
            value={activeField === 'max' ? maxInput : maxVal}
            onFocus={() => { setActiveField('max'); setMaxInput(String(maxVal)); }}
            onChange={(e) => setMaxInput(e.target.value)}
            onBlur={() => commit('max')}
            onKeyDown={(e) => e.key === 'Enter' && commit('max')}
            aria-label="Giá tối đa"
            className="w-full rounded-xl border border-[#e8e4dd] px-3 py-2 text-sm"
            placeholder={String(max)}
          />
        </div>
      </div>
      <p className="text-xs text-[#5c5852]">
        {minVal.toLocaleString('vi-VN')}đ — {maxVal.toLocaleString('vi-VN')}đ / đêm
      </p>
    </div>
  );
}
