import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, cleanup, waitFor, within } from '@testing-library/react';
import { RoomDetailModal } from './RoomDetailModal';
import { expectNoAxeViolations } from '../../test/axe';

const room = {
  id: 101,
  name: 'Phòng Deluxe Hướng Biển',
  base_price: 1500000,
  total_rooms: 5,
  max_quantity_per_booking: 3,
  capacity: 3,
  size: 42,
  bed_type: 'king',
  view_type: 'sea',
  description: 'Phòng rộng rãi với ban công nhìn ra biển.',
  cancellation_policy_summary: 'Hủy miễn phí trước 48 giờ.',
  check_in_time: '14:00',
  check_out_time: '12:00',
  room_type: { id: 2, name: 'Deluxe' },
  average_rating: 8.9,
  review_count: 12,
  images: [
    { id: 1, url: '/img/one.jpg', alt_text: 'Ảnh một' },
    { id: 2, url: '/img/two.jpg', alt_text: 'Ảnh hai' },
  ],
  amenities: [
    { id: 1, slug: 'wifi', name: 'Wi-Fi' },
    { id: 2, slug: 'bathtub', name: 'Bồn tắm' },
  ],
};

const hotel = { id: 1, name: 'Khách sạn Biển Xanh' };

beforeEach(() => {
  document.body.style.overflow = '';
});

afterEach(() => {
  cleanup();
  document.body.style.overflow = '';
});

function renderModal(props = {}) {
  const utils = render(
    <RoomDetailModal
      room={room}
      hotel={hotel}
      isOpen
      onClose={vi.fn()}
      onSelectRoom={vi.fn()}
      onQuantityChange={vi.fn()}
      isLoggedIn
      nights={2}
      {...props}
    />
  );
  return utils;
}

describe('RoomDetailModal — structure', () => {
  it('renders as a dialog named by the header heading (aria-labelledby)', async () => {
    renderModal();
    const dialog = screen.getByRole('dialog');
    const labelledById = dialog.getAttribute('aria-labelledby');
    expect(labelledById).toBe('room-detail-title');
    expect(document.getElementById('room-detail-title')?.textContent).toBe('Phòng Deluxe Hướng Biển');
  });

  it('locks body scroll while open and unlocks on close', () => {
    const onClose = vi.fn();
    const { rerender } = renderModal({ onClose });
    expect(document.body.style.overflow).toBe('hidden');
    rerender(
      <RoomDetailModal room={room} hotel={hotel} isOpen={false} onClose={onClose} onQuantityChange={vi.fn()} isLoggedIn />
    );
    expect(document.body.style.overflow).toBe('');
  });

  it('moves focus into the dialog shortly after opening', async () => {
    renderModal();
    // first focusable is the icon-only close button (aria-label, no text content)
    await waitFor(() =>
      expect(document.activeElement).toBe(screen.getByRole('button', { name: 'Đóng chi tiết phòng' }))
    );
  });
});

describe('RoomDetailModal — tabs', () => {
  it('starts on the overview tab and shows description, meta and policies summary', () => {
    renderModal();
    expect(screen.getByText('Mô tả phòng')).toBeInTheDocument();
    expect(screen.getByText(room.description)).toBeInTheDocument();
    expect(screen.getByText('Giường King')).toBeInTheDocument();
    expect(screen.getByText('View biển')).toBeInTheDocument();
  });

  it('switches to the amenities tab with categorized groups', () => {
    renderModal();
    fireEvent.click(screen.getByRole('tab', { name: /Tiện nghi/ }));
    expect(screen.getByRole('tabpanel')).toHaveAttribute('id', 'panel-amenities');
    expect(screen.getByText('Wi-Fi')).toBeInTheDocument();
    expect(screen.getByText('Bồn tắm')).toBeInTheDocument();
  });

  it('switches to the policies tab and shows check-in/out times', () => {
    renderModal();
    fireEvent.click(screen.getByRole('tab', { name: /Chính sách/ }));
    expect(screen.getByRole('tabpanel')).toHaveAttribute('id', 'panel-policies');
    expect(screen.getByText('Chính sách hủy đặt phòng')).toBeInTheDocument();
    expect(screen.getByText('14:00')).toBeInTheDocument();
  });

  it('supports a deep-linked initialTab and resets on reopen', () => {
    const { rerender } = renderModal({ initialTab: 'gallery' });
    expect(screen.getByRole('tabpanel')).toHaveAttribute('id', 'panel-gallery');
    rerender(
      <RoomDetailModal room={room} hotel={hotel} isOpen initialTab="policies" onClose={vi.fn()} onQuantityChange={vi.fn()} isLoggedIn />
    );
    expect(screen.getByRole('tabpanel')).toHaveAttribute('id', 'panel-policies');
  });
});

