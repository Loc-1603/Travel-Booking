import React from 'react';
import { Skeleton } from '../Skeleton';

export function RoomCardSkeleton({ variant = 'card', className }) {
  const baseClasses = 'rounded-2xl overflow-hidden border border-[#e8e4dd]/80 bg-white shadow-sm';
  const variantClass = variant === 'gallery' ? 'rounded-xl bg-[#e8e4dd]' : baseClasses;

  if (variant === 'gallery') {
    return (
      <article className={variantClass} style={{ height: '200px' }}>
        <Skeleton className="w-full h-full" />
      </article>
    );
  }

  return (
    <article className={variantClass} {...(className ? { className } : {})}>
      <Skeleton className="w-full aspect-[4/3]" />
      <div className="p-4 sm:p-5 space-y-3">
        <div className="flex items-start justify-between gap-3">
          <div className="flex-1 space-y-2">
            <Skeleton className="h-6 w-3/4" />
            <Skeleton className="h-4 w-1/2" />
          </div>
          <Skeleton className="h-8 w-24" />
        </div>
        <div className="flex flex-wrap gap-3">
          <Skeleton className="h-5 w-20" />
          <Skeleton className="h-5 w-20" />
          <Skeleton className="h-5 w-24" />
        </div>
        <Skeleton className="h-4 w-full" />
        <Skeleton className="h-4 w-5/6" />
        <div className="flex flex-wrap gap-2">
          <Skeleton className="h-6 w-20 rounded-full" />
          <Skeleton className="h-6 w-24 rounded-full" />
          <Skeleton className="h-6 w-28 rounded-full" />
          <Skeleton className="h-6 w-16 rounded-full" />
        </div>
        <Skeleton className="h-10 w-full rounded-xl" />
      </div>
    </article>
  );
}

export function RoomCardSkeletonList({ count = 3, variant = 'card' }) {
  return (
    <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3" role="status" aria-label="Đang tải danh sách phòng">
      {Array.from({ length: count }).map((_, i) => (
        <RoomCardSkeleton key={i} variant={variant} />
      ))}
    </div>
  );
}