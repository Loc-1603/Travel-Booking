import { memo, useState, useCallback, useEffect, useRef } from 'react';
import { cn } from '../../../lib/utils';
import { LazyImage } from '../../LazyImage';
import { X, ChevronLeft, ChevronRight, Expand } from 'lucide-react';

const SWIPE_THRESHOLD = 50;

export const GalleryTab = memo(function GalleryTab({ room }) {
  const { images = [] } = room;
  const [selectedIndex, setSelectedIndex] = useState(0);
  const [isLightboxOpen, setIsLightboxOpen] = useState(false);

  const touchStartX = useRef(0);
  const touchStartY = useRef(0);
  const isTrackingTouch = useRef(false);

  const openLightbox = useCallback((index) => {
    setSelectedIndex(index);
    setIsLightboxOpen(true);
  }, []);

  const closeLightbox = useCallback(() => {
    setIsLightboxOpen(false);
  }, []);

  const navigate = useCallback((delta) => {
    setSelectedIndex((prev) => (prev + delta + images.length) % images.length);
  }, [images.length]);

  const handleLightboxTouchStart = useCallback((e) => {
    touchStartX.current = e.touches[0].clientX;
    touchStartY.current = e.touches[0].clientY;
    isTrackingTouch.current = true;
  }, []);

  const handleLightboxTouchEnd = useCallback((e) => {
    if (!isTrackingTouch.current) return;
    isTrackingTouch.current = false;
    const deltaX = e.changedTouches[0].clientX - touchStartX.current;
    const deltaY = e.changedTouches[0].clientY - touchStartY.current;
    // Horizontal swipe only: must dominate the vertical movement (scroll/none)
    if (Math.abs(deltaX) > SWIPE_THRESHOLD && Math.abs(deltaX) > Math.abs(deltaY)) {
      navigate(deltaX < 0 ? 1 : -1);
    }
  }, [navigate]);

  useEffect(() => {
    if (!isLightboxOpen) return;

    const handleKeyDown = (e) => {
      if (e.key === 'Escape') closeLightbox();
      if (e.key === 'ArrowLeft') navigate(-1);
      if (e.key === 'ArrowRight') navigate(1);
    };

    document.addEventListener('keydown', handleKeyDown);
    document.body.style.overflow = 'hidden';

    return () => {
      document.removeEventListener('keydown', handleKeyDown);
      document.body.style.overflow = '';
    };
  }, [isLightboxOpen, closeLightbox, navigate]);

  const allImages = images.length > 0 ? images : [{ url: room.banner_image || room.bannerImage }].filter(Boolean);

  if (allImages.length === 0) {
    return (
      <div id="panel-gallery" role="tabpanel" aria-labelledby="tab-gallery" className="p-4">
        <div className="aspect-[4/3] rounded-2xl bg-[#e8e4dd] flex items-center justify-center">
          <svg className="w-16 h-16 text-[#7a756d]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
        </div>
        <p className="text-center text-[#5c5852] mt-4">Chưa có hình ảnh cho phòng này</p>
      </div>
    );
  }

  return (
    <>
      <div
        id="panel-gallery"
        role="tabpanel"
        aria-labelledby="tab-gallery"
        className="p-4"
      >
        <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
          {allImages.map((img, idx) => (
            <button
              key={img.id || idx}
              type="button"
              onClick={() => openLightbox(idx)}
              className={cn(
                'relative aspect-[4/3] rounded-xl overflow-hidden bg-[#e8e4dd] transition-all duration-200 hover:shadow-lg hover:scale-[1.02]',
                selectedIndex === idx && 'ring-2 ring-[#b8860b] ring-offset-2'
              )}
              aria-label={`Xem hình ảnh ${idx + 1}`}
              aria-current={selectedIndex === idx ? 'true' : undefined}
            >
              <LazyImage
                src={img.url}
                alt={img.alt_text || `Hình ảnh phòng ${idx + 1}`}
                eager={idx < 4}
                width={800}
                height={600}
                className="w-full h-full object-cover"
              />
              {allImages.length > 1 && idx === 0 && (
                <div className="absolute inset-0 flex items-center justify-center bg-black/30">
                  <Expand className="w-8 h-8 text-white/90" />
                </div>
              )}
            </button>
          ))}
        </div>

        {allImages.length > 4 && (
          <button
            type="button"
            onClick={() => openLightbox(0)}
            className="w-full mt-4 py-3 rounded-xl border border-[#e8e4dd] text-[#5c5852] font-medium hover:bg-[#f5f2ed] transition-colors"
          >
            Xem tất cả {allImages.length} ảnh
          </button>
        )}
      </div>

      {isLightboxOpen && allImages.length > 0 && (
        <div
          className="fixed inset-0 bg-black/95 z-50 flex items-center justify-center p-4"
          onClick={closeLightbox}
          onTouchStart={handleLightboxTouchStart}
          onTouchEnd={handleLightboxTouchEnd}
          role="dialog"
          aria-modal="true"
          aria-label="Phóng to hình ảnh"
        >
          <button
            onClick={closeLightbox}
            className="absolute top-4 right-4 p-2 text-white hover:bg-white/10 rounded-full z-10"
            aria-label="Đóng"
          >
            <X className="w-6 h-6" />
          </button>

          <div className="relative max-w-6xl max-h-[90vh] w-full" onClick={(e) => e.stopPropagation()}>
            <img
              src={allImages[selectedIndex]?.url}
              alt={allImages[selectedIndex]?.alt_text || `Hình ảnh ${selectedIndex + 1}`}
              className="max-w-full max-h-[80vh] object-contain"
            />

            {allImages.length > 1 && (
              <>
                <button
                  onClick={() => navigate(-1)}
                  className="absolute left-0 top-1/2 -translate-y-1/2 -translate-x-12 sm:-translate-x-16 p-3 rounded-full bg-white/10 hover:bg-white/20 text-white transition-colors"
                  aria-label="Ảnh trước"
                >
                  <ChevronLeft className="w-8 h-8" />
                </button>
                <button
                  onClick={() => navigate(1)}
                  className="absolute right-0 top-1/2 -translate-y-1/2 translate-x-12 sm:translate-x-16 p-3 rounded-full bg-white/10 hover:bg-white/20 text-white transition-colors"
                  aria-label="Ảnh sau"
                >
                  <ChevronRight className="w-8 h-8" />
                </button>

                <div className="flex justify-center gap-2 mt-6">
                  {allImages.map((_, idx) => (
                    <button
                      key={idx}
                      onClick={() => setSelectedIndex(idx)}
                      className={cn(
                        'w-2.5 h-2.5 rounded-full transition-all duration-200',
                        selectedIndex === idx ? 'bg-white scale-125' : 'bg-white/50 hover:bg-white/75'
                      )}
                      aria-label={`Ảnh ${idx + 1}`}
                      aria-current={selectedIndex === idx ? 'true' : undefined}
                    />
                  ))}
                </div>
              </>
            )}
          </div>
        </div>
      )}
    </>
  );
});