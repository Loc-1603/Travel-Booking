import { memo } from 'react';
import { useLazyImage, BLUR_PLACEHOLDER } from '../hooks/useLazyImage';
import { cn } from '../lib/utils';

/**
 * Shared lazy <img> with blur placeholder (1x1 base64) until decoded.
 * Keeps native `loading="lazy"` as a safety net alongside the IntersectionObserver gate.
 * `srcSet`/`sizes` passthrough for future backend image variants.
 */
export const LazyImage = memo(function LazyImage({
  src,
  alt = '',
  className,
  eager = false,
  width,
  height,
  srcSet,
  sizes,
  fetchPriority,
  ...props
}) {
  const { ref, shouldLoad, isLoaded, handleLoad } = useLazyImage({ enabled: !eager });

  return (
    <img
      ref={ref}
      src={shouldLoad ? src : BLUR_PLACEHOLDER}
      srcSet={shouldLoad ? srcSet : undefined}
      sizes={sizes}
      alt={alt}
      width={width}
      height={height}
      loading={eager ? 'eager' : 'lazy'}
      decoding="async"
      fetchPriority={fetchPriority}
      onLoad={handleLoad}
      className={cn(
        'transition-opacity duration-300',
        isLoaded ? 'opacity-100' : 'opacity-0 blur-[20px]',
        className
      )}
      {...props}
    />
  );
});
