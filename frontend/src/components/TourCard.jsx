import { Link } from 'react-router-dom';
import { MapPin } from 'lucide-react';
import { formatPrice, getRatingLabel } from '../lib/utils';
import { getTourPrice } from '../lib/tourSearch';

export function TourCard({ tour, to, currency = 'VND', imageOverlay, children }) {
  const price = getTourPrice(tour);
  const img = tour.banner_image || tour.images?.[0];
  const rating = tour.average_rating != null ? Number(tour.average_rating) : null;
  const reviewCount = tour.review_count != null ? Number(tour.review_count) : 0;
  const href = to ?? `/tours/${tour.uuid}`;

  const content = (
    <>
      <div className="aspect-[4/3] bg-[#e8e4dd] relative overflow-hidden flex-shrink-0">
        {img?.url ? (
          <img
            src={img.url}
            alt={img.alt_text || tour.title}
            className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
          />
        ) : (
          <div className="w-full h-full flex items-center justify-center text-[#a39e94]">
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
        <h3 className="font-serif font-semibold text-lg text-[#1a1a1a] group-hover:text-[#b8860b] transition-colors truncate">
          {tour.title}
        </h3>
        <p className="text-sm text-[#5c5852] mt-0.5">
          {tour.province?.name || '—'}
        </p>
        {tour.provider && (
          <p className="text-sm text-[#7a756d] mt-0.5 truncate">
            {tour.provider.business_name}
          </p>
        )}
        {rating != null && rating > 0 && (
          <div className="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1">
            <span className="font-semibold text-[#1a1a1a]">{rating.toFixed(1)}</span>
            <span className="text-[#5c5852] text-sm">{getRatingLabel(rating)}</span>
            {reviewCount > 0 && (
              <span className="text-[#7a756d] text-sm">
                {reviewCount} {reviewCount === 1 ? 'review' : 'reviews'}
              </span>
            )}
          </div>
        )}
        {price != null && (
          <div className="mt-2 flex items-baseline gap-2 flex-wrap">
            <span className="font-semibold text-[#1a1a1a]">{formatPrice(price, currency)}</span>
            <span className="text-[#7a756d] text-sm">from / hour</span>
          </div>
        )}
        {children}
      </div>
    </>
  );

  if (href.startsWith('http') || !href) {
    return (
      <div className="group flex flex-col rounded-2xl overflow-hidden border border-[#e8e4dd] bg-white shadow-[0_4px_12px_rgb(26_26_26_/0.06)] hover:shadow-[0_12px_28px_rgb(26_26_26_/0.1)] hover:border-[#d4cec4] transition-all duration-300">
        {content}
      </div>
    );
  }

  return (
    <Link
      to={href}
      className="group flex flex-col rounded-2xl overflow-hidden border border-[#e8e4dd] bg-white shadow-[0_4px_12px_rgb(26_26_26_/0.06)] hover:shadow-[0_12px_28px_rgb(26_26_26_/0.1)] hover:border-[#d4cec4] transition-all duration-300"
    >
      {content}
    </Link>
  );
}
