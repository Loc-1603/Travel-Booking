// Minimal page-level skeleton shown while a lazy route chunk loads
export default function RouteLoader() {
  return (
    <div className="w-full" role="status" aria-label="Đang tải trang">
      <div className="h-8 w-56 bg-[#e8e4dd] rounded-lg animate-pulse mb-6" />
      <div className="h-48 bg-[#e8e4dd] rounded-2xl animate-pulse mb-6" />
      <div className="space-y-3">
        {[1, 2, 3].map((i) => (
          <div key={i} className="h-24 bg-[#e8e4dd] rounded-2xl animate-pulse" />
        ))}
      </div>
    </div>
  );
}
