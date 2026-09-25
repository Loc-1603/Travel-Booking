import React, { useState, useRef, useEffect, memo } from 'react';
import { cn } from '../../lib/utils';

const BLUR_PLACEHOLDER = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

export function RoomImageGallery({
  images = [],
  variant = 'card',
  featuredImage,
  remainingImages = [],
  onClick,
  className,
}) {
  const [loadedImages, setLoadedImages] = useState(new Set());
  const observerRef = useRef(null);

  const allImages = featuredImage ? [featuredImage, ...remainingImages] : images;
  const hasImages = allImages.length > 0;

  useEffect(() => {
    if (variant !== 'card' && variant !== 'detailed') return;

    observerRef.current = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            const img = entry.target;
            if (img.dataset.src && !loadedImages.has(img.dataset.src)) {
              img.src = img.dataset.src;
              setLoadedImages((prev) => new Set(prev).add(img.dataset.src));
            }
            observerRef.current?.unobserve(img);
          }
        });
      },
      { rootMargin: '100px', threshold: 0.01 }
    );

    const imgs = document.querySelectorAll('[data-lazy-img]');
    imgs.forEach((img) => observerRef.current?.observe(img));

    return () => observerRef.current?.disconnect();
  }, [variant, loadedImages]);

  const handleImageLoad = (src) => {
    setLoadedImages((prev) => new Set(prev).add(src));
  };

  if (!hasImages) {
    return (
      <div className={cn('w-full bg-[#e8e4dd] flex items-center justify-center', variant === 'gallery' ? 'h-64' : 'aspect-[4/3]')}>
        <svg className="w-12 h-12 text-[#7a756d]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
      </div>
    );
  }

  const renderImage = (img, index) => {
    const isLoaded = loadedImages.has(img?.url);
    const isFeatured = index === 0 && variant !== 'gallery';

    return (
      <div
        key={img.id || index}
        className={cn(
          'relative overflow-hidden',
          isFeatured && variant !== 'gallery' && 'col-span-2 row-span-2',
          variant === 'gallery' && 'aspect-[4/3]'
        )}
        style={variant === 'card' || variant === 'detailed' ? { aspectRatio: isFeatured ? '4/3' : '1/1' } : {}}
      >
        <img
          data-lazy-img
          data-src={img.url}
          src={isLoaded ? img.url : BLUR_PLACEHOLDER}
          alt={img.alt_text || ''}
          className={cn(
            'w-full h-full object-cover transition-opacity duration-300',
            isLoaded ? 'opacity-100' : 'opacity-0 blur-[20px]'
          )}
          onLoad={() => handleImageLoad(img.url)}
          loading={index === 0 ? 'eager' : 'lazy'}
          width={isFeatured ? 800 : 400}
          height={isFeatured ? 600 : 400}
        />
        {variant === 'card' && index === 0 && remainingImages.length > 0 && (
          <div className="absolute bottom-2 right-2 bg-black/60 text-white px-2 py-1 rounded text-xs">
            +{remainingImages.length}
          </div>
        )}
      </div>
    );
  };

  if (variant === 'gallery') {
    return (
      <div
        className={cn('relative', className)}
        onClick={onClick}
        role={onClick ? 'button' : undefined}
        tabIndex={onClick ? 0 : undefined}
        onKeyDown={(e) => e.key === 'Enter' && onClick?.()}
      >
        <img
          src={featuredImage?.url || allImages[0]?.url}
          alt={featuredImage?.alt_text || allImages[0]?.alt_text || ''}
          className="w-full h-64 object-cover"
          loading="eager"
        />
        {allImages.length > 1 && (
          <div className="absolute bottom-2 right-2 bg-black/60 text-white px-2 py-1 rounded text-xs">
            {allImages.length} ảnh
          </div>
        )}
      </div>
    );
  }

  return (
    <div
      className={cn('grid grid-cols-2 gap-1', className)}
      role="list"
      aria-label="Hình ảnh phòng"
    >
      {allImages.map((img, idx) => (
        <div key={img.id || idx} role="listitem">
          {renderImage(img, idx)}
        </div>
      ))}
    </div>
  );
}

export const MemoizedRoomImageGallery = memo(RoomImageGallery);
MemoizedRoomImageGallery.displayName = 'RoomImageGallery';