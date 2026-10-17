import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { Loader2, Star } from 'lucide-react';
import { api } from '../lib/api';
import { Modal } from './Modal';

/**
 * Danh sách đánh giá dùng chung (tour_uuid cho tour, provider_uuid cho guide).
 * Backend hợp nhất: GET /reviews.
 */
export function ReviewList({ tourUuid, providerUuid }) {
  const { t } = useTranslation();
  const [lightbox, setLightbox] = useState(null);
  const { data, isLoading, isError } = useQuery({
    queryKey: ['reviews', tourUuid ?? providerUuid],
    queryFn: async () => {
      const params = { per_page: 10 };
      if (tourUuid) params.tour_uuid = tourUuid;
      if (providerUuid) params.provider_uuid = providerUuid;
      const res = await api.get('/reviews', { params });
      return res.data;
    },
    enabled: !!(tourUuid || providerUuid),
  });

  if (isLoading) return <Loader2 className="w-5 h-5 animate-spin text-accent" />;
  if (isError) return null;

  const payload = data?.data ?? {};
  const reviews = Array.isArray(payload?.data) ? payload.data : [];
  if (reviews.length === 0) {
    return <p className="text-sm text-neutral-500">{t('tours.reviews.empty')}</p>;
  }

  return (
    <div className="space-y-3">
      {reviews.map((r) => (
        <div key={r.id} className="rounded-xl border border-neutral-200 bg-white p-4">
          <div className="flex items-center justify-between gap-2">
            <div className="flex items-center gap-1 text-accent" aria-label={`${r.rating}/5`}>
              {[1, 2, 3, 4, 5].map((s) => (
                <Star key={s} className={`w-4 h-4 ${s <= r.rating ? 'fill-current' : 'text-stone-300'}`} />
              ))}
            </div>
            {(r.customer_name || r.tour?.title) && (
              <p className="text-xs text-neutral-500 truncate">
                {[r.customer_name, r.tour?.title].filter(Boolean).join(' · ')}
              </p>
            )}
          </div>
          {r.comment && <p className="text-sm text-neutral-700 mt-2 whitespace-pre-wrap">{r.comment}</p>}
          {Array.isArray(r.images) && r.images.length > 0 && (
            <div className="flex flex-wrap gap-2 mt-3">
              {r.images.map((img, idx) => (
                <button
                  key={img.id}
                  type="button"
                  onClick={() => setLightbox({ url: img.url, index: idx })}
                  aria-label={t('tours.reviews.photoAlt', { index: idx + 1 })}
                  className="w-20 h-20 rounded-xl overflow-hidden border border-neutral-200 hover:border-accent transition-colors p-0 min-h-0 min-w-0"
                >
                  <img src={img.url} alt={t('tours.reviews.photoAlt', { index: idx + 1 })} loading="lazy" className="w-full h-full object-cover" />
                </button>
              ))}
            </div>
          )}
        </div>
      ))}
      <Modal
        open={!!lightbox}
        onClose={() => setLightbox(null)}
        title={t('tours.reviews.photoTitle')}
        size="lg"
      >
        {lightbox && (
          <img
            src={lightbox.url}
            alt={t('tours.reviews.photoAlt', { index: lightbox.index + 1 })}
            className="w-full max-h-[70vh] rounded-2xl object-contain"
          />
        )}
      </Modal>
    </div>
  );
}
