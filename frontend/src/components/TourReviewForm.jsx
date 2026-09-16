import { useRef, useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { ImagePlus, Star, X } from 'lucide-react';
import { api } from '../lib/api';

const MAX_IMAGES = 5;

export function TourReviewForm({ tourBookingId, onSubmitted }) {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const fileRef = useRef(null);
  const [rating, setRating] = useState(5);
  const [comment, setComment] = useState('');
  const [files, setFiles] = useState([]);
  const [error, setError] = useState(null);

  const previews = files.map((f) => ({ file: f, url: URL.createObjectURL(f) }));

  const mutation = useMutation({
    mutationFn: (formData) => api.post('/tour-reviews', formData),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['tour-reviews'] });
      queryClient.invalidateQueries({ queryKey: ['tour-bookings-mine'] });
      queryClient.invalidateQueries({ queryKey: ['tour-bookings'] });
      previews.forEach((p) => URL.revokeObjectURL(p.url));
      setComment('');
      setFiles([]);
      setError(null);
      onSubmitted?.();
    },
    onError: (err) => setError(err?.response?.data?.message || err?.message),
  });

  const pickFiles = (e) => {
    const chosen = Array.from(e.target.files ?? []).filter((f) => f.type.startsWith('image/'));
    setFiles((prev) => [...prev, ...chosen].slice(0, MAX_IMAGES));
    e.target.value = '';
  };

  const submit = (e) => {
    e.preventDefault();
    setError(null);
    const formData = new FormData();
    formData.append('tour_booking_id', tourBookingId);
    formData.append('rating', String(rating));
    if (comment.trim()) formData.append('comment', comment.trim());
    files.forEach((f) => formData.append('images[]', f));
    mutation.mutate(formData);
  };

  return (
    <form onSubmit={submit} className="rounded-2xl border border-[#e8e4dd] bg-white p-5 space-y-3">
      <h3 className="font-semibold text-[#1a1a1a]">{t('tours.reviews.writeTitle')}</h3>
      <div className="flex items-center gap-1" role="radiogroup" aria-label={t('tours.reviews.writeTitle')}>
        {[1, 2, 3, 4, 5].map((s) => (
          <button
            key={s}
            type="button"
            onClick={() => setRating(s)}
            className="p-1"
            aria-label={`Rate ${s}`}
            aria-pressed={s === rating}
          >
            <Star className={`w-6 h-6 ${s <= rating ? 'text-[#b8860b] fill-current' : 'text-stone-300'}`} />
          </button>
        ))}
      </div>
      <textarea
        rows={4}
        value={comment}
        onChange={(e) => setComment(e.target.value)}
        placeholder={t('tours.reviews.commentPlaceholder')}
        maxLength={2000}
        className="w-full rounded-xl border border-[#e8e4dd] px-3 py-2 text-sm"
      />
      <div>
        <input ref={fileRef} type="file" accept="image/*" multiple className="hidden" onChange={pickFiles} />
        {previews.length > 0 && (
          <div className="flex flex-wrap gap-2 mb-2">
            {previews.map((p, i) => (
              <div key={`${p.url}-${i}`} className="relative w-20 h-20 rounded-xl overflow-hidden border border-[#e8e4dd]">
                <img src={p.url} alt="" className="w-full h-full object-cover" />
                <button
                  type="button"
                  onClick={() => setFiles((prev) => prev.filter((_, j) => j !== i))}
                  className="absolute top-1 right-1 w-6 h-6 min-h-0 min-w-0 rounded-full bg-black/60 text-white flex items-center justify-center"
                  aria-label={t('tours.reviews.removePhoto')}
                >
                  <X className="w-3.5 h-3.5" />
                </button>
              </div>
            ))}
          </div>
        )}
        {files.length < MAX_IMAGES && (
          <button
            type="button"
            onClick={() => fileRef.current?.click()}
            className="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-[#e8e4dd] text-sm font-medium text-[#45423d] hover:bg-[#faf8f5]"
          >
            <ImagePlus className="w-4 h-4" />
            {t('tours.reviews.addPhotos', { count: MAX_IMAGES - files.length })}
          </button>
        )}
      </div>
      {error && <p className="text-sm text-red-600">{error}</p>}
      <button
        type="submit"
        disabled={mutation.isPending}
        className="px-5 py-2.5 rounded-xl bg-[#1a1a1a] text-white text-sm font-medium hover:bg-[#2d2a28] disabled:opacity-60"
      >
        {t('tours.reviews.submit')}
      </button>
    </form>
  );
}
