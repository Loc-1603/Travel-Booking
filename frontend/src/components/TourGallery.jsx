import { useMemo, useState, useEffect, useRef } from 'react';
import { X, ChevronLeft, ChevronRight, Maximize2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { cn } from '../lib/utils';
import { LazyImage } from './LazyImage';

export function TourGallery({ images = [], alt, className = '' }) {
  const { t } = useTranslation();
  const resolvedAlt = alt ?? t('tours.gallery.label');
  const [lightboxIndex, setLightboxIndex] = useState(null);
  const [lightboxOpen, setLightboxOpen] = useState(false);
  const lightboxRef = useRef(null);
  const prevButtonRef = useRef(null);
  const nextButtonRef = useRef(null);

  const normalizedImages = useMemo(() => {
    return images.map((img, i) => {
      if (typeof img === 'string') {
        return { url: img, alt: `${resolvedAlt} ${i + 1}` };
      }
      return { url: img.url, alt: img.alt || img.alt_text || `${resolvedAlt} ${i + 1}` };
    });
  }, [images, resolvedAlt]);

  const handleOpen = (index) => {
    setLightboxIndex(index);
    setLightboxOpen(true);
  };

  const handleClose = () => {
    setLightboxOpen(false);
    setLightboxIndex(null);
  };

  const handlePrev = () => {
    if (lightboxIndex > 0) setLightboxIndex(lightboxIndex - 1);
  };

  const handleNext = () => {
    if (lightboxIndex < normalizedImages.length - 1) setLightboxIndex(lightboxIndex + 1);
  };

  useEffect(() => {
    if (!lightboxOpen) return undefined;
    const onKeyDown = (event) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        setLightboxOpen(false);
        setLightboxIndex(null);
      } else if (event.key === 'ArrowLeft') {
        event.preventDefault();
        setLightboxIndex((i) => (i > 0 ? i - 1 : i));
      } else if (event.key === 'ArrowRight') {
        event.preventDefault();
        setLightboxIndex((i) => (i < normalizedImages.length - 1 ? i + 1 : i));
      }
    };
    document.addEventListener('keydown', onKeyDown);
    return () => document.removeEventListener('keydown', onKeyDown);
  }, [lightboxOpen, normalizedImages.length]);

  const handleBackdropClick = (event) => {
    if (event.target === lightboxRef.current) {
      handleClose();
    }
  };

  if (normalizedImages.length === 0) {
    return (
      <div
        className={cn('flex h-48 items-center justify-center rounded-2xl bg-neutral-100 text-neutral-500', className)}
        role="img"
        aria-label={t('tours.gallery.empty')}
      >
        {t('tours.gallery.empty')}
      </div>
    );
  }

  const mainImage = normalizedImages[lightboxIndex ?? 0];

  return (
    <div className={cn('space-y-4', className)} aria-label={t('tours.gallery.label')}>
      <div className="relative">
        <LazyImage
          src={mainImage.url}
          alt={mainImage.alt}
          className="rounded-2xl object-cover w-full"
          width={800}
          height={600}
        />
        {lightboxOpen ? (
          <button
            ref={nextButtonRef}
            type="button"
            onClick={handleNext}
            disabled={lightboxIndex >= normalizedImages.length - 1}
            aria-label={t('tours.gallery.next')}
            className={cn(
              'absolute right-2 top-1/2 -translate-y-1/2 rounded-full p-2 text-white transition-colors focus:outline-none focus:ring-2 focus:ring-white',
              lightboxIndex >= normalizedImages.length - 1 && 'opacity-40'
            )}
          >
            <ChevronRight className="h-4 w-4" />
          </button>
        ) : (
          <button
            type="button"
            onClick={() => handleOpen(0)}
            aria-label={t('tours.gallery.open')}
            className={cn(
              'absolute bottom-4 left-4 rounded-full bg-black/60 p-3 text-white transition-colors hover:bg-black/80 focus:outline-none focus:ring-2 focus:ring-white'
            )}
          >
            <Maximize2 className="h-5 w-5" />
          </button>
        )}
      </div>

      <div className="grid grid-cols-4 gap-2" role="tablist" aria-label={t('tours.gallery.thumbnails')}>
        {normalizedImages.map((img, idx) => (
          <button
            key={idx}
            type="button"
            onClick={() => handleOpen(idx)}
            aria-label={t('tours.gallery.viewPhoto', { index: idx + 1 })}
            aria-selected={lightboxIndex === idx}
            className={cn(
              'relative aspect-square overflow-hidden rounded-lg border-2 transition-all',
              lightboxIndex === idx ? 'border-accent' : 'border-neutral-200 hover:border-neutral-300'
            )}
          >
            <LazyImage
              src={img.url}
              alt={img.alt}
              className="h-full w-full object-cover"
            />
          </button>
        ))}
      </div>

      {lightboxOpen && (
        <div
          ref={lightboxRef}
          onClick={handleBackdropClick}
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
          role="dialog"
          aria-modal="true"
          aria-label={t('tours.gallery.photoOf', { index: lightboxIndex + 1, count: normalizedImages.length })}
        >
          <div className="relative max-w-3xl w-full">
            <header className="absolute top-4 right-4">
              <button
                type="button"
                onClick={handleClose}
                aria-label={t('tours.gallery.close')}
                className="rounded-full p-2 text-white transition-colors hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white"
              >
                <X className="h-6 w-6" />
              </button>
            </header>
            <LazyImage
              src={mainImage?.url}
              alt={mainImage?.alt}
              className="rounded-2xl max-w-full max-h-[85vh] object-contain"
            />
            {normalizedImages.length > 1 && (
              <div className="absolute bottom-4 left-0 right-0 flex justify-center gap-2">
                <button
                  ref={prevButtonRef}
                  type="button"
                  onClick={handlePrev}
                  disabled={lightboxIndex <= 0}
                  aria-label={t('tours.gallery.prev')}
                  className={cn(
                    'rounded-full bg-black/60 p-2 text-white transition-colors hover:bg-black/80 disabled:opacity-40 focus:outline-none focus:ring-2 focus:ring-white'
                  )}
                >
                  <ChevronLeft className="h-4 w-4" />
                </button>
                <button
                  ref={nextButtonRef}
                  type="button"
                  onClick={handleNext}
                  disabled={lightboxIndex >= normalizedImages.length - 1}
                  aria-label={t('tours.gallery.next')}
                  className={cn(
                    'rounded-full bg-black/60 p-2 text-white transition-colors hover:bg-black/80 disabled:opacity-40 focus:outline-none focus:ring-2 focus:ring-white'
                  )}
                >
                  <ChevronRight className="h-4 w-4" />
                </button>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}

export default TourGallery;