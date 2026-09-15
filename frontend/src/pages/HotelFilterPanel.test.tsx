import { describe, it, expect, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { HotelFilterPanel } from '../pages/HotelList';

vi.mock('react-i18next', () => {
  return {
    useTranslation: () => ({
      t: (key: string, options?: { defaultValue?: string }) => {
        const translations: Record<string, string> = {
          'hotels.filters.minPrice': 'Tối thiểu',
          'hotels.filters.maxPrice': 'Tối đa',
          'hotels.filters.priceError': 'Giá tối thiểu không được lớn hơn giá tối đa',
          'hotels.filters.priceInvalid': 'Giá không hợp lệ',
          'hotels.filters.guests': 'Khách',
          'hotels.filters.roomsFitAtLeast': 'Phòng phù hợp từ',
          'hotels.filters.minimumRating': 'Đánh giá tối thiểu',
          'hotels.filters.basedOnGuestReviews': 'Dựa trên đánh giá khách',
          'hotels.filters.priceRange': 'Khoảng giá (mỗi đêm)',
          'hotels.filters.amenities': 'Tiện ích',
          'hotels.filters.sortBy': 'Sắp xếp theo',
          'hotels.filters.clearAll': 'Xóa bộ lọc',
          'hotels.reviewScore.excellent': 'Xuất sắc: 5',
          'hotels.reviewScore.veryGood': 'Rất tốt: 4+',
          'hotels.reviewScore.good': 'Tốt: 3+',
          'hotels.reviewScore.pleasant': 'Thoải mái: 2+',
          'common.any': 'Tất cả',
          'common.guest': 'Khách',
          'common.guests': 'Khách',
          'common.clearFilters': 'Xóa bộ lọc',
        };
        return translations[key] || options?.defaultValue || key;
      },
    }),
  };
});

const renderPanel = (props: Partial<{
  minPrice: string;
  maxPrice: string;
  priceError: string;
  onCommitPriceRange: any;
  minCapacity: string;
  minRating: string;
}> = {}) => {
  const defaultProps = {
    minCapacity: props.minCapacity ?? '',
    minRating: props.minRating ?? '',
    minPrice: props.minPrice ?? '',
    maxPrice: props.maxPrice ?? '',
    priceError: props.priceError ?? '',
    onCommitPriceRange: props.onCommitPriceRange ?? vi.fn(),
    amenities: [],
    selectedAmenities: [],
    onApply: vi.fn(),
    onClear: vi.fn(),
  };

  return render(<HotelFilterPanel {...defaultProps} />);
};

describe('HotelFilterPanel - UI Rendering', () => {
  it('renders price inputs with correct placeholders', () => {
    renderPanel({});

    expect(screen.getByPlaceholderText('Tối thiểu')).toBeInTheDocument();
    expect(screen.getByPlaceholderText('Tối đa')).toBeInTheDocument();
  });

  it('renders price inputs with correct step attribute', () => {
    renderPanel({});

    const minInput = screen.getByPlaceholderText('Tối thiểu');
    const maxInput = screen.getByPlaceholderText('Tối đa');

    expect(minInput).toHaveAttribute('step', '10000');
    expect(maxInput).toHaveAttribute('step', '10000');
  });

  it('renders price inputs without max attribute (free input)', () => {
    renderPanel({});

    const minInput = screen.getByPlaceholderText('Tối thiểu');
    const maxInput = screen.getByPlaceholderText('Tối đa');

    expect(minInput).not.toHaveAttribute('max');
    expect(maxInput).not.toHaveAttribute('max');
  });

  it('displays price summary when both min and max are set in URL', () => {
    renderPanel({ minPrice: '500000', maxPrice: '2000000' });

    const summary = screen.getByText(/500\.?000/);
    expect(summary).toBeInTheDocument();
  });

  it('displays max price label when only max is set in URL', () => {
    renderPanel({ minPrice: '', maxPrice: '1500000' });

    const summary = screen.getByText(/1\.?500\.?000/);
    expect(summary).toBeInTheDocument();
  });

  it('displays min price label when only min is set in URL', () => {
    renderPanel({ minPrice: '800000', maxPrice: '' });

    const summary = screen.getByText(/800\.?000/);
    expect(summary).toBeInTheDocument();
  });

  it('renders guest selector', () => {
    renderPanel({ minCapacity: '2' });

    const select = screen.getByRole('combobox');
    expect(select).toHaveValue('2');
  });

  it('renders rating filter options', () => {
    renderPanel({ minRating: '4' });

    expect(screen.getByText('Xuất sắc: 5')).toBeInTheDocument();
    expect(screen.getByText('Rất tốt: 4+')).toBeInTheDocument();
    expect(screen.getByText('Tốt: 3+')).toBeInTheDocument();
    expect(screen.getByText('Thoải mái: 2+')).toBeInTheDocument();
  });

  it('shows clear filters button', () => {
    renderPanel({});

    expect(screen.getByText('Xóa bộ lọc')).toBeInTheDocument();
  });
});