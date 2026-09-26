import { memo } from 'react';
import { getAmenityLabel } from '../../../lib/amenities';
import { AmenityIcon } from '../../AmenityIcon';
import { cn } from '../../../lib/utils';

const AMENITY_CATEGORIES = {
  room: { label: 'Phòng', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
  bathroom: { label: 'Nhà tắm', icon: 'M9 15l6-6M4 15h17m0 0v-6.818a4 4 0 00-3.172-3.818A4 4 0 014 8.182V15z' },
  entertainment: { label: 'Giải trí', icon: 'M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
  workspace: { label: 'Làm việc', icon: 'M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2' },
  accessibility: { label: 'Khả năng tiếp cận', icon: 'M15 10.5a3 3 0 11-6 0 3 3 0 016 0z M19.364 5.636l-3.536 3.536M9.879 19.879l-3.536-3.536M4.636 9.879l3.536 3.536M14.121 14.121l3.536 3.536' },
  general: { label: 'Khác', icon: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z' },
};

function categoryIcon(path) {
  return (
    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d={path} />
    </svg>
  );
}

const DEFAULT_CATEGORY_SLUGS = {
  room: ['wifi', 'air_conditioning', 'tv', 'safe', 'minibar', 'desk', 'wardrobe', 'balcony'],
  bathroom: ['bathtub', 'shower', 'hair_dryer', 'toiletries', 'towels', 'robe', 'slippers'],
  entertainment: ['tv', 'streaming', 'sound_system', 'games'],
  workspace: ['desk', 'wifi', 'office_chair', 'outlet'],
  accessibility: ['wheelchair_accessible', 'grab_bars', 'roll_in_shower', 'visual_alarms'],
};

export const AmenitiesTab = memo(function AmenitiesTab({ room }) {
  const { amenities = [] } = room;

  if (amenities.length === 0) {
    return (
      <div id="panel-amenities" role="tabpanel" aria-labelledby="tab-amenities" className="p-4">
        <div className="text-center py-12">
          <svg className="w-16 h-16 mx-auto text-[#d4cec4]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
          </svg>
          <p className="text-[#5c5852] mt-4">Chưa có thông tin tiện nghi cho phòng này</p>
        </div>
      </div>
    );
  }

  const categorizedAmenities = {};

  amenities.forEach((amenity) => {
    const slug = amenity.slug?.toLowerCase() || '';
    let category = 'general';

    for (const [cat, slugs] of Object.entries(DEFAULT_CATEGORY_SLUGS)) {
      if (slugs.some((s) => slug.includes(s))) {
        category = cat;
        break;
      }
    }

    if (!categorizedAmenities[category]) {
      categorizedAmenities[category] = [];
    }
    categorizedAmenities[category].push(amenity);
  });

  const categoriesInOrder = ['room', 'bathroom', 'entertainment', 'workspace', 'accessibility', 'general'];

  return (
    <div id="panel-amenities" role="tabpanel" aria-labelledby="tab-amenities" className="p-4 space-y-6">
      {categoriesInOrder.map((category) => {
        const items = categorizedAmenities[category];
        if (!items || items.length === 0) return null;

        const { label, icon } = AMENITY_CATEGORIES[category] || AMENITY_CATEGORIES.general;

        return (
          <section key={category} className="space-y-3">
            <h3 className="flex items-center gap-2 text-lg font-semibold text-[#1a1a1a]">
              <span className="w-10 h-10 rounded-xl bg-[#f5f2ed] flex items-center justify-center text-[#b8860b]600">
                {categoryIcon(icon)}
              </span>
              {label} ({items.length})
            </h3>
            <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
              {items.map((a) => (
                <div
                  key={a.id}
                  className={cn(
                    'flex items-center gap-2.5 px-3 py-2.5 rounded-xl border transition-colors',
                    'bg-white border-[#e8e4dd] hover:bg-[#faf8f5] hover:border-[#b8860b]200'
                  )}
                >
                  <AmenityIcon slug={a.slug} className="w-5 h-5 text-[#b8860b]600 shrink-0" />
                  <span className="text-[#45423d] text-sm">{getAmenityLabel((k) => k, a)}</span>
                </div>
              ))}
            </div>
          </section>
        );
      })}
    </div>
  );
});
