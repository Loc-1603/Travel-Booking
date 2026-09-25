import React from 'react';
import { formatPrice } from '../../lib/utils';
import { cn } from '../../lib/utils';

export function RoomPriceBadge({ price, nights, variant = 'compact', className }) {
  if (price == null) return null;

  const formattedPrice = formatPrice(price);
  const totalPrice = nights && nights > 0 ? formatPrice(price * nights) : null;

  if (variant === 'compact') {
    return (
      <div className={cn('flex items-baseline gap-1', className)}>
        <span className="text-[#b8860b] font-semibold text-lg">{formattedPrice}</span>
        <span className="text-[#5c5852] text-sm">/đêm</span>
        {totalPrice && (
          <span className="text-[#5c5852] text-xs ml-1">(Tổng {nights} đêm: {totalPrice})</span>
        )}
      </div>
    );
  }

  if (variant === 'detailed') {
    return (
      <div className={cn('flex flex-col items-end gap-0.5', className)}>
        <div className="flex items-baseline gap-1">
          <span className="text-[#b8860b] font-bold text-xl">{formattedPrice}</span>
          <span className="text-[#5c5852] text-sm">/đêm</span>
        </div>
        {totalPrice && (
          <span className="text-[#5c5852] text-sm">Tổng {nights} đêm: <span className="font-medium text-[#1a1a1a]">{totalPrice}</span></span>
        )}
      </div>
    );
  }

  return (
    <div className={cn('text-right', className)}>
      <div className="text-[#b8860b] font-semibold text-lg">{formattedPrice}</div>
      <div className="text-[#5c5852] text-xs">/đêm</div>
      {totalPrice && <div className="text-[#5c5852] text-xs mt-1">Tổng: {totalPrice}</div>}
    </div>
  );
}