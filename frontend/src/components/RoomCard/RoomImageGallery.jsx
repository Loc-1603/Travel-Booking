import { memo, useCallback, useState } from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { cn } from '../../lib/utils';
import { LazyImage } from '../LazyImage';

/**
 * Single-image carousel for the room list (FX-5).
 * Exactly ONE photo is visible at a time inside a fixed aspect-[4/3] frame,
 * so the card height never depends on how many photos the room has.
 * Prev/next wraps around: first ←→ last.
 */
const RoomSingleCarousel = memo(function RoomSingleCarousel({ images, onClick, className }) {
  const { t } = useTranslation();
  const count = images.length;
  const [index, setIndex] = useState(0);
  // Hướng trượt: 1 = ảnh tiếp theo (trượt từ phải sang), -1 = ảnh trước
  // (trượt từ trái sang), 0 = lần đầu / đổi phòng (chỉ fade nhẹ).
  const [direction, setDirection] = useState(0);

  // New room (or new photo set) → back to the first photo.
  // Render-phase reset (not an effect) per the React docs pattern.
  const galleryKey = `${count}:${images[0]?.id ?? images[0]?.url ?? ''}`;
  const [prevKey, setPrevKey] = useState(galleryKey);
  if (prevKey !== galleryKey) {
    setPrevKey(galleryKey);
    setIndex(0);
    setDirection(0);
  }

  const go = useCallback(
    (delta) => {
      setDirection(delta > 0 ? 1 : -1);
      setIndex((i) => (i + delta + count) % count);
    },
    [count]
  );
  const goPrev = useCallback((e) => {
    e.stopPropagation();
    go(-1);
  }, [go]);
  const goNext = useCallback((e) => {
    e.stopPropagation();
    go(1);
  }, [go]);

  const onKeyDown = useCallback(
    (e) => {
      if (e.key === 'ArrowLeft') {
        e.preventDefault();
        go(-1);
      } else if (e.key === 'ArrowRight') {
        e.preventDefault();
        go(1);
      } else if ((e.key === 'Enter' || e.key === ' ') && e.target === e.currentTarget) {
        e.preventDefault();
        onClick?.();
      }
    },
    [go, onClick]
  );

  const dotGo = useCallback(
    (idx) => {
      setDirection(idx > index ? 1 : idx < index ? -1 : 0);
      setIndex(idx);
    },
    [index]
  );

  const current = images[index] ?? images[0];
  // key={index} remounts the photo wrapper on every change so the
  // slide/fade animation replays for each transition.
  const slideClass =
    direction > 0 ? 'animate-gallery-next' : direction < 0 ? 'animate-gallery-prev' : 'animate-gallery-fade';

  return (
    <div
      role="group"
      aria-roledescription="carousel"
      aria-label={t('hotelDetail.gallery.carouselLabel', { count })}
      className={cn('relative aspect-[4/3] w-full overflow-hidden bg-[#e8e4dd] max-h-[480px]', className)}
      onKeyDown={count > 1 ? onKeyDown : undefined}
    >
      <div
        role={onClick ? 'button' : undefined}
        tabIndex={onClick ? 0 : undefined}
        aria-label={current?.alt_text || undefined}
        onClick={onClick}
        onKeyDown={
          onClick
            ? (e) => {
                if ((e.key === 'Enter' || e.key === ' ') && count <= 1) {
                  e.preventDefault();
                  onClick();
                }
              }
            : undefined
        }
        className={cn('h-full w-full', onClick && 'cursor-pointer')}
      >
        <div key={index} className={cn('h-full w-full', slideClass)}>
          <LazyImage
            src={current?.url}
            alt={current?.alt_text || ''}
            eager
            width={800}
            height={600}
            className="h-full w-full object-cover"
          />
        </div>
      </div>
      {count > 1 && (
        <>
          <button
            type="button"
            onClick={goPrev}
            aria-label={t('hotelDetail.gallery.prev')}
            className="absolute left-2 top-1/2 -translate-y-1/2 rounded-full bg-white/90 p-1.5 shadow transition-colors hover:bg-white"
          >
            <ChevronLeft className="h-4 w-4 text-[#1a1a1a]" aria-hidden />
          </button>
          <button
            type="button"
            onClick={goNext}
            aria-label={t('hotelDetail.gallery.next')}
            className="absolute right-2 top-1/2 -translate-y-1/2 rounded-full bg-white/90 p-1.5 shadow transition-colors hover:bg-white"
          >
            <ChevronRight className="h-4 w-4 text-[#1a1a1a]" aria-hidden />
          </button>
          <div className="absolute bottom-2 right-2 rounded bg-black/60 px-2 py-1 text-xs text-white" aria-live="polite">
            {index + 1} / {count}
          </div>
          <div className="absolute bottom-2 left-1/2 flex -translate-x-1/2 items-center gap-1.5">
            {images.map((img, idx) => (
              <button
                key={img.id ?? idx}
                type="button"
                onClick={(e) => {
                  e.stopPropagation();
                  dotGo(idx);
                }}
                aria-label={t('hotelDetail.gallery.imageAriaLabel', { index: idx + 1 })}
                aria-current={idx === index}
                // min-h-0/min-w-0/p-0: override the global 44px touch-target rule
                // so the dots stay tiny (6px) on the small room photo.
                className={cn(
                  'h-1.5 w-1.5 min-h-0 min-w-0 shrink-0 rounded-full p-0 shadow ring-1 ring-black/20 transition-colors duration-200',
                  idx === index ? 'scale-110 bg-blue-600' : 'bg-white hover:bg-white'
                )}
              />
            ))}
          </div>
        </>
      )}
    </div>
  );
});

export const RoomImageGallery = memo(function RoomImageGallery({
  images = [],
  variant = 'card',
  featuredImage,
  remainingImages = [],
  onClick,
  className,
}) {
  const allImages = featuredImage ? [featuredImage, ...remainingImages] : images;
  const hasImages = allImages.length > 0;

  if (!hasImages) {
    return (
      <div className={cn('w-full bg-[#e8e4dd] flex items-center justify-center aspect-[4/3] max-h-[480px]', variant === 'gallery' ? 'h-64' : 'aspect-[4/3]')}>
        <svg className="w-12 h-12 text-[#7a756d]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
      </div>
    );
  }

  if (variant === 'gallery') {
    return (
      <div
        className={cn('relative max-h-[480px] overflow-hidden', className)}
        onClick={onClick}
        role={onClick ? 'button' : undefined}
        tabIndex={onClick ? 0 : undefined}
        onKeyDown={(e) => e.key === 'Enter' && onClick?.()}
      >
        <LazyImage
          src={featuredImage?.url || allImages[0]?.url}
          alt={featuredImage?.alt_text || allImages[0]?.alt_text || ''}
          eager
          width={800}
          height={256}
          className="w-full h-64 object-cover"
        />
        {allImages.length > 1 && (
          <div className="absolute bottom-2 right-2 bg-black/60 text-white px-2 py-1 rounded text-xs">
            {allImages.length} ảnh
          </div>
        )}
      </div>
    );
  }

  // card / detailed: single-photo carousel with circular prev/next (FX-5).
  // Card height is fixed by aspect-[4/3] regardless of photo count.
  return <RoomSingleCarousel images={allImages} onClick={onClick} className={className} />;
});
