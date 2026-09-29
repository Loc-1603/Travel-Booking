import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, cleanup } from '@testing-library/react';
import { useState } from 'react';
import { RoomCard } from './RoomCard';
import { RoomPriceBadge } from './RoomPriceBadge';
import { expectNoAxeViolations } from '../../test/axe';

// Counting mock: each call = one RoomCard render through its memo boundary
vi.mock('./RoomPriceBadge', () => ({
  RoomPriceBadge: vi.fn(() => null),
}));
const RoomPriceBadgeMock = vi.mocked(RoomPriceBadge);

const room = {
  id: 101,
  name: 'Phòng Deluxe Hướng Biển',
  description: 'Phòng rộng rãi với ban công nhìn ra biển, đầy đủ tiện nghi hiện đại cho kỳ nghỉ thoải mái.',
  size: 42,
  bed_type: 'king',
  view_type: 'sea',
  capacity: 3,
  base_price: 1500000,
  total_rooms: 5,
  max_quantity_per_booking: 3,
  room_type: { id: 2, name: 'Deluxe' },
  cancellation_policy_summary: 'Hủy miễn phí trước 48 giờ.',
  images: [
    { id: 1, url: '/img/main.jpg', alt_text: 'Ảnh chính phòng deluxe' },
    { id: 2, url: '/img/extra.jpg', alt_text: 'Ảnh phụ phòng deluxe' },
  ],
  amenities: [
    { id: 1, slug: 'wifi', name: 'Wi-Fi' },
    { id: 2, slug: 'air_conditioning', name: 'Điều hòa' },
    { id: 3, slug: 'minibar', name: 'Minibar' },
    { id: 4, slug: 'safe', name: 'Két an toàn' },
    { id: 5, slug: 'bathtub', name: 'Bồn tắm' },
  ],
};

beforeEach(() => {
  RoomPriceBadgeMock.mockClear();
});

afterEach(() => {
  cleanup();
});

describe('RoomCard — detailed variant', () => {
  it('renders name, room type, capacity, size, bed and view details', () => {
    render(<RoomCard room={room} variant="detailed" />);
    expect(screen.getByRole('heading', { name: 'Phòng Deluxe Hướng Biển' })).toBeInTheDocument();
    // room_type name appears twice: top-right badge + meta line
    expect(screen.getByText('Deluxe', { selector: 'p' })).toBeInTheDocument();
    expect(screen.getByText('Deluxe', { selector: 'span' })).toBeInTheDocument();
    expect(screen.getByText(/3 khách/)).toBeInTheDocument();
    expect(screen.getByText(/42 m²/)).toBeInTheDocument();
    expect(screen.getByText('king')).toBeInTheDocument();
    expect(screen.getByText('sea')).toBeInTheDocument();
    expect(screen.getByText(room.description)).toBeInTheDocument();
  });

  it('shows the first 4 amenities plus an overflow chip', () => {
    render(<RoomCard room={room} variant="detailed" />);
    expect(screen.getByText('Wi-Fi')).toBeInTheDocument();
    expect(screen.getByText('Két an toàn')).toBeInTheDocument();
    expect(screen.getByText('+1 tiện nghi khác')).toBeInTheDocument();
  });

  it('fires onSelect when "Chọn phòng này" is clicked', () => {
    const onSelect = vi.fn();
    render(<RoomCard room={room} variant="detailed" onSelect={onSelect} />);
    fireEvent.click(screen.getByRole('button', { name: 'Chọn phòng này' }));
    expect(onSelect).toHaveBeenCalledWith(room);
  });

  it('marks a selected card and disables its button', () => {
    render(<RoomCard room={room} variant="detailed" selected onSelect={vi.fn()} />);
    const btn = screen.getByRole('button', { name: 'Đã chọn' });
    expect(btn).toBeDisabled();
  });

  it('opens details with the room object when the gallery is clicked', () => {
    const onViewDetails = vi.fn();
    render(<RoomCard room={room} variant="detailed" onViewDetails={onViewDetails} />);
    fireEvent.click(screen.getByAltText('Ảnh chính phòng deluxe'));
    // must receive the room, not the click event (regression: empty modal bug)
    expect(onViewDetails).toHaveBeenNthCalledWith(1, room);
    fireEvent.click(screen.getByAltText('Ảnh phụ phòng deluxe'));
    expect(onViewDetails).toHaveBeenNthCalledWith(2, room);
  });

  it('has no axe violations', async () => {
    const { container } = render(
      <RoomCard room={room} variant="detailed" nights={2} onSelect={vi.fn()} />
    );
    await expectNoAxeViolations(container);
  });
});

describe('RoomCard — card variant', () => {
  it('shows the +N images badge over the featured image', () => {
    render(<RoomCard room={room} variant="card" />);
    expect(screen.getByText('+1')).toBeInTheDocument();
  });

  it('has no axe violations', async () => {
    const { container } = render(<RoomCard room={room} variant="card" nights={2} />);
    await expectNoAxeViolations(container);
  });
});

describe('RoomCard — gallery variant (booking widget picker)', () => {
  it('is a keyboard-operable button that fires onSelect on click and Enter', () => {
    const onSelect = vi.fn();
    render(<RoomCard room={room} variant="gallery" onSelect={onSelect} />);
    const card = screen.getByRole('button', { name: /Phòng Deluxe Hướng Biển/ });
    fireEvent.click(card);
    expect(onSelect).toHaveBeenCalledTimes(1);
    fireEvent.keyDown(card, { key: 'Enter' });
    expect(onSelect).toHaveBeenCalledTimes(2);
    expect(onSelect).toHaveBeenLastCalledWith(room);
  });

  it('shows capacity and per-night price in the overlay', () => {
    render(<RoomCard room={room} variant="gallery" />);
    expect(screen.getByText(/3 khách/)).toBeInTheDocument();
    expect(screen.getByText(/1\.500\.000đ\/đêm/)).toBeInTheDocument();
  });

  it('has no axe violations (selected state included)', async () => {
    const { container } = render(
      <RoomCard room={room} variant="gallery" selected onSelect={vi.fn()} />
    );
    await expectNoAxeViolations(container);
  });
});

describe('RoomCard — memoization (Phase 7.2 regression)', () => {
  // Stable props across parent re-renders — memo only works when callbacks are stable
  const stableOnSelect = vi.fn();

  it('skips re-render when props are shallow-equal', () => {
    function Parent() {
      const [, force] = useState(0);
      return (
        <>
          <RoomCard room={room} variant="detailed" nights={2} onSelect={stableOnSelect} />
          <button type="button" onClick={() => force((n) => n + 1)}>
            force-rerender
          </button>
        </>
      );
    }
    render(<Parent />);
    expect(RoomPriceBadgeMock).toHaveBeenCalledTimes(1);
    fireEvent.click(screen.getByRole('button', { name: 'force-rerender' }));
    // parent re-rendered, memo child must have been skipped
    expect(RoomPriceBadgeMock).toHaveBeenCalledTimes(1);
  });

  it('re-renders when the room object changes', () => {
    const before = RoomPriceBadgeMock.mock.calls.length;
    render(<RoomCard room={{ ...room, base_price: 999 }} variant="detailed" />);
    expect(RoomPriceBadgeMock.mock.calls.length).toBe(before + 1);
  });
});

describe('RoomCard — no images fallback', () => {
  it('renders the placeholder artwork instead of an empty grid', () => {
    const { container } = render(<RoomCard room={{ ...room, images: [] }} variant="detailed" />);
    expect(container.querySelector('svg')).toBeTruthy();
  });
});
