import { memo } from 'react';
import { cn } from '../../lib/utils';
import { LazyImage } from '../LazyImage';

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
      <div className={cn('w-full bg-[#e8e4dd] flex items-center justify-center', variant === 'gallery' ? 'h-64' : 'aspect-[4/3]')}>
        <svg className="w-12 h-12 text-[#7a756d]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
      </div>
    );
  }

  const renderImage = (img, index) => {
    const isFeatured = index === 0 && variant !== 'gallery';

    return (
      <div
        key={img.id || index}
        onClick={onClick}
        className={cn(
          'relative overflow-hidden',
          onClick && 'cursor-pointer',
          isFeatured && variant !== 'gallery' && 'col-span-2 row-span-2',
          variant === 'gallery' && 'aspect-[4/3]'
        )}
        style={variant === 'card' || variant === 'detailed' ? { aspectRatio: isFeatured ? '4/3' : '1/1' } : {}}
      >
        <LazyImage
          src={img.url}
          alt={img.alt_text || ''}
          eager={index === 0}
          width={isFeatured ? 800 : 400}
          height={isFeatured ? 600 : 400}
          className="w-full h-full object-cover"
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
});
