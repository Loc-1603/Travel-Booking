import { Link } from 'react-router-dom';
import { MapPin } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { formatPrice, getRatingLabel } from '../lib/utils';
import { getTourPriceParts } from '../lib/tourSearch';

export function TourCard({ tour, to, currency = 'VND', imageOverlay, children }) {
  const { t } = useTranslation();
  const parts = getTourPriceParts(tour);
  const img = tour.banner_image || tour.images?.[0];
  const rating = tour.average_rating != null ? Number(tour.average_rating) : null;
  const reviewCount = tour.review_count != null ? Number(tour.review_count) : 0;
  const href = to ?? `/tours/${tour.uuid}`;

  const content = (
    <>
      <div className="aspect-[4/3] bg-neutral-200 relative overflow-hidden flex-shrink-0">
        {img?.url ? (
          <img
            src={img.url}
            alt={img.alt_text || tour.title}
            className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
          />
        ) : (
          <div className="w-full h-full flex items-center justify-center text-neutral-500">
            <MapPin className="w-12 h-12" />
          </div>
        )}
        {imageOverlay && (
          <div className="absolute inset-0 pointer-events-none">
            <div className="absolute top-3 right-3 pointer-events-auto">{imageOverlay}</div>
          </div>
        )}
      </div>
      <div className="p-5 flex flex-col flex-1">
        <h3 className="font-serif font-semibold text-lg text-neutral-900 group-hover:text-accent transition-colors truncate">
          {tour.title}
        </h3>
        <p className="text-sm text-neutral-600 mt-0.5">
          {tour.province?.name || t('tours.list.unknownProvince')}
        </p>
        {tour.provider && (
          <p className="text-sm text-neutral-500 mt-0.5 truncate">
            {tour.provider.business_name}
          </p>
        )}
        {rating != null && rating > 0 && (
          <div className="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1">
            <span className="font-semibold text-neutral-900">{rating.toFixed(1)}</span>
            <span className="text-neutral-600 text-sm">{getRatingLabel(rating)}</span>
            {reviewCount > 0 && (
              <span className="text-neutral-500 text-sm">
                {t('tours.reviewCount', { count: reviewCount })}
              </span>
            )}
          </div>
        )}
        {parts && (parts.hourly > 0 || parts.daily > 0) && (
          <div className="mt-2 space-y-0.5" data-testid="tour-price">
            {parts.hourly > 0 && (
              <p className="text-sm text-neutral-900">
                <span className="font-semibold">{formatPrice(parts.hourTotal, currency)}</span>{' '}
                <span className="text-neutral-500">{t('tours.card.perHourUnit')}</span>
              </p>
            )}
            {parts.daily > 0 && (
              <p className="text-sm text-neutral-900">
                <span className="font-semibold">{formatPrice(parts.dayTotal, currency)}</span>{' '}
                <span className="text-neutral-500">{t('tours.card.perDayUnit')}</span>
              </p>
            )}
          </div>
        )}
        {children}
      </div>
    </>
  );

  if (href.startsWith('http') || !href) {
    return (
      <div className="group flex flex-col rounded-2xl overflow-hidden border border-neutral-200 bg-white shadow-[0_4px_12px_rgb(26_26_26_/0.06)] hover:shadow-[0_12px_28px_rgb(26_26_26_/0.1)] hover:border-neutral-300 transition-all duration-300">
        {content}
      </div>
    );
  }

  return (
    <Link
      to={href}
      className="group flex flex-col rounded-2xl overflow-hidden border border-neutral-200 bg-white shadow-[0_4px_12px_rgb(26_26_26_/0.06)] hover:shadow-[0_12px_28px_rgb(26_26_26_/0.1)] hover:border-neutral-300 transition-all duration-300"
    >
      {content}
    </Link>
  );
}
