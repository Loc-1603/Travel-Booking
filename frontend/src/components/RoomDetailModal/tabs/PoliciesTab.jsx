import React from 'react';

function PolicyCard({ title, description, icon, children }) {
  if (!description && !children) return null;

  return (
    <div className="rounded-2xl border border-[#e8e4dd] bg-white overflow-hidden">
      <div className="flex items-center gap-3 px-5 py-4 bg-[#faf8f5] border-b border-[#e8e4dd]">
        <div className="w-10 h-10 rounded-xl bg-[#f5f2ed] flex items-center justify-center text-[#b8860b]600 shrink-0">
          {icon}
        </div>
        <h3 className="font-semibold text-[#1a1a1a]">{title}</h3>
      </div>
      <div className="p-5">
        {description && <p className="text-[#45423d] leading-relaxed">{description}</p>}
        {children}
      </div>
    </div>
  );
}

function PolicyRow({ label, value }) {
  if (!value) return null;
  return (
    <div className="flex items-center justify-between py-2 border-t border-[#e8e4dd]/50 last:border-0">
      <span className="text-[#5c5852]">{label}</span>
      <span className="text-[#1a1a1a] font-medium">{value}</span>
    </div>
  );
}

export function PoliciesTab({ room }) {
  const {
    cancellation_policy,
    cancellation_policy_summary,
    check_in_time,
    check_out_time,
    children_policy,
    pet_policy,
    smoking_policy,
    party_policy,
    additional_rules,
  } = room;

  return (
    <div id="panel-policies" role="tabpanel" aria-labelledby="tab-policies" className="p-4 space-y-6">
      <PolicyCard
        title="Chính sách hủy đặt phòng"
        icon={<svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" /></svg>}
      >
        {cancellation_policy_summary && (
          <p className="text-[#b8860b] font-medium mb-3">{cancellation_policy_summary}</p>
        )}
        {cancellation_policy && (
          <div className="prose prose-sm max-w-none text-[#45423d] whitespace-pre-line">
            {cancellation_policy}
          </div>
        )}
      </PolicyCard>

      <PolicyCard
        title="Thời gian nhận/trả phòng"
        icon={<svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>}
      >
        <div className="space-y-0">
          <PolicyRow label="Nhận phòng" value={check_in_time || '14:00'} />
          <PolicyRow label="Trả phòng" value={check_out_time || '12:00'} />
        </div>
      </PolicyCard>

      <PolicyCard
        title="Trẻ em & Giường thêm"
        icon={<svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>}
      >
        {children_policy ? (
          <p className="text-[#45423d] whitespace-pre-line">{children_policy}</p>
        ) : (
          <div className="space-y-0">
            <PolicyRow label="Trẻ em" value="Chào đón trẻ em mọi lứa tuổi" />
            <PolicyRow label="Giường thêm" value="Có sẵn theo yêu cầu (có thể phát sinh phí)" />
          </div>
        )}
      </PolicyCard>

      <PolicyCard
        title="Thú cưng"
        icon={<svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4.5v15m7.5-7.5h-15" /></svg>}
      >
        {pet_policy ? (
          <p className="text-[#45423d] whitespace-pre-line">{pet_policy}</p>
        ) : (
          <p className="text-[#5c5852]">Không cho phép thú cưng</p>
        )}
      </PolicyCard>

      {(smoking_policy || party_policy || additional_rules) && (
        <PolicyCard
          title="Quy định khác"
          icon={<svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>}
        >
          <div className="space-y-0">
            {smoking_policy && <PolicyRow label="Hút thuốc" value={smoking_policy} />}
            {party_policy && <PolicyRow label="Tiệc tùng/Sự kiện" value={party_policy} />}
            {additional_rules && (
              <div className="pt-2">
                <p className="text-[#5c5852] mb-2">Quy định bổ sung:</p>
                <p className="text-[#45423d] whitespace-pre-line">{additional_rules}</p>
              </div>
            )}
          </div>
        </PolicyCard>
      )}

      {!cancellation_policy && !check_in_time && !check_out_time && !children_policy && !pet_policy && !smoking_policy && !party_policy && !additional_rules && (
        <div className="text-center py-12">
          <svg className="w-16 h-16 mx-auto text-[#d4cec4]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          <p className="text-[#5c5852] mt-4">Chưa có thông tin chính sách chi tiết</p>
        </div>
      )}
    </div>
  );
}
