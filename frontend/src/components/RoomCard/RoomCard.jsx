import { memo, useMemo } from 'react';
import { cn } from '../../lib/utils';
import { RoomImageGallery } from './RoomImageGallery';
import { RoomPriceBadge } from './RoomPriceBadge';
import { AmenityIcon } from '../AmenityIcon';
import { getAmenityLabel } from '../../lib/amenities';

const VARIANT_CLASSES = {
  card: 'rounded-2xl overflow-hidden border border-[#e8e4dd]/80 bg-white shadow-sm hover:shadow-xl hover:border-[#b8860b]200/60 transition-all duration-300',
  detailed: 'rounded-2xl overflow-hidden border border-[#e8e4dd]/80 bg-white shadow-sm hover:shadow-xl hover:border-[#b8860b]200/60 transition-all duration-300',
  gallery: 'rounded-xl overflow-hidden bg-[#e8e4dd] cursor-pointer',
};

const CAPACITY_ICONS = {
  1: 'single',
  2: 'double',
  3: 'triple',
  4: 'quad',
};

export const RoomCard = memo(function RoomCard({
  room,
  variant = 'card',
  nights,
  onViewDetails,
  onSelect,
  selected = false,
  className,
  ...props
}) {
  const {
    name,
    description,
    size,
    bed_type,
    view_type,
    capacity,
    base_price,
    images = [],
    amenities = [],
    room_type,
    cancellation_policy_summary,
  } = room;

  const price = base_price != null ? Number(base_price) : null;
  const featuredImage = images[0];
  const remainingImages = useMemo(() => images.slice(1), [images]);

  const variantClass = VARIANT_CLASSES[variant] || VARIANT_CLASSES.card;

  if (variant === 'gallery') {
    return (
      <article
        className={cn(variantClass, selected && 'ring-2 ring-[#b8860b] ring-offset-2', className)}
        onClick={() => onSelect?.(room)}
        role="button"
        tabIndex={0}
        onKeyDown={(e) => e.key === 'Enter' && onSelect?.(room)}
        {...props}
      >
        <RoomImageGallery
          images={images}
          variant="gallery"
          featuredImage={featuredImage}
          remainingImages={remainingImages}
        />
        <div className="absolute bottom-0 left-0 right-0 p-4 bg-gradient-to-t from-black/70 to-transparent">
          <h3 className="text-white font-semibold text-lg truncate">{name}</h3>
          <div className="flex items-center gap-3 mt-2 text-white/90 text-sm">
            <span className="flex items-center gap-1">
              <span className="w-4 h-4 rounded-full bg-white/30 flex items-center justify-center" />
              {capacity} {capacity === 1 ? 'khách' : 'khách'}
            </span>
            {price != null && (
              <span className="font-medium">{price.toLocaleString('vi-VN')}đ/đêm</span>
            )}
          </div>
        </div>
        {selected && (
          <div className="absolute inset-0 bg-black/20 flex items-center justify-center">
            <svg className="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
            </svg>
          </div>
        )}
      </article>
    );
  }

  return (
    <article
      className={cn(variantClass, className)}
      {...props}
    >
      <div className="relative">
        <RoomImageGallery
          images={images}
          variant={variant}
          featuredImage={featuredImage}
          remainingImages={remainingImages}
          onClick={onViewDetails}
        />
        {variant === 'detailed' && (
          <div className="absolute top-3 right-3 flex gap-1">
            {room_type && (
              <span className="px-2 py-1 text-xs font-medium rounded-full bg-white/90 backdrop-blur text-[#1a1a1a]">
                {room_type.name}
              </span>
            )}
          </div>
        )}
      </div>

      <div className="p-4 sm:p-5 flex flex-col gap-3">
        <div className="flex items-start justify-between gap-3">
          <div className="flex-1 min-w-0">
            <h3 className="font-semibold text-[#1a1a1a] text-lg truncate">{name}</h3>
            {room_type && (
              <p className="text-sm text-[#5c5852] mt-0.5">{room_type.name}</p>
            )}
          </div>
          <RoomPriceBadge
            price={price}
            nights={nights}
            variant={variant === 'detailed' ? 'detailed' : 'compact'}
          />
        </div>

        <div className="flex flex-wrap items-center gap-3 text-sm text-[#5c5852]">
          <span className="flex items-center gap-1">
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            {capacity} {capacity === 1 ? 'khách' : 'khách'}
          </span>
          {size && (
            <span className="flex items-center gap-1">
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
              </svg>
              {size} m²
            </span>
          )}
          {bed_type && (
            <span className="flex items-center gap-1">
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
              </svg>
              {bed_type}
            </span>
          )}
          {view_type && (
            <span className="flex items-center gap-1">
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
              {view_type}
            </span>
          )}
        </div>

        {variant === 'detailed' && description && (
          <p className="text-[#45423d] text-sm line-clamp-3">{description}</p>
        )}

        {variant === 'detailed' && amenities.length > 0 && (
          <div className="flex flex-wrap gap-2">
            {amenities.slice(0, 4).map((a) => (
              <span key={a.id} className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-[#f5f2ed] text-[#45423d] text-xs">
                <AmenityIcon slug={a.slug} className="w-3.5 h-3.5 text-[#b8860b]600" />
                {getAmenityLabel((k) => k, a)}
              </span>
            ))}
            {amenities.length > 4 && (
              <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-[#e8e4dd] text-[#5c5852] text-xs">
                +{amenities.length - 4} tiện nghi khác
              </span>
            )}
          </div>
        )}

        {variant === 'detailed' && cancellation_policy_summary && (
          <p className="text-sm text-[#5c5852] line-clamp-2">{cancellation_policy_summary}</p>
        )}

        {onSelect && (
          <button
            type="button"
            onClick={() => onSelect(room)}
            className={cn(
              'w-full py-2.5 rounded-xl font-semibold text-sm transition-colors',
              selected
                ? 'bg-[#b8860b] text-white hover:bg-[#996f09]'
                : 'bg-[#f5f2ed] text-[#1a1a1a] hover:bg-[#e8e4dd] border border-[#e8e4dd]'
            )}
            disabled={selected}
          >
            {selected ? 'Đã chọn' : 'Chọn phòng này'}
          </button>
        )}
      </div>
    </article>
  );
});