describe('RoomDetailModal — gallery lightbox', () => {
  function openLightbox() {
    fireEvent.click(screen.getByRole('tab', { name: /Hình ảnh/ }));
    fireEvent.click(screen.getByRole('button', { name: 'Xem hình ảnh 1' }));
    return screen.getByRole('dialog', { name: 'Phóng to hình ảnh' });
  }

  it('opens the lightbox from the gallery tab', () => {
    renderModal();
    const lightbox = openLightbox();
    expect(within(lightbox).getByAltText('Ảnh một')).toBeInTheDocument();
  });

  it('ArrowRight/ArrowLeft navigate between images', () => {
    renderModal();
    const lightbox = openLightbox();
    // scope to the lightbox — the grid thumbnails behind share the same alt texts
    fireEvent.keyDown(document, { key: 'ArrowRight' });
    expect(within(lightbox).getByAltText('Ảnh hai')).toBeInTheDocument();
    fireEvent.keyDown(document, { key: 'ArrowLeft' });
    expect(within(lightbox).getByAltText('Ảnh một')).toBeInTheDocument();
  });

  it('Escape closes only the lightbox, not the modal (nested overlay rule)', () => {
    const onClose = vi.fn();
    renderModal({ onClose });
    openLightbox();
    fireEvent.keyDown(document, { key: 'Escape' });
    expect(screen.queryByRole('dialog', { name: 'Phóng to hình ảnh' })).toBeNull();
    // the room modal itself is still open
    expect(screen.getByRole('dialog', { name: 'Phòng Deluxe Hướng Biển' })).toBeInTheDocument();
    expect(onClose).not.toHaveBeenCalled();
  });

  it('a second Escape then closes the modal', () => {
    const onClose = vi.fn();
    renderModal({ onClose });
    openLightbox();
    fireEvent.keyDown(document, { key: 'Escape' });
    fireEvent.keyDown(document, { key: 'Escape' });
    expect(onClose).toHaveBeenCalledTimes(1);
  });
});

describe('RoomDetailModal — footer', () => {
  it('shows the multi-night total and quantity stepper', () => {
    renderModal({ quantity: 2 });
    expect(screen.getByText('Tổng 2 đêm × 2 phòng')).toBeInTheDocument();
    expect(screen.getByText('2')).toBeInTheDocument();
  });

  it('emits quantity changes and clamps at the bounds', () => {
    const onQuantityChange = vi.fn();
    renderModal({ onQuantityChange });
    expect(screen.getByRole('button', { name: 'Giảm số lượng' })).toBeDisabled();
    fireEvent.click(screen.getByRole('button', { name: 'Tăng số lượng' }));
    expect(onQuantityChange).toHaveBeenLastCalledWith(1);
  });

  it('delegates room selection to onSelectRoom', () => {
    const onSelectRoom = vi.fn();
    renderModal({ onSelectRoom });
    fireEvent.click(screen.getByRole('button', { name: 'Chọn phòng này' }));
    expect(onSelectRoom).toHaveBeenCalledTimes(1);
  });

  it('requires login — CTA disabled with "Đăng nhập để đặt"', () => {
    renderModal({ isLoggedIn: false });
    const cta = screen.getByRole('button', { name: /Đăng nhập để đặt/ });
    expect(cta).toBeDisabled();
  });
});

describe('RoomDetailModal — accessibility (axe-core)', () => {
  it('overview tab has no violations', async () => {
    renderModal();
    await expectNoAxeViolations(screen.getByRole('dialog'));
  });

  it('gallery tab + open lightbox have no violations', async () => {
    renderModal({ initialTab: 'gallery' });
    fireEvent.click(screen.getByRole('button', { name: 'Xem hình ảnh 1' }));
    await expectNoAxeViolations(document.body);
  });

  it('policies tab has no violations', async () => {
    renderModal({ initialTab: 'policies' });
    await expectNoAxeViolations(screen.getByRole('dialog'));
  });
});
