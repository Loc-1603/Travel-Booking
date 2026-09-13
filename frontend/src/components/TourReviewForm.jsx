import { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { Star } from 'lucide-react';
import { api } from '../lib/api';

export function TourReviewForm({ tourBookingId, onSubmitted }) {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const [rating, setRating] = useState(5);
  const [comment, setComment] = useState('');
  const [error, setError] = useState(null);

  const mutation = useMutation({
    mutationFn: (body) => api.post('/tour-reviews', body),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['tour-reviews'] });
      setComment('');
      setError(null);
      onSubmitted?.();
    },
    onError: (err) => setError(err?.response?.data?.message || err?.message),
  });

  const submit = (e) => {
    e.preventDefault();
    setError(null);
    mutation.mutate({ tour_booking_id: tourBookingId, rating, comment: comment.trim() || undefined });
  };

  return (
    <form onSubmit={submit} className="rounded-2xl border border-[#e8e4dd] bg-white p-5 space-y-3">
      <h3 className="font-semibold text-[#1a1a1a]">{t('tours.reviews.writeTitle')}</h3>
      <div className="flex items-center gap-1">
        {[1, 2, 3, 4, 5].map((s) => (
          <button
            key={s}
            type="button"
            onClick={() => setRating(s)}
            className="p-1"
            aria-label={`Rate ${s}`}
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
