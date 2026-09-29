import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, cleanup } from '@testing-library/react';
import { useState } from 'react';
import { RoomFilterPanel } from './RoomFilterPanel';
import { expectNoAxeViolations } from '../../test/axe';

const rooms = [
  {
    id: 101,
    capacity: 2,
    base_price: 500000,
    bed_type: 'double',
    view_type: 'city',
    room_type: { id: 1, name: 'Standard' },
    amenities: [{ id: 1, slug: 'wifi', name: 'Wi-Fi' }, { id: 2, slug: 'pool', name: 'Hồ bơi' }],
  },
  {
    id: 102,
    capacity: 4,
    base_price: 1500000,
    bed_type: 'king',
    view_type: 'sea',
    room_type: { id: 2, name: 'Deluxe' },
    amenities: [{ id: 1, slug: 'wifi', name: 'Wi-Fi' }],
  },
  {
    id: 103,
    capacity: 6,
    base_price: 900000,
    bed_type: 'queen',
    view_type: 'sea',
    room_type: { id: 1, name: 'Standard' },
    amenities: [],
  },
];

const baseFilters = {
  capacity: '',
  price: { min: '', max: '' },
  roomTypes: [],
  bedTypes: [],
  viewTypes: [],
  amenities: [],
};

beforeEach(() => {
  document.body.style.overflow = '';
});

afterEach(() => {
  cleanup();
  document.body.style.overflow = '';
});

describe('RoomFilterPanel — desktop sidebar', () => {
  it('derives filter options from the rooms list', () => {
    render(<RoomFilterPanel rooms={rooms} filters={baseFilters} onFiltersChange={vi.fn()} />);
    expect(screen.getByRole('checkbox', { name: 'Standard' })).toBeInTheDocument();
    expect(screen.getByRole('checkbox', { name: 'Deluxe' })).toBeInTheDocument();
    expect(screen.getByRole('checkbox', { name: 'king' })).toBeInTheDocument();
    expect(screen.getByRole('checkbox', { name: 'sea' })).toBeInTheDocument();
    expect(screen.getByRole('checkbox', { name: 'Wi-Fi' })).toBeInTheDocument();
    expect(screen.getByText(/500\.000đ — 1\.500\.000đ/)).toBeInTheDocument();
  });

  it('propagates capacity changes', () => {
    const onFiltersChange = vi.fn();
    render(<RoomFilterPanel rooms={rooms} filters={baseFilters} onFiltersChange={onFiltersChange} />);
    fireEvent.change(screen.getByRole('combobox', { name: 'Sức chứa tối thiểu' }), { target: { value: '4' } });
    expect(onFiltersChange).toHaveBeenCalledWith({ ...baseFilters, capacity: '4' });
  });

  it('toggles room type checkboxes (multi-select accumulates)', () => {
    // Realistic harness: feed the emitted filters back so multi-select accumulates
    const onFiltersChange = vi.fn();
    function StatefulPanel() {
      const [filters, setFilters] = useState(baseFilters);
      const handleChange = (next: typeof baseFilters) => {
        onFiltersChange(next);
        setFilters(next);
      };
      return <RoomFilterPanel rooms={rooms} filters={filters} onFiltersChange={handleChange} />;
    }
    render(<StatefulPanel />);
    fireEvent.click(screen.getByRole('checkbox', { name: 'Deluxe' }));
    expect(onFiltersChange).toHaveBeenLastCalledWith({ ...baseFilters, roomTypes: [2] });
    fireEvent.click(screen.getByRole('checkbox', { name: 'Standard' }));
    expect(onFiltersChange).toHaveBeenLastCalledWith({ ...baseFilters, roomTypes: [2, 1] });
    // checkbox states reflect the accumulated selection
    expect(screen.getByRole('checkbox', { name: 'Deluxe' })).toBeChecked();
    expect(screen.getByRole('checkbox', { name: 'Standard' })).toBeChecked();
  });

  it('toggles amenities with AND semantics client-side (panel just reports selection)', () => {
    const onFiltersChange = vi.fn();
    render(<RoomFilterPanel rooms={rooms} filters={baseFilters} onFiltersChange={onFiltersChange} />);
    fireEvent.click(screen.getByRole('checkbox', { name: 'Hồ bơi' }));
    expect(onFiltersChange).toHaveBeenLastCalledWith({ ...baseFilters, amenities: ['pool'] });
  });

  it('shows the active filter count badge', () => {
    render(
      <RoomFilterPanel
        rooms={rooms}
        filters={{ ...baseFilters, roomTypes: [1, 2], capacity: '4' }}
        onFiltersChange={vi.fn()}
      />
    );
    expect(screen.getByText('3')).toBeInTheDocument();
  });

  it('clears every filter back to defaults', () => {
    const onFiltersChange = vi.fn();
    render(
      <RoomFilterPanel
        rooms={rooms}
        filters={{ ...baseFilters, capacity: '2', roomTypes: [1] }}
        onFiltersChange={onFiltersChange}
      />
    );
    fireEvent.click(screen.getByRole('button', { name: 'Xóa tất cả' }));
    expect(onFiltersChange).toHaveBeenLastCalledWith(baseFilters);
  });

  it('has no axe violations', async () => {
    const { container } = render(<RoomFilterPanel rooms={rooms} filters={baseFilters} onFiltersChange={vi.fn()} />);
    await expectNoAxeViolations(container);
  });
});

describe('RoomFilterPanel — mobile bottom sheet', () => {
  it('renders the sheet with an apply button that closes it', () => {
    const onClose = vi.fn();
    const { container } = render(
      <RoomFilterPanel rooms={rooms} filters={baseFilters} onFiltersChange={vi.fn()} isMobile onClose={onClose} />
    );
    expect(screen.getByText('Bộ lọc')).toBeInTheDocument();
    expect(document.body.style.overflow).toBe('hidden');

    fireEvent.click(screen.getByRole('button', { name: 'Áp dụng bộ lọc' }));
    expect(onClose).toHaveBeenCalledTimes(1);
    expect(container).toBeInTheDocument();
  });

  it('closes via the sheet header X button', () => {
    const onClose = vi.fn();
    render(
      <RoomFilterPanel rooms={rooms} filters={baseFilters} onFiltersChange={vi.fn()} isMobile onClose={onClose} />
    );
    fireEvent.click(screen.getByRole('button', { name: 'Đóng bộ lọc' }));
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('has no axe violations', async () => {
    const { container } = render(
      <RoomFilterPanel rooms={rooms} filters={baseFilters} onFiltersChange={vi.fn()} isMobile onClose={vi.fn()} />
    );
    await expectNoAxeViolations(container);
  });
});
