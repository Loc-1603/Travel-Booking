import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Star } from 'lucide-react';
import GuideAvatar from './GuideAvatar';
import { formatPrice, getRatingLabel } from '../lib/utils';
import { getGuideScore } from '../lib/guideSearch';

/**
 * 1vs1 guide card: avatar, rating + review count + ranking score,
 * starting price, and link to the guide profile.
 */
export function VendorGuideCard({ guide, provinceSlug, travelDate, sort, currency = 'VND' }) {
  const { t } = useTranslation();
  const rating = guide.average_rating != null ? Number(guide.average_rating) : null;
  const reviewCount = guide.review_count != null ? Number(guide.review_count) : 0;
  const score = getGuideScore(guide);
  const params = new URLSearchParams();
  if (provinceSlug) params.set('province', provinceSlug);
  if (travelDate) params.set('date', travelDate);
  if (sort) params.set('sort', sort);
  const qs = params.toString();
  const href = `/guides/${guide.uuid}${qs ? `?${qs}` : ''}`;

  return (
    <Link
      to={href}
      className="group flex flex-col rounded-2xl overflow-hidden border border-[#e8e4dd] bg-white shadow-[0_4px_12px_rgb(26_26_26_/0.06)] hover:shadow-[0_12px_28px_rgb(26_26_26_/0.1)] hover:border-[#d4cec4] transition-all duration-300"
    >
      <div className="p-5 flex flex-col items-center text-center gap-4">
        <GuideAvatar name={guide.business_name} avatarUrl={guide.avatar} size={64} />
        <div className="min-w-0 flex-1 w-full">
          <h3 className="font-serif font-semibold text-lg text-[#1a1a1a] group-hover:text-[#b8860b] transition-colors truncate">
            {guide.business_name}
          </h3>
          {guide.languages?.length > 0 && (
            <p className="text-sm text-[#7a756d] mt-0.5 truncate">{guide.languages.join(', ')}</p>
          )}
          <div className="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
            {rating != null && rating > 0 ? (
              <>
                <span className="inline-flex items-center gap-1 font-semibold text-[#1a1a1a]">
                  <Star className="w-4 h-4 text-[#b8860b] fill-current" />
                  {rating.toFixed(1)}
                </span>
                <span className="text-[#5c5852]">{getRatingLabel(rating)}</span>
                <span className="text-[#7a756d]">
                  {reviewCount} {t('tours.guides.reviewsCount')}
                </span>
                <span className="text-xs px-2 py-0.5 rounded-full bg-[#f9edd1] text-[#996f09] font-medium">
                  {score} {t('tours.guides.score')}
                </span>
              </>
            ) : (
              <span className="text-[#7a756d]">{t('tours.reviews.empty')}</span>
            )}
          </div>
        </div>
      </div>
      <div className="px-5 pb-5 pt-0 flex items-center justify-between gap-2 mt-auto">
        <div className="text-sm">
          {guide.price_from != null ? (
            <>
              <span className="text-[#7a756d]">{t('tours.guides.priceFrom')} </span>
              <span className="font-semibold text-[#1a1a1a]">{formatPrice(Number(guide.price_from), currency)}</span>
            </>
          ) : (
            <span className="text-[#7a756d]">—</span>
          )}
          {guide.primary_tour?.title && (
            <p className="text-xs text-[#7a756d] mt-0.5 truncate max-w-[220px]">{guide.primary_tour.title}</p>
          )}
        </div>
        <span className="shrink-0 inline-flex items-center gap-1.5 text-sm font-medium text-[#b8860b]">
          {t('tours.guides.viewProfile')} <span aria-hidden>→</span>
        </span>
      </div>
    </Link>
  );
}
