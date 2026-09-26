import { memo } from 'react';
import { cn } from '../../lib/utils';

const TABS = [
  { key: 'overview', label: 'Tổng quan', icon: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
  { key: 'gallery', label: 'Hình ảnh', icon: 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z' },
  { key: 'amenities', label: 'Tiện nghi', icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z' },
  { key: 'policies', label: 'Chính sách', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
];

function TabIcon({ path }) {
  return (
    <svg className="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d={path} />
    </svg>
  );
}

export const RoomDetailTabs = memo(function RoomDetailTabs({ activeTab, onTabChange, className }) {
  return (
    <nav
      className={cn('flex gap-1 overflow-x-auto pb-2 px-4 bg-white/95 backdrop-blur-sm border-b border-[#e8e4dd] sticky top-14 z-10', className)}
      aria-label="Tabs chi tiết phòng"
      role="tablist"
    >
      {TABS.map((tab) => (
        <button
          key={tab.key}
          onClick={() => onTabChange(tab.key)}
          className={cn(
            'flex items-center gap-2 px-4 py-2.5 rounded-xl font-medium text-sm whitespace-nowrap transition-all duration-200',
            activeTab === tab.key
              ? 'bg-[#b8860b] text-white shadow-sm'
              : 'text-[#5c5852] hover:bg-[#f5f2ed] hover:text-[#1a1a1a]'
          )}
          role="tab"
          aria-selected={activeTab === tab.key}
          aria-controls={`panel-${tab.key}`}
          id={`tab-${tab.key}`}
        >
          <TabIcon path={tab.icon} aria-hidden="true" />
          {tab.label}
        </button>
      ))}
    </nav>
  );
});
