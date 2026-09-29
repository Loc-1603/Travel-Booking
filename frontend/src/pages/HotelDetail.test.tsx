import { describe, it, expect, vi, beforeAll, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor, within } from '@testing-library/react';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import HotelDetail from './HotelDetail';

const { apiGet, mockUser } = vi.hoisted(() => ({
  apiGet: vi.fn(),
  mockUser: { id: 1, name: 'Customer', email: 'customer@test.com', role: 'customer' },
}));

// Module-scope viewport flag read by the matchMedia stub (flip before renderPage)
let mediaMatches = false;

const translations: Record<string, string> = {
  'hotelDetail.tabs.overview': 'Tổng quan',
  'hotelDetail.tabs.rooms': 'Phòng',
  'hotelDetail.tabs.reviews': 'Đánh giá',
  'hotelDetail.tabs.location': 'Vị trí',
  'hotelDetail.tabs.ariaLabel': 'Các phần chi tiết khách sạn',
  'hotelDetail.rooms.noRooms': 'Không có phòng trống.',
  'hotelDetail.rooms.otherType': 'Khác',
  'hotelDetail.rooms.roomsCount': '{{count}} phòng',
  'hotelDetail.rooms.filters': 'Bộ lọc',
  'hotelDetail.rooms.sortBy': 'Sắp xếp phòng',
  'hotelDetail.rooms.sortRecommended': 'Đề xuất',
  'hotelDetail.rooms.sortPriceAsc': 'Giá: thấp → cao',
  'hotelDetail.rooms.sortPriceDesc': 'Giá: cao → thấp',
  'hotelDetail.rooms.sortCapacity': 'Sức chứa: nhiều nhất',
  'hotelDetail.rooms.noResults': 'Không có phòng phù hợp với bộ lọc',
  'hotelDetail.rooms.noResultsHint': 'Thử điều chỉnh hoặc xóa bộ lọc để xem thêm phòng.',
  'hotelDetail.rooms.clearFilters': 'Xóa bộ lọc',
  'hotelDetail.booking.title': 'Đặt phòng',
  'hotelDetail.booking.checkAvailability': 'Kiểm tra tính sẵn sàng',
  'hotelDetail.booking.selectRoom': 'Chọn phòng',
  'hotelDetail.booking.quantity': 'Số phòng',
  'hotelDetail.booking.rooms': '{{count}} phòng',
  'hotelDetail.booking.subtotal': 'Tạm tính',
  'hotelDetail.booking.tax': 'Thuế ({{rate}}%)',
  'hotelDetail.booking.total': 'Tổng cộng',
  'hotelDetail.booking.bookNow': 'Đặt ngay',
  'hotelDetail.booking.loginRequired': 'Bạn cần đăng nhập để đặt phòng.',
  'hotelDetail.overview.amenities': 'Tiện nghi',
  'hotelDetail.overview.checkInOut': 'Nhận/Trả phòng',
  'hotelDetail.overview.checkIn': 'Nhận phòng',
  'hotelDetail.overview.checkOut': 'Trả phòng',
  'common.guests': 'Khách',
  'common.guest': 'Khách',
  'common.date.checkIn': 'Ngày nhận phòng',
  'common.date.checkOut': 'Ngày trả phòng',
  'common.reviews': 'đánh giá',
  'common.login': 'Đăng nhập',
  'common.register': 'Đăng ký',
  'hotels.wishlist.add': 'Lưu vào danh sách yêu thích',
  'hotels.wishlist.remove': 'Bỏ khỏi danh sách yêu thích',
  'hotels.wishlist.loginToSave': 'Đăng nhập để lưu',
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

vi.mock('../lib/api', () => ({ api: { get: apiGet } }));

vi.mock('../contexts/useAuth', () => ({ useAuth: () => ({ user: mockUser }) }));

vi.mock('../hooks/useWishlist', () => ({
  useWishlist: () => ({
    isInWishlist: () => false,
    addToWishlist: vi.fn(),
    removeFromWishlist: vi.fn(),
    addPending: false,
    removePending: false,
  }),
}));

vi.mock('../components/DatePicker', () => ({
  DatePicker: ({ id, value, onChange }: { id?: string; value?: string; onChange?: (v: string) => void }) => (
    <input
      id={id}
      type="date"
      value={value ?? ''}
      onChange={(e: { target: { value: string } }) => onChange?.(e.target.value)}
    />
  ),
}));

const hotelFixture = {
  id: 1,
  name: 'Khách sạn Biển Xanh Hà Nội',
  city: 'Hà Nội',
  country: 'Việt Nam',
  average_rating: 9.2,
  review_count: 10,
  description: 'Khách sạn sang trọng ngay cạnh Hồ Gươm.',
  check_in: '14:00',
  check_out: '12:00',
  tax_rate: 0.1,
  tax_inclusive: false,
  amenities: [],
  images: [
    { id: 1, url: '/img/hero-1.jpg', alt_text: 'Ảnh khách sạn 1' },
    { id: 2, url: '/img/hero-2.jpg', alt_text: 'Ảnh khách sạn 2' },
  ],
  rooms: [
    {
      id: 101,
      name: 'Phòng Standard Giá Rẻ',
      capacity: 2,
      base_price: 500000,
      size: 25,
      bed_type: 'double',
      view_type: 'city',
      description: 'Phòng tiêu chuẩn giản tiện.',
      cancellation_policy_summary: 'Hủy miễn phí trước 24 giờ.',
      room_type: { id: 1, name: 'Standard' },
      amenities: [{ id: 1, slug: 'wifi', name: 'Wi-Fi' }],
      images: [{ id: 11, url: '/img/r101.jpg', alt_text: 'Ảnh phòng Standard' }],
      total_rooms: 5,
      max_quantity_per_booking: 2,
    },
    {
      id: 102,
      name: 'Phòng Deluxe Cao Cấp',
      capacity: 4,
      base_price: 1500000,
      size: 40,
      bed_type: 'king',
      view_type: 'sea',
      description: 'Phòng deluxe cao cấp.',
      cancellation_policy_summary: 'Hủy miễn phí trước 48 giờ.',
      room_type: { id: 2, name: 'Deluxe' },
      amenities: [{ id: 1, slug: 'wifi', name: 'Wi-Fi' }, { id: 2, slug: 'bathtub', name: 'Bồn tắm' }],
      images: [
        { id: 21, url: '/img/r102-main.jpg', alt_text: 'Ảnh chính Deluxe' },
        { id: 22, url: '/img/r102-extra.jpg', alt_text: 'Ảnh phụ Deluxe' },
      ],
      total_rooms: 3,
      max_quantity_per_booking: 3,
    },
    {
      id: 103,
      name: 'Phòng Suite Rộng Thoáng',
      capacity: 6,
      base_price: 900000,
      size: 60,
      bed_type: 'queen',
      view_type: 'sea',
      description: 'Suite rộng thoáng.',
      cancellation_policy_summary: null,
      room_type: { id: 2, name: 'Deluxe' },
      amenities: [],
      images: [{ id: 31, url: '/img/r103.jpg', alt_text: 'Ảnh phòng Suite' }],
      total_rooms: 2,
      max_quantity_per_booking: 2,
    },
    {
      id: 104,
      name: 'Phòng Không Loại',
      capacity: 2,
      base_price: 700000,
      size: 20,
      bed_type: 'single',
      view_type: 'city',
      description: null,
      cancellation_policy_summary: null,
      room_type: null,
      amenities: [],
      images: [{ id: 41, url: '/img/r104.jpg', alt_text: 'Ảnh phòng lạ' }],
      total_rooms: 1,
      max_quantity_per_booking: 1,
    },
  ],
};

beforeAll(() => {
  window.matchMedia = ((query: string) => ({
    matches: mediaMatches,
    media: query,
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
    addListener: vi.fn(),
    removeListener: vi.fn(),
    dispatchEvent: vi.fn(),
    onchange: null,
  })) as unknown as typeof window.matchMedia;
});

beforeEach(() => {
  mediaMatches = false;
  apiGet.mockReset();
  apiGet.mockImplementation((url: string) => {
    if (url === '/hotels/1') return Promise.resolve({ data: { success: true, data: hotelFixture } });
    if (url.startsWith('/reviews')) return Promise.resolve({ data: { data: [], meta: {} } });
    return Promise.resolve({ data: { success: true, data: null } });
  });
});

function renderPage(route: string) {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={qc}>
      <MemoryRouter initialEntries={[route]}>
        {/* useParams() needs a matching route — rendering <HotelDetail/> bare gives no :id */}
        <Routes>
          <Route path="/hotels/:id" element={<HotelDetail />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>
  );
}

async function openRoomsTab() {
  renderPage('/hotels/1');
  await screen.findByText('Khách sạn Biển Xanh Hà Nội');
  fireEvent.click(screen.getByRole('button', { name: 'Phòng' }));
}

function cardNames() {
  return screen.getAllByRole('article').map((a) => a.querySelector('h3')?.textContent);
}

describe('HotelDetail — Phòng tab rendering (8.2)', () => {
  it('groups rooms by room type with per-group and total counts', async () => {
    await openRoomsTab();
    expect(screen.getByRole('heading', { name: 'Standard' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Deluxe' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Khác' })).toBeInTheDocument();
    expect(screen.getByText('4 phòng')).toBeInTheDocument();
    expect(screen.getAllByText('1 phòng')).toHaveLength(2); // Standard + Khác
    expect(screen.getByText('2 phòng')).toBeInTheDocument(); // Deluxe
    for (const name of hotelFixture.rooms.map((r) => r.name)) {
      expect(screen.getByText(name)).toBeInTheDocument();
    }
  });

  it('renders the desktop filter sidebar on wide viewports', async () => {
    await openRoomsTab();
    expect(await screen.findByRole('combobox', { name: 'Sức chứa tối thiểu' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Bộ lọc' })).toBeNull();
  });

  it('shows the mobile filter button instead of the sidebar on narrow viewports', async () => {
    mediaMatches = true;
    await openRoomsTab();
    expect(screen.getByRole('button', { name: 'Bộ lọc' })).toBeInTheDocument();
    expect(screen.queryByRole('combobox', { name: 'Sức chứa tối thiểu' })).toBeNull();
  });
});

describe('HotelDetail — sorting (8.2)', () => {
  it('sorts rooms by price ascending across groups', async () => {
    await openRoomsTab();
    fireEvent.change(screen.getByLabelText('Sắp xếp phòng'), { target: { value: 'price_asc' } });
    expect(cardNames()).toEqual([
      'Phòng Standard Giá Rẻ', // 500k
      'Phòng Không Loại', // 700k
      'Phòng Suite Rộng Thoáng', // 900k
      'Phòng Deluxe Cao Cấp', // 1500k
    ]);
  });

  it('sorts rooms by capacity descending', async () => {
    await openRoomsTab();
    fireEvent.change(screen.getByLabelText('Sắp xếp phòng'), { target: { value: 'capacity' } });
    expect(cardNames()).toEqual([
      'Phòng Suite Rộng Thoáng', // 6
      'Phòng Deluxe Cao Cấp', // 4
      'Phòng Standard Giá Rẻ', // 2
      'Phòng Không Loại', // 2
    ]);
  });
});

describe('HotelDetail — filtering with 300ms debounce (8.2)', () => {
  it('filters by capacity from the desktop sidebar', async () => {
    await openRoomsTab();
    const capacity = await screen.findByRole('combobox', { name: 'Sức chứa tối thiểu' });
    fireEvent.change(capacity, { target: { value: '4' } });
    await waitFor(() => expect(screen.getAllByRole('article')).toHaveLength(2), { timeout: 2000 });
    expect(screen.queryByRole('heading', { name: 'Standard' })).toBeNull();
    expect(screen.getByRole('heading', { name: 'Deluxe' })).toBeInTheDocument();
  });

  it('filters by minimum price via the slider number inputs', async () => {
    await openRoomsTab();
    const minInput = await screen.findByLabelText('Giá tối thiểu');
    fireEvent.focus(minInput);
    fireEvent.change(minInput, { target: { value: '800000' } });
    fireEvent.blur(minInput);
    // 500k / 700k drop out; 900k (Suite) and 1.5M (Deluxe) stay
    await waitFor(() => expect(screen.getAllByRole('article')).toHaveLength(2), { timeout: 2000 });
    expect(screen.getByText('Phòng Suite Rộng Thoáng')).toBeInTheDocument();
    expect(screen.getByText('Phòng Deluxe Cao Cấp')).toBeInTheDocument();
  });

  it('debounces: rooms stay visible right after changing the filter', async () => {
    await openRoomsTab();
    const capacity = await screen.findByRole('combobox', { name: 'Sức chứa tối thiểu' });
    fireEvent.change(capacity, { target: { value: '4' } });
    // debounce is 300ms — before it elapses the old list is still shown
    expect(screen.getAllByRole('article')).toHaveLength(4);
    await waitFor(() => expect(screen.getAllByRole('article')).toHaveLength(2), { timeout: 2000 });
  });

  it('shows the empty state and restores the list via "Xóa bộ lọc"', async () => {
    await openRoomsTab();
    const capacity = await screen.findByRole('combobox', { name: 'Sức chứa tối thiểu' });
    fireEvent.change(capacity, { target: { value: '10' } });
    expect(await screen.findByText('Không có phòng phù hợp với bộ lọc')).toBeInTheDocument();
    expect(screen.queryByRole('article')).toBeNull();

    fireEvent.click(screen.getByRole('button', { name: 'Xóa bộ lọc' }));
    await waitFor(() => expect(screen.getAllByRole('article')).toHaveLength(4), { timeout: 2000 });
  });

  it('opens the mobile filter bottom sheet and closes via the apply button', async () => {
    mediaMatches = true;
    await openRoomsTab();
    fireEvent.click(screen.getByRole('button', { name: 'Bộ lọc' }));
    expect(await screen.findByText('Bộ lọc phòng')).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Áp dụng bộ lọc' }));
    await waitFor(() => expect(screen.queryByText('Bộ lọc phòng')).toBeNull());
  });
});

describe('HotelDetail — RoomDetailModal wiring (8.2)', () => {
  it('opens the modal from a room card gallery, selects the room and updates the widget', async () => {
    renderPage('/hotels/1?check_in=2026-10-01&check_out=2026-10-03');
    await screen.findByText('Khách sạn Biển Xanh Hà Nội');
    fireEvent.click(screen.getByRole('button', { name: 'Phòng' }));

    // the second (non-featured) gallery image only exists in the rooms tab card
    fireEvent.click(await screen.findByAltText('Ảnh phụ Deluxe'));
    const dialog = await screen.findByRole('dialog');
    expect(within(dialog).getByText('Phòng Deluxe Cao Cấp')).toBeInTheDocument();
    // modal footer shows the 2-night total for 1 room
    expect(within(dialog).getByText('Tổng 2 đêm × 1 phòng')).toBeInTheDocument();

    fireEvent.click(within(dialog).getByRole('button', { name: 'Chọn phòng này' }));
    await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());

    // widget now shows the quantity select and the full price summary
    expect(screen.getByLabelText('Số phòng')).toHaveValue('1');
    const summaryBlock = screen.getByText('Tạm tính').closest('div')?.parentElement as HTMLElement;
    expect(within(summaryBlock).getByText(/3\.000\.000/)).toBeInTheDocument(); // 1.5M × 2 nights
    expect(within(summaryBlock).getByText('Thuế (10%)')).toBeInTheDocument();
    expect(within(summaryBlock).getByText(/3\.300\.000/)).toBeInTheDocument(); // + 10% tax
  });

  it('closes the modal with Escape without selecting', async () => {
    await openRoomsTab();
    fireEvent.click(await screen.findByAltText('Ảnh phòng Standard'));
    expect(await screen.findByRole('dialog')).toBeInTheDocument();
    fireEvent.keyDown(document, { key: 'Escape' });
    await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
    // no room got selected → widget has no quantity select
    expect(screen.queryByLabelText('Số phòng')).toBeNull();
  });
});

describe('HotelDetail — empty rooms list', () => {
  it('shows the no-rooms message when the hotel has no rooms', async () => {
    apiGet.mockImplementation((url: string) => {
      if (url === '/hotels/1') {
        return Promise.resolve({ data: { success: true, data: { ...hotelFixture, rooms: [] } } });
      }
      if (url.startsWith('/reviews')) return Promise.resolve({ data: { data: [], meta: {} } });
      return Promise.resolve({ data: { success: true, data: null } });
    });
    await openRoomsTab();
    expect(screen.getByText('Không có phòng trống.')).toBeInTheDocument();
  });
});
