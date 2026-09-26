import { memo } from 'react';
import { cn, formatPrice } from '../../lib/utils';
import { Minus, Plus, User, Lock } from 'lucide-react';

export const RoomDetailFooter = memo(function RoomDetailFooter({
  room,
  nights,
  quantity,
  onQuantityChange,
  onSelectRoom,
  isLoggedIn,
  selected = false,
  className,
}) {
  const { base_price, total_rooms, max_quantity_per_booking } = room;
  const price = base_price != null ? Number(base_price) : null;
  const maxQty = Math.min(total_rooms || 5, max_quantity_per_booking || 5, 5);

  const subTotal = price && nights ? price * nights * quantity : null;
  const total = subTotal;

  return (
    <footer
      className={cn(
        'sticky bottom-0 z-10 flex items-center justify-between gap-4 px-4 pt-4 pb-[calc(env(safe-area-inset-bottom)+1rem)] bg-white/95 backdrop-blur-sm border-t border-[#e8e4dd] shadow-[0_-4px_20px_rgba(0,0,0,0.08)]',
        className
      )}
      role="contentinfo"
    >
      <div className="flex-1 min-w-0">
        {price != null && nights && (
          <div className="flex items-baseline justify-between gap-4 text-sm">
            <span className="text-[#5c5852]">Tổng {nights} đêm × {quantity} phòng</span>
            <span className="text-[#1a1a1a] font-semibold text-lg">{formatPrice(total)}</span>
          </div>
        )}
        {price != null && !nights && (
          <div className="text-right">
            <span className="text-[#b8860b] font-bold text-xl">{formatPrice(price)}</span>
            <span className="text-[#5c5852] text-sm">/đêm</span>
          </div>
        )}
      </div>

      <div className="flex items-center gap-3 shrink-0">
        <div className="flex items-center gap-2 border border-[#e8e4dd] rounded-xl overflow-hidden">
          <button
            type="button"
            onClick={() => onQuantityChange(-1)}
            disabled={quantity <= 1}
            className="p-3 text-[#5c5852] hover:bg-[#f5f2ed] disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
            aria-label="Giảm số lượng"
          >
            <Minus className="w-5 h-5" />
          </button>
          <span className="w-12 text-center font-semibold text-[#1a1a1a]">{quantity}</span>
          <button
            type="button"
            onClick={() => onQuantityChange(1)}
            disabled={quantity >= maxQty}
            className="p-3 text-[#5c5852] hover:bg-[#f5f2ed] disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
            aria-label="Tăng số lượng"
          >
            <Plus className="w-5 h-5" />
          </button>
        </div>

        <button
          type="button"
          onClick={onSelectRoom}
          disabled={selected || !isLoggedIn}
          className={cn(
            'flex-1 sm:w-auto px-6 py-3 rounded-xl font-semibold text-sm transition-all duration-200 flex items-center justify-center gap-2',
            selected
              ? 'bg-[#1a1a1a] text-white cursor-default'
              : isLoggedIn
              ? 'bg-[#b8860b] text-white hover:bg-[#996f09] shadow-lg shadow-[#b8860b]/30'
              : 'bg-[#d4cec4] text-[#7a756d] cursor-not-allowed'
          )}
        >
          {selected ? (
            <>
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
              </svg>
              Đã chọn
            </>
          ) : isLoggedIn ? (
            'Chọn phòng này'
          ) : (
            <>
              <Lock className="w-4 h-4" />
              Đăng nhập để đặt
            </>
          )}
        </button>
      </div>

      {!isLoggedIn && !selected && (
        <div className="fixed bottom-24 left-4 right-4 sm:left-auto sm:right-4 sm:w-72 bg-white rounded-2xl shadow-xl border border-[#e8e4dd] p-4 z-20 animate-slide-up">
          <div className="flex items-center gap-2 text-[#b8860b] mb-3">
            <User className="w-5 h-5" />
            <span className="font-semibold">Cần đăng nhập</span>
          </div>
          <p className="text-[#5c5852] text-sm mb-4">Vui lòng đăng nhập để chọn phòng và tiếp tục đặt phòng.</p>
          <a
            href="/login"
            className="block w-full py-2.5 rounded-xl bg-[#b8860b] text-white font-semibold text-center hover:bg-[#996f09] transition-colors"
          >
            Đăng nhập ngay
          </a>
        </div>
      )}
    </footer>
  );
});
