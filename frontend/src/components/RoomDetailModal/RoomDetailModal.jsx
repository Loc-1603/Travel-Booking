import { useState, memo } from 'react';
import { createPortal } from 'react-dom';
import { RoomDetailHeader } from './RoomDetailHeader';
import { RoomDetailTabs } from './RoomDetailTabs';
import { OverviewTab } from './tabs/OverviewTab';
import { GalleryTab } from './tabs/GalleryTab';
import { AmenitiesTab } from './tabs/AmenitiesTab';
import { PoliciesTab } from './tabs/PoliciesTab';
import { RoomDetailFooter } from './RoomDetailFooter';
import { useRoomDetailModal } from './hooks/useRoomDetailModal';
import { cn } from '../../lib/utils';

const TAB_COMPONENTS = {
  overview: OverviewTab,
  gallery: GalleryTab,
  amenities: AmenitiesTab,
  policies: PoliciesTab,
};

export const RoomDetailModal = memo(function RoomDetailModal({
  room,
  hotel,
  isOpen,
  onClose,
  onSelectRoom,
  quantity = 1,
  onQuantityChange,
  isLoggedIn = false,
  nights,
  initialTab = 'overview',
  className,
}) {
  const [activeTab, setActiveTab] = useState(initialTab);
  const [prevInitialTab, setPrevInitialTab] = useState(initialTab);
  const [prevIsOpen, setPrevIsOpen] = useState(isOpen);
  // Reset tab when a new deep-link tab is requested or the modal is reopened
  // (render-phase update, no effect needed)
  if (initialTab !== prevInitialTab || (isOpen && !prevIsOpen)) {
    setPrevInitialTab(initialTab);
    setPrevIsOpen(isOpen);
    setActiveTab(initialTab);
  } else if (isOpen !== prevIsOpen) {
    setPrevIsOpen(isOpen);
  }

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
        'fixed inset-0 z-50 flex flex-col bg-white rounded-t-3xl sm:rounded-3xl sm:w-[90vw] sm:max-w-4xl sm:mx-auto sm:my-8 sm:shadow-2xl sm:border sm:border-[#e8e4dd] overflow-hidden',
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

      <div data-modal-scroll className="flex-1 overflow-y-auto overscroll-contain -mx-4 px-4 pb-32">
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
});
