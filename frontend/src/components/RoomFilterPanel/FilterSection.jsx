import React, { useState } from 'react';
import { ChevronDown, ChevronUp } from 'lucide-react';
import { cn } from '../../lib/utils';

export function FilterSection({ title, children, defaultOpen = true, count }) {
  const [open, setOpen] = useState(defaultOpen);

  return (
    <section className="border-b border-[#e8e4dd] last:border-0 pb-4 last:pb-0">
      <button
        type="button"
        onClick={() => setOpen(!open)}
        className="w-full flex items-center justify-between py-3"
      >
        <div className="flex items-center gap-2">
          <h3 className="text-sm font-semibold uppercase tracking-wide text-[#1a1a1a]">
            {title}
          </h3>
          {typeof count === 'number' && count > 0 && (
            <span className="text-xs px-2 py-0.5 rounded-full bg-[#b8860b] text-white">
              {count}
            </span>
          )}
        </div>
        {open ? <ChevronUp className="w-4 h-4 text-[#5c5852]" /> : <ChevronDown className="w-4 h-4 text-[#5c5852]" />}
      </button>
      <div className={cn('transition-all duration-200 overflow-hidden', open ? 'max-h-[1000px] opacity-100' : 'max-h-0 opacity-0')}>
        <div className="pb-2">
          {children}
        </div>
      </div>
    </section>
  );
}
