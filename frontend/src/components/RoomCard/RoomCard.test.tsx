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

// Minimal i18n mock (same pattern as HotelDetail.test.tsx) — the real i18n
// instance has no resources loaded in unit tests, so t() returns keys.
vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) => {
      const dict: Record<string, string> = {
        'hotelDetail.gallery.prev': 'Ảnh trước',
        'hotelDetail.gallery.next': 'Ảnh sau',
        'hotelDetail.gallery.carouselLabel': 'Hình ảnh phòng',
        'hotelDetail.gallery.imageAriaLabel': 'Ảnh {{index}}',
      };
      let s = dict[key] ?? key;
      if (opts) {
        for (const [k, v] of Object.entries(opts)) s = s.split(`{{${k}}}`).join(String(v));
      }
      return s;
    },
  }),
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
    // FX-5: only the first photo is rendered initially
    fireEvent.click(screen.getByAltText('Ảnh chính phòng deluxe'));
    // must receive the room, not the click event (regression: empty modal bug)
    expect(onViewDetails).toHaveBeenNthCalledWith(1, room);
    // next arrow reveals the second photo; clicking it opens the same room
    fireEvent.click(screen.getByRole('button', { name: 'Ảnh sau' }));
    fireEvent.click(screen.getByAltText('Ảnh phụ phòng deluxe'));
    expect(onViewDetails).toHaveBeenNthCalledWith(2, room);
  });

  it('shows one photo at a time with a counter and circular prev/next (FX-5)', () => {
    render(<RoomCard room={room} variant="detailed" />);
    // first photo by default, counter 1 / 2
    expect(screen.getByAltText('Ảnh chính phòng deluxe')).toBeInTheDocument();
    expect(screen.queryByAltText('Ảnh phụ phòng deluxe')).toBeNull();
    expect(screen.getByText('1 / 2')).toBeInTheDocument();

    // next → second photo
    fireEvent.click(screen.getByRole('button', { name: 'Ảnh sau' }));
    expect(screen.getByText('2 / 2')).toBeInTheDocument();
    expect(screen.queryByAltText('Ảnh chính phòng deluxe')).toBeNull();

    // next on the last photo wraps to the first
    fireEvent.click(screen.getByRole('button', { name: 'Ảnh sau' }));
    expect(screen.getByText('1 / 2')).toBeInTheDocument();
    expect(screen.getByAltText('Ảnh chính phòng deluxe')).toBeInTheDocument();

    // prev on the first photo wraps to the last
    fireEvent.click(screen.getByRole('button', { name: 'Ảnh trước' }));
    expect(screen.getByText('2 / 2')).toBeInTheDocument();
    expect(screen.getByAltText('Ảnh phụ phòng deluxe')).toBeInTheDocument();
  });

  it('renders no arrows for a single-photo room (FX-5)', () => {
    render(<RoomCard room={{ ...room, images: room.images.slice(0, 1) }} variant="detailed" />);
    expect(screen.getByAltText('Ảnh chính phòng deluxe')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Ảnh sau' })).toBeNull();
    expect(screen.queryByRole('button', { name: 'Ảnh trước' })).toBeNull();
  });

  it('has no axe violations', async () => {
    const { container } = render(
      <RoomCard room={room} variant="detailed" nights={2} onSelect={vi.fn()} />
    );
    await expectNoAxeViolations(container);
  });
});

describe('RoomCard — card variant', () => {
  it('shows a photo counter instead of stacked thumbnails (FX-5)', () => {
    render(<RoomCard room={room} variant="card" />);
    expect(screen.getByText('1 / 2')).toBeInTheDocument();
    expect(screen.queryByAltText('Ảnh phụ phòng deluxe')).toBeNull();
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

describe('RoomCard — horizontal row layout (FX-4)', () => {
  it('renders detailed as a full-width flex row: photo left, info right on sm+', () => {
    const { container } = render(
      <RoomCard room={room} variant="detailed" className="w-full" />
    );
    const article = container.querySelector('article');
    expect(article?.className).toMatch('@container');
    expect(article?.className).toMatch('w-full');
    expect(article?.className).toMatch('sm:flex');
    const heading = screen.getByRole('heading', { name: 'Phòng Deluxe Hướng Biển' });
    expect(heading.className).toMatch('line-clamp-2');
    expect(heading.className).not.toMatch(/(^|\s)truncate(\s|$)/);
  });

  it('renders full content with no axe violations', async () => {
    const { container } = render(
      <div>
        <RoomCard room={room} variant="detailed" nights={2} />
      </div>
    );
    // nothing truncated away early: description, first-4 amenity chips + overflow chip visible
    expect(screen.getByText(room.description)).toBeInTheDocument();
    expect(screen.getByText('Két an toàn')).toBeInTheDocument();
    expect(screen.getByText('+1 tiện nghi khác')).toBeInTheDocument();
    expect(screen.queryByText('Bồn tắm')).toBeNull(); // 5th amenity folds into the overflow chip
    expect(screen.getByText(room.cancellation_policy_summary)).toBeInTheDocument();
    await expectNoAxeViolations(container);
  });
});
