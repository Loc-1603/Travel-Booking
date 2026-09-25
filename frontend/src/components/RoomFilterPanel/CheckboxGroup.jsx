import React from 'react';
import { cn } from '../../lib/utils';

export function CheckboxGroup({
  options = [],
  selected = [],
  onChange,
  optionLabel,
  optionValue,
  limitHeight = true,
}) {
  const toggle = (value) => {
    const exists = selected.includes(value);
    const next = exists ? selected.filter((v) => v !== value) : [...selected, value];
    onChange?.(next);
  };

  return (
    <div className={cn('space-y-2', limitHeight && 'max-h-64 overflow-y-auto pr-2')}>
      {options.map((opt) => {
        const value = optionValue ? optionValue(opt) : opt.value;
        const label = optionLabel ? optionLabel(opt) : opt.label;
        const checked = selected.includes(value);
        return (
          <label key={value} className="flex items-center gap-2 cursor-pointer group">
            <input
              type="checkbox"
              checked={checked}
              onChange={() => toggle(value)}
              className="rounded border-[#e8e4dd] text-[#b8860b] focus:ring-[#b8860b]/30"
            />
            <span className="text-sm text-[#45423d] group-hover:text-[#1a1a1a]">{label}</span>
          </label>
        );
      })}
      {options.length === 0 && (
        <p className="text-sm text-[#7a756d]">Không có tùy chọn</p>
      )}
    </div>
  );
}
