import { useCallback, useEffect, useRef, useState } from 'react';

// 1x1 transparent GIF — shared blur placeholder (previously duplicated in RoomImageGallery + GalleryTab)
export const BLUR_PLACEHOLDER = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

/**
 * Per-image lazy loading: IntersectionObserver (rootMargin configurable) gates when the
 * real `src` is set; `isLoaded` flips only via onLoad so the blur placeholder stays
 * until the image has actually decoded.
 *
 * Must be used inside a single <img> element (via LazyImage), not in a .map loop.
 */
export function useLazyImage({ rootMargin = '100px', enabled = true } = {}) {
  // jsdom / very old browsers have no IntersectionObserver: load eagerly from the start
  const [isVisible, setIsVisible] = useState(() => typeof IntersectionObserver === 'undefined');
  const [isLoaded, setIsLoaded] = useState(false);
  const ref = useRef(null);

  useEffect(() => {
    if (!enabled || isVisible) return;
    const node = ref.current;
    if (!node) return undefined;

    const observer = new IntersectionObserver(
      (entries) => {
        if (entries.some((entry) => entry.isIntersecting)) {
          setIsVisible(true);
        }
      },
      { rootMargin, threshold: 0.01 }
    );
    observer.observe(node);
    return () => observer.disconnect();
  }, [enabled, isVisible, rootMargin]);

  const handleLoad = useCallback(() => setIsLoaded(true), []);

  return {
    ref,
    isVisible,
    isLoaded,
    handleLoad,
    // When lazy loading is disabled (eager), load right away
    shouldLoad: isVisible || !enabled,
  };
}
