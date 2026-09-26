import { memo } from 'react';
import { X } from 'lucide-react';
import { formatPrice } from '../../lib/utils';
import { cn } from '../../lib/utils';

export const RoomDetailHeader = memo(function RoomDetailHeader({ room, onClose, className }) {
  const { name, room_type, base_price, average_rating, review_count } = room;
  const price = base_price != null ? Number(base_price) : null;

  return (
    <header className={cn('sticky top-0 z-10 flex items-start justify-between gap-4 p-4 pt-[calc(env(safe-area-inset-top)+1rem)] sm:pt-4 bg-white/95 backdrop-blur-sm border-b border-[#e8e4dd]', className)}>
      <div className="flex-1 min-w-0 pr-4">
        <h1 className="text-xl sm:text-2xl font-bold text-[#1a1a1a] truncate">{name}</h1>
        {room_type && (
          <span className="inline-block mt-1.5 px-2.5 py-0.5 text-xs font-medium rounded-full bg-[#f5f2ed] text-[#b8860b]700">
            {room_type.name}
          </span>
        )}
        {(average_rating != null || review_count > 0) && (
          <div className="flex items-center gap-2 mt-2 text-sm text-[#5c5852]">
            {average_rating != null && (
              <span className="flex items-center gap-1 font-medium text-[#1a1a1a]">
                <svg className="w-4 h-4 fill-amber-400 text-amber-400" viewBox="0 0 24 24">
                  <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                </svg>
                {Number(average_rating).toFixed(1)}
              </span>
            )}
            {review_count > 0 && (
              <span>· {review_count} đánh giá</span>
            )}
          </div>
        )}
      </div>

      <div className="flex items-center gap-2 shrink-0">
        {price != null && (
          <div className="hidden sm:block text-right">
            <div className="text-[#b8860b] font-bold text-xl">{formatPrice(price)}</div>
            <div className="text-[#5c5852] text-sm">/đêm</div>
          </div>
        )}
        <button
          type="button"
          onClick={onClose}
          className="p-2 rounded-xl bg-[#f5f2ed] text-[#5c5852] hover:bg-[#e8e4dd] hover:text-[#1a1a1a] transition-colors"
          aria-label="Đóng chi tiết phòng"
        >
          <X className="w-5 h-5" />
        </button>
      </div>
    </header>
  );
});
