import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { Loader2, Star, X } from 'lucide-react';
import { api } from '../lib/api';

export function TourReviewList({ tourUuid, providerUuid }) {
  const { t } = useTranslation();
  const [lightbox, setLightbox] = useState(null);
  const { data, isLoading, isError } = useQuery({
    queryKey: ['tour-reviews', tourUuid ?? providerUuid],
    queryFn: async () => {
      const params = { per_page: 10 };
      if (tourUuid) params.tour_uuid = tourUuid;
      if (providerUuid) params.provider_uuid = providerUuid;
      const res = await api.get('/tour-reviews', { params });
      return res.data;
    },
    enabled: !!(tourUuid || providerUuid),
  });

  if (isLoading) return <Loader2 className="w-5 h-5 animate-spin text-[#b8860b]" />;
  if (isError) return null;

  const payload = data?.data ?? {};
  const reviews = Array.isArray(payload?.data) ? payload.data : [];
  if (reviews.length === 0) {
    return <p className="text-sm text-[#7a756d]">{t('tours.reviews.empty')}</p>;
  }

  return (
    <div className="space-y-3">
      {reviews.map((r) => (
        <div key={r.id} className="rounded-xl border border-[#e8e4dd] bg-white p-4">
          <div className="flex items-center justify-between gap-2">
            <div className="flex items-center gap-1 text-[#b8860b]" aria-label={`${r.rating}/5`}>
              {[1, 2, 3, 4, 5].map((s) => (
                <Star key={s} className={`w-4 h-4 ${s <= r.rating ? 'fill-current' : 'text-stone-300'}`} />
              ))}
            </div>
            {(r.customer_name || r.tour?.title) && (
              <p className="text-xs text-[#7a756d] truncate">
                {[r.customer_name, r.tour?.title].filter(Boolean).join(' · ')}
              </p>
            )}
          </div>
          {r.comment && <p className="text-sm text-[#45423d] mt-2 whitespace-pre-wrap">{r.comment}</p>}
          {Array.isArray(r.images) && r.images.length > 0 && (
            <div className="flex flex-wrap gap-2 mt-3">
              {r.images.map((img) => (
                <button
                  key={img.id}
                  type="button"
                  onClick={() => setLightbox(img.url)}
                  className="w-20 h-20 rounded-xl overflow-hidden border border-[#e8e4dd] hover:border-[#b8860b] transition-colors p-0 min-h-0 min-w-0"
                >
                  <img src={img.url} alt="" loading="lazy" className="w-full h-full object-cover" />
                </button>
              ))}
            </div>
          )}
        </div>
      ))}
      {lightbox && (
        <div
          className="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4"
          onClick={() => setLightbox(null)}
          role="dialog"
          aria-modal="true"
        >
          <button
            type="button"
            onClick={() => setLightbox(null)}
            className="absolute top-4 right-4 w-10 h-10 rounded-full bg-white/10 text-white flex items-center justify-center hover:bg-white/20"
            aria-label={t('tours.reviews.closePhoto')}
          >
            <X className="w-5 h-5" />
          </button>
          <img
            src={lightbox}
            alt=""
            className="max-w-full max-h-[85vh] rounded-2xl object-contain"
            onClick={(e) => e.stopPropagation()}
          />
        </div>
      )}
    </div>
  );
}
