import React, { useState, memo } from 'react';
import { createPortal } from 'react-dom';
import { RoomDetailHeader } from './RoomDetailHeader';
import { RoomDetailTabs } from './RoomDetailTabs';
import { OverviewTab } from './tabs/OverviewTab';
import { GalleryTab } from './tabs/GalleryTab';
import { AmenitiesTab } from './tabs/AmenitiesTab';
import { PoliciesTab } from './tabs/PoliciesTab';
import { RoomDetailFooter } from './RoomDetailFooter';
import { useRoomDetailModal } from './hooks/useRoomDetailModal';
import { cn } from '../../../lib/utils';

const TAB_COMPONENTS = {
  overview: OverviewTab,
  gallery: GalleryTab,
  amenities: AmenitiesTab,
  policies: PoliciesTab,
};

export function RoomDetailModal({
  room,
  hotel,
  isOpen,
  onClose,
  onSelectRoom,
  quantity = 1,
  onQuantityChange,
  isLoggedIn = false,
  nights,
  className,
}) {
  const [activeTab, setActiveTab] = useState('overview');

  const {
    modalRef,
    handleBackdropClick,
  } = useRoomDetailModal({
    isOpen,
    onClose,
    onSelectRoom,
  });

  if (!isOpen || !room) return null;

  const TabComponent = TAB_COMPONENTS[activeTab] || OverviewTab;

  const modalContent = (
    <div
      ref={modalRef}
      className={cn(
        'fixed inset-0 z-50 flex flex-col bg-white rounded-t-3xl sm:rounded-3xl sm:max-w-4xl sm:mx-auto sm:my-8 sm:shadow-2xl sm:border sm:border-[#e8e4dd] overflow-hidden',
        className
      )}
      onClick={handleBackdropClick}
      role="dialog"
      aria-modal="true"
      aria-labelledby="room-detail-title"
    >
      <RoomDetailHeader room={room} onClose={onClose} />

      <RoomDetailTabs
        activeTab={activeTab}
        onTabChange={setActiveTab}
      />

      <div className="flex-1 overflow-y-auto -mx-4 px-4 pb-32">
        <TabComponent room={room} hotel={hotel} />
      </div>

      <RoomDetailFooter
        room={room}
        hotel={hotel}
        nights={nights}
        quantity={quantity}
        onQuantityChange={onQuantityChange}
        onSelectRoom={onSelectRoom}
        isLoggedIn={isLoggedIn}
        selected={false}
      />
    </div>
  );

  return createPortal(modalContent, document.body);
}

export const MemoizedRoomDetailModal = memo(RoomDetailModal);
MemoizedRoomDetailModal.displayName = 'RoomDetailModal';