import React from 'react';
import { getAmenityLabel } from '../../../lib/amenities';
import { AmenityIcon } from '../../AmenityIcon';

const BED_TYPE_LABELS = {
  single: 'Giường đơn',
  double: 'Giường đôi',
  queen: 'Giường Queen',
  king: 'Giường King',
  twin: '2 giường đơn',
  sofa_bed: 'Sofa giường',
  bunk: 'Giường tầng',
};

const VIEW_TYPE_LABELS = {
  city: 'View thành phố',
  sea: 'View biển',
  mountain: 'View núi',
  garden: 'View vườn',
  pool: 'View hồ bơi',
  courtyard: 'View sân trong',
  landmark: 'View điểm du lịch',
};

function InfoRow({ label, value, icon }) {
  if (!value) return null;
  return (
    <div className="flex items-start gap-3 py-3 border-t border-[#e8e4dd]/50">
      <div className="flex-shrink-0 w-10 h-10 rounded-xl bg-[#f5f2ed] flex items-center justify-center text-[#b8860b]600">
        {icon}
      </div>
      <div className="flex-1">
        <p className="text-[#5c5852] text-sm">{label}</p>
        <p className="text-[#1a1a1a] font-medium">{value}</p>
      </div>
    </div>
  );
}

function PolicyRow({ label, value, icon }) {
  if (!value) return null;
  return (
    <div className="flex items-start gap-3 py-2">
      <div className="flex-shrink-0 w-8 h-8 rounded-lg bg-[#f5f2ed] flex items-center justify-center text-[#b8860b]600">
        {icon}
      </div>
      <div className="flex-1">
        <p className="text-[#5c5852] text-sm">{label}</p>
        <p className="text-[#1a1a1a]">{value}</p>
      </div>
    </div>
  );
}

export function OverviewTab({ room }) {
  const {
    description,
    size,
    bed_type,
    view_type,
    capacity,
    total_rooms,
    amenities = [],
    cancellation_policy_summary,
    cancellation_policy,
    check_in_time,
    check_out_time,
    children_policy,
    pet_policy,
  } = room;

  return (
    <div id="panel-overview" role="tabpanel" aria-labelledby="tab-overview" className="p-4 space-y-6">
      {description && (
        <section>
          <h2 className="text-lg font-semibold text-[#1a1a1a] mb-3">Mô tả phòng</h2>
          <p className="text-[#45423d] leading-relaxed whitespace-pre-line">{description}</p>
        </section>
      )}

      <section>
        <h2 className="text-lg font-semibold text-[#1a1a1a] mb-3">Thông tin cơ bản</h2>
        <div className="space-y-0">
          <InfoRow label="Sức chứa" value={`${capacity} khách`} icon={<span>👥</span>} />
          {size && <InfoRow label="Diện tích" value={`${size} m²`} icon={<span>🏠</span>} />}
          {bed_type && <InfoRow label="Loại giường" value={BED_TYPE_LABELS[bed_type] || bed_type} icon={<span>🛏️</span>} />}
          {view_type && <InfoRow label="View phòng" value={VIEW_TYPE_LABELS[view_type] || view_type} icon={<span>🌄</span>} />}
          {total_rooms && <InfoRow label="Số phòng có sẵn" value={`${total_rooms} phòng`} icon={<span>🚪</span>} />}
        </div>
      </section>

      {amenities.length > 0 && (
        <section>
          <h2 className="text-lg font-semibold text-[#1a1a1a] mb-3">Tiện nghi trong phòng</h2>
          <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
            {amenities.map((a) => (
              <div key={a.id} className="flex items-center gap-2 px-3 py-2 rounded-xl bg-[#faf8f5] border border-[#e8e4dd]">
                <AmenityIcon slug={a.slug} className="w-5 h-5 text-[#b8860b]600 shrink-0" />
                <span className="text-[#45423d] text-sm">{getAmenityLabel((k) => k, a)}</span>
              </div>
            ))}
          </div>
        </section>
      )}

      {(cancellation_policy_summary || cancellation_policy || check_in_time || check_out_time || children_policy || pet_policy) && (
        <section>
          <h2 className="text-lg font-semibold text-[#1a1a1a] mb-3">Chính sách</h2>
          <div className="space-y-0">
            {cancellation_policy_summary && <PolicyRow label="Hủy đặt phòng" value={cancellation_policy_summary} icon={<span>❌</span>} />}
            {check_in_time && <PolicyRow label="Nhận phòng" value={check_in_time} icon={<span>🕘</span>} />}
            {check_out_time && <PolicyRow label="Trả phòng" value={check_out_time} icon={<span>🕛</span>} />}
            {children_policy && <PolicyRow label="Trẻ em" value={children_policy} icon={<span>👶</span>} />}
            {pet_policy && <PolicyRow label="Thú cưng" value={pet_policy} icon={<span>🐾</span>} />}
          </div>
        </section>
      )}
    </div>
  );
}
