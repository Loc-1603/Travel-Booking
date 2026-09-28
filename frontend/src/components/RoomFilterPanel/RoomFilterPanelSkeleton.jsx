import { Skeleton } from '../Skeleton';

// Suspense fallback for lazy RoomFilterPanel — mirrors the 280px desktop sidebar layout
export function RoomFilterPanelSkeleton() {
  return (
    <aside className="w-[280px] shrink-0" aria-hidden="true">
      <div className="rounded-2xl border border-[#e8e4dd] bg-white p-5 shadow-sm space-y-5">
        <div className="flex items-center justify-between">
          <Skeleton className="h-6 w-28" />
          <Skeleton className="h-4 w-16" />
        </div>
        {[0, 1, 2, 3, 4].map((i) => (
          <div key={i} className="space-y-3">
            <Skeleton className="h-5 w-20" />
            <Skeleton className="h-9 w-full" />
            <Skeleton className="h-9 w-full" />
          </div>
        ))}
      </div>
    </aside>
  );
}
