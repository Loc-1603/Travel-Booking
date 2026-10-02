import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { HotelRoomFilterBar } from './HotelRoomFilterBar';

const translations: Record<string, string> = {
  'hotelDetail.rooms.filterBar.checkIn': 'Nhận phòng',
  'hotelDetail.rooms.filterBar.checkOut': 'Trả phòng',
  'hotelDetail.rooms.filterBar.guests': 'Khách',
  'hotelDetail.rooms.filterBar.guestSummary': '{{adults}} người lớn · {{rooms}} phòng',
  'hotelDetail.rooms.filterBar.adults': 'Người lớn',
  'hotelDetail.rooms.filterBar.rooms': 'Phòng',
  'hotelDetail.rooms.filterBar.search': 'Tìm kiếm',
  'hotelDetail.rooms.filterBar.filter': 'Bộ lọc',
  'hotelDetail.rooms.filterBar.decreaseAdults': 'Giảm người lớn',
  'hotelDetail.rooms.filterBar.increaseAdults': 'Tăng người lớn',
  'hotelDetail.rooms.filterBar.decreaseRooms': 'Giảm số phòng',
  'hotelDetail.rooms.filterBar.increaseRooms': 'Tăng số phòng',
};

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) => {
      let s = translations[key] ?? key;
      if (opts) {
        for (const [k, v] of Object.entries(opts)) s = s.split(`{{${k}}}`).join(String(v));
      }
      return s;
    },
  }),
}));

vi.mock('./DatePicker', () => ({
  DatePicker: ({ id, value, onChange }: { id?: string; value?: string; onChange?: (v: string) => void }) => (
    <input
      id={id}
      type="date"
      value={value ?? ''}
      onChange={(e: { target: { value: string } }) => onChange?.(e.target.value)}
    />
  ),
}));

const baseSearch = { checkIn: '2026-10-01', checkOut: '2026-10-03', adults: 2, rooms: 1 };

function renderBar(overrides = {}) {
  const props = {
    roomSearch: { ...baseSearch },
    onSearchChange: vi.fn(),
    onSearch: vi.fn(),
    onFilterClick: vi.fn(),
    isLoading: false,
    activeFilterCount: 0,
    ...overrides,
  };
  render(<HotelRoomFilterBar {...props} />);
  return props;
}

describe('HotelRoomFilterBar (redesign FE-3)', () => {
  it('renders date fields, guest summary and both action buttons', () => {
    renderBar();
    expect(screen.getByLabelText('Nhận phòng')).toHaveValue('2026-10-01');
    expect(screen.getByLabelText('Trả phòng')).toHaveValue('2026-10-03');
    expect(screen.getByText('2 người lớn · 1 phòng')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Tìm kiếm' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Bộ lọc' })).toBeInTheDocument();
  });

  it('auto-bumps check-out to check-in + 1 night when check-in moves past it', () => {
    const props = renderBar();
    fireEvent.change(screen.getByLabelText('Nhận phòng'), { target: { value: '2026-10-05' } });
    expect(props.onSearchChange).toHaveBeenCalledWith({
      checkIn: '2026-10-05',
      checkOut: '2026-10-06',
    });
  });

  it('disables search when check-out is not after check-in', () => {
    renderBar({ roomSearch: { ...baseSearch, checkOut: '2026-10-01' } });
    expect(screen.getByRole('button', { name: 'Tìm kiếm' })).toBeDisabled();
  });

  it('calls onSearch on button click when dates are valid', () => {
    const props = renderBar();
    fireEvent.click(screen.getByRole('button', { name: 'Tìm kiếm' }));
    expect(props.onSearch).toHaveBeenCalledTimes(1);
  });

  it('opens the guest dropdown with adults-only steppers clamped to bounds', () => {
    const props = renderBar({ roomSearch: { ...baseSearch, adults: 1 } });
    fireEvent.click(screen.getByText('1 người lớn · 1 phòng'));
    expect(screen.getByRole('dialog', { name: 'Khách' })).toBeInTheDocument();
    expect(screen.queryByText('Trẻ em')).toBeNull(); // adults only

    expect(screen.getByRole('button', { name: 'Giảm người lớn' })).toBeDisabled();
    fireEvent.click(screen.getByRole('button', { name: 'Tăng người lớn' }));
    expect(props.onSearchChange).toHaveBeenCalledWith({ adults: 2 });
  });

  it('forwards the filter button click and shows the active-filter badge', () => {
    const props = renderBar({ activeFilterCount: 3 });
    expect(screen.getByText('3')).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: /Bộ lọc/ }));
    expect(props.onFilterClick).toHaveBeenCalledTimes(1);
  });
});
