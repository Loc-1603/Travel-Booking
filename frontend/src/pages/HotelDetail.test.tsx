import '@testing-library/jest-dom/vitest';



import { describe, it, expect, vi, beforeAll, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor, within, cleanup } from '@testing-library/react';
import { MemoryRouter, Routes, Route, useLocation } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import HotelDetail from './HotelDetail';
import { expectNoAxeViolations } from '../test/axe';

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
  'hotelDetail.gallery.prev': 'Ảnh trước',
  'hotelDetail.gallery.next': 'Ảnh sau',
  'common.close': 'Đóng',
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
  'hotels.reviewCount': '{{count}} đánh giá',
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

// Same as renderPage plus a probe exposing the router URL (FE-10: URL sync tests)
function LocationProbe() {
  const { search } = useLocation();
  return <div data-testid="location-probe">{search}</div>;
}

function renderPageWithLocation(route: string) {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={qc}>
      <MemoryRouter initialEntries={[route]}>
        {/* Probe lives outside Routes so it survives SPA navigation (FX-2: navigate() to /book) */}
        <LocationProbe />
        <Routes>
          <Route path="/hotels/:id" element={<HotelDetail />} />
          <Route path="/book" element={<div data-testid="book-page">book</div>} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>
  );
}

function probeSearch() {
  return screen.getByTestId('location-probe').textContent ?? '';
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

  it('renders the room filter bar without an inline sidebar on wide viewports', async () => {
    await openRoomsTab();
    // FE-6: filters moved fully into the drawer — no inline sidebar on desktop
    expect(screen.queryByRole('combobox', { name: 'Sức chứa tối thiểu' })).toBeNull();
    // redesign FE-3: filter bar is visible on all viewports in the rooms tab
    expect(screen.getByLabelText('Nhận phòng')).toBeInTheDocument();
    expect(screen.getByLabelText('Trả phòng')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Tìm kiếm' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Bộ lọc' })).toBeInTheDocument();
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
  // FE-6: desktop filters live in the right drawer — open it before touching controls
  async function openDesktopDrawer() {
    fireEvent.click(screen.getByRole('button', { name: 'Bộ lọc' }));
    await screen.findByRole('dialog', { name: 'Bộ lọc phòng' });
  }

  it('filters by capacity from the desktop drawer', async () => {
    await openRoomsTab();
    await openDesktopDrawer();
    const capacity = await screen.findByRole('combobox', { name: 'Sức chứa tối thiểu' });
    fireEvent.change(capacity, { target: { value: '4' } });
    await waitFor(() => expect(screen.getAllByRole('article')).toHaveLength(2), { timeout: 2000 });
    expect(screen.queryByRole('heading', { name: 'Standard' })).toBeNull();
    expect(screen.getByRole('heading', { name: 'Deluxe' })).toBeInTheDocument();
  });

  it('filters by minimum price via the slider number inputs', async () => {
    await openRoomsTab();
    await openDesktopDrawer();
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
    await openDesktopDrawer();
    const capacity = await screen.findByRole('combobox', { name: 'Sức chứa tối thiểu' });
    fireEvent.change(capacity, { target: { value: '4' } });
    // debounce is 300ms — before it elapses the old list is still shown
    expect(screen.getAllByRole('article')).toHaveLength(4);
    await waitFor(() => expect(screen.getAllByRole('article')).toHaveLength(2), { timeout: 2000 });
  });

  it('shows the empty state and restores the list via "Xóa bộ lọc"', async () => {
    await openRoomsTab();
    await openDesktopDrawer();
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
  it('opens the modal from a room card gallery and navigates to booking on select (no widget)', async () => {
    // FX-2: selecting navigates via react-router (no window.location hard-reload)
    renderPageWithLocation('/hotels/1?check_in=2026-10-01&check_out=2026-10-03');
    await screen.findByText('Khách sạn Biển Xanh Hà Nội');
    fireEvent.click(screen.getByRole('button', { name: 'Phòng' }));

    // FX-5: single-photo carousel — the second photo appears after the next arrow.
    // Scope to the Deluxe card: the hero gallery uses the same translated labels.
    const deluxeCard = screen.getByRole('heading', { name: 'Phòng Deluxe Cao Cấp' }).closest('article') as HTMLElement;
    fireEvent.click(within(deluxeCard).getByRole('button', { name: 'Ảnh sau' }));
    fireEvent.click(await within(deluxeCard).findByAltText('Ảnh phụ Deluxe'));
    const dialog = await screen.findByRole('dialog');
    expect(within(dialog).getByText('Phòng Deluxe Cao Cấp')).toBeInTheDocument();
    // modal footer shows the 2-night total for 1 room
    expect(within(dialog).getByText('Tổng 2 đêm × 1 phòng')).toBeInTheDocument();

    fireEvent.click(within(dialog).getByRole('button', { name: 'Chọn phòng này' }));
    await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());

    // no booking widget anymore — selecting goes straight to the booking page via SPA navigation
    await screen.findByTestId('book-page');
    expect(probeSearch()).toContain('hotel_id=1');
    expect(probeSearch()).toContain('room_id=102');
    expect(probeSearch()).toContain('check_in=2026-10-01');
    expect(probeSearch()).toContain('check_out=2026-10-03');
    expect(probeSearch()).toContain('quantity=1');
  });

  it('closes the modal with Escape without navigating', async () => {
    renderPageWithLocation('/hotels/1');
    await screen.findByText('Khách sạn Biển Xanh Hà Nội');
    fireEvent.click(screen.getByRole('button', { name: 'Phòng' }));
    fireEvent.click(await screen.findByAltText('Ảnh phòng Standard'));
    expect(await screen.findByRole('dialog')).toBeInTheDocument();
    fireEvent.keyDown(document, { key: 'Escape' });
    await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
    // modal closed without selecting → still on the hotel page, no booking navigation
    expect(screen.queryByTestId('book-page')).toBeNull();
  });
});

describe('HotelDetail — sticky tabs (redesign FE-2)', () => {
  it('renders tabs in a sticky container offset by the header height', async () => {
    renderPage('/hotels/1');
    await screen.findByText('Khách sạn Biển Xanh Hà Nội');
    const nav = screen.getByRole('navigation', { name: 'Các phần chi tiết khách sạn' });
    const bar = nav.parentElement as HTMLElement;
    expect(bar.className).toMatch('sticky');
    expect(bar.className).toMatch('top-[var(--header-height,0px)]');
    const activeTab = screen.getByRole('button', { name: 'Tổng quan' });
    expect(activeTab.className).toMatch('font-semibold');
    expect(activeTab.className).toMatch('text-[#b8860b]');
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

describe('HotelDetail — room filter bar + availability query (redesign FE-3/FE-4)', () => {
  it('requests the hotel with check_in/check_out/adults/rooms params', async () => {
    await openRoomsTab();
    const hotelCall = apiGet.mock.calls.find((c) => c[0] === '/hotels/1');
    expect(hotelCall).toBeDefined();
    const params = (hotelCall as unknown[])[1] as { params: Record<string, unknown> };
    expect(params.params.check_in).toMatch(/^\d{4}-\d{2}-\d{2}$/);
    expect(params.params.check_out).toMatch(/^\d{4}-\d{2}-\d{2}$/);
    expect(params.params.adults).toBe(1);
    expect(params.params.rooms).toBe(1);
  });

  it('initialises the filter bar dates from URL params', async () => {
    renderPage('/hotels/1?check_in=2026-10-01&check_out=2026-10-03');
    await screen.findByText('Khách sạn Biển Xanh Hà Nội');
    fireEvent.click(screen.getByRole('button', { name: 'Phòng' }));
    expect(await screen.findByLabelText('Nhận phòng')).toHaveValue('2026-10-01');
    expect(screen.getByLabelText('Trả phòng')).toHaveValue('2026-10-03');
  });

  it('filters rooms by adults capacity from the guest selector after Tìm kiếm (FX-3)', async () => {
    await openRoomsTab();
    expect(await screen.findByLabelText('Nhận phòng')).toBeInTheDocument();
    expect(screen.getAllByRole('article')).toHaveLength(4);

    fireEvent.click(screen.getByText('1 người lớn · 1 phòng'));
    const increase = screen.getByRole('button', { name: 'Tăng người lớn' });
    fireEvent.click(increase);
    fireEvent.click(increase);
    fireEvent.click(increase);
    fireEvent.click(increase); // 1 → 5 adults
    expect(screen.getByText('5 người lớn · 1 phòng')).toBeInTheDocument();

    // FX-3: editing the draft does NOT refetch — the list is unchanged until search
    await new Promise((r) => setTimeout(r, 350));
    expect(screen.getAllByRole('article')).toHaveLength(4);

    // only the 6-guest Suite fits 5 adults once applied
    fireEvent.click(screen.getByRole('button', { name: 'Tìm kiếm' }));
    await waitFor(() => expect(screen.getAllByRole('article')).toHaveLength(1), { timeout: 2000 });
    expect(screen.getByText('Phòng Suite Rộng Thoáng')).toBeInTheDocument();
  });

  it('hides rooms with no availability left for the requested quantity (FE-8)', async () => {
    apiGet.mockImplementation((url: string) => {
      if (url === '/hotels/1') {
        return Promise.resolve({
          data: {
            success: true,
            data: {
              ...hotelFixture,
              rooms: hotelFixture.rooms.map((r) => ({
                ...r,
                min_available_rooms: r.id === 102 ? 0 : 5,
              })),
            },
          },
        });
      }
      if (url.startsWith('/reviews')) return Promise.resolve({ data: { data: [], meta: {} } });
      return Promise.resolve({ data: { success: true, data: null } });
    });
    renderPage('/hotels/1');
    await screen.findByText('Khách sạn Biển Xanh Hà Nội');
    fireEvent.click(screen.getByRole('button', { name: 'Phòng' }));
    // default search: valid dates + 2 adults + 1 room → Deluxe (0 free) drops out
    await waitFor(() => expect(screen.getAllByRole('article')).toHaveLength(3), { timeout: 2000 });
    expect(screen.queryByText('Phòng Deluxe Cao Cấp')).toBeNull();
    expect(screen.getByText('Phòng Standard Giá Rẻ')).toBeInTheDocument();
  });

  it('opens the desktop right drawer from the filter bar Bộ lọc button', async () => {    await openRoomsTab();
    await screen.findByLabelText('Nhận phòng');
    fireEvent.click(screen.getByRole('button', { name: 'Bộ lọc' }));
    const dialog = await screen.findByRole('dialog', { name: 'Bộ lọc phòng' });
    // drawer hosts the filter controls and slides in from the right
    expect(within(dialog).getByRole('combobox', { name: 'Sức chứa tối thiểu' })).toBeInTheDocument();
    expect(dialog.querySelector('aside')?.className).toMatch('animate-slide-in-right');
    fireEvent.click(screen.getByRole('button', { name: 'Đóng bộ lọc' }));
    // slide-out animation plays before unmount
    await waitFor(() => expect(screen.queryByRole('dialog', { name: 'Bộ lọc phòng' })).toBeNull(), { timeout: 2000 });
  });

  it('closes the desktop drawer via backdrop click and ESC', async () => {
    await openRoomsTab();
    await screen.findByLabelText('Nhận phòng');
    fireEvent.click(screen.getByRole('button', { name: 'Bộ lọc' }));
    await screen.findByRole('dialog', { name: 'Bộ lọc phòng' });
    fireEvent.click(screen.getByTestId('drawer-backdrop'));
    await waitFor(() => expect(screen.queryByRole('dialog', { name: 'Bộ lọc phòng' })).toBeNull(), { timeout: 2000 });

    fireEvent.click(screen.getByRole('button', { name: 'Bộ lọc' }));
    await screen.findByRole('dialog', { name: 'Bộ lọc phòng' });
    fireEvent.keyDown(document, { key: 'Escape' });
    await waitFor(() => expect(screen.queryByRole('dialog', { name: 'Bộ lọc phòng' })).toBeNull(), { timeout: 2000 });
  });
});

describe('HotelDetail — vertical full-width room list (FX-4)', () => {
  it('renders every room group as full-width rows, not a horizontal carousel', async () => {
    await openRoomsTab();
    const cards = await screen.findAllByRole('article');
    expect(cards.length).toBeGreaterThan(0);
    for (const card of cards) {
      expect(card.className).toMatch('w-full');
      expect(card.className).not.toMatch('w-[320px]');
      expect(card.className).not.toMatch('snap-start');
    }
    // no scroll-region wrappers left over from the old carousel
    expect(screen.queryByRole('region')).toBeNull();
  });
});

describe('HotelDetail — search applies via Tìm kiếm only (FX-3)', () => {
  it('does not fetch or sync the URL while editing; Tìm kiếm applies both', async () => {
    renderPageWithLocation('/hotels/1');
    await screen.findByText('Khách sạn Biển Xanh Hà Nội');
    fireEvent.click(screen.getByRole('button', { name: 'Phòng' }));
    const checkIn = await screen.findByLabelText('Nhận phòng');
    const initialSearch = probeSearch();
    expect(initialSearch).toContain(`check_in=${(checkIn as HTMLInputElement).value}`);
    const hotelCallsBefore = apiGet.mock.calls.filter((c) => c[0] === '/hotels/1').length;

    fireEvent.change(checkIn, { target: { value: '2026-11-05' } });
    // wait past the old 300ms debounce to prove nothing fires on edit
    await new Promise((r) => setTimeout(r, 400));
    expect(probeSearch()).not.toContain('check_in=2026-11-05');
    expect(apiGet.mock.calls.filter((c) => c[0] === '/hotels/1').length).toBe(hotelCallsBefore);

    // explicit search applies: URL updated + a new request with the new dates
    fireEvent.click(screen.getByRole('button', { name: 'Tìm kiếm' }));
    await waitFor(() => expect(probeSearch()).toContain('check_in=2026-11-05'), { timeout: 2000 });
    expect(probeSearch()).toContain('check_out=2026-11-06');
    await waitFor(
      () => expect(apiGet.mock.calls.filter((c) => c[0] === '/hotels/1').length).toBeGreaterThan(hotelCallsBefore),
      { timeout: 2000 }
    );

    // "refresh": fresh mount on the synced URL restores the same dates
    const savedSearch = probeSearch();
    cleanup();
    renderPage(`/hotels/1${savedSearch}`);
    await screen.findByText('Khách sạn Biển Xanh Hà Nội');
    fireEvent.click(screen.getByRole('button', { name: 'Phòng' }));
    expect(await screen.findByLabelText('Nhận phòng')).toHaveValue('2026-11-05');
    expect(screen.getByLabelText('Trả phòng')).toHaveValue('2026-11-06');
  });
});

describe('HotelDetail — reviews tab reads the API envelope (FX-1)', () => {
  const reviewsEnvelope = {
    success: true,
    data: {
      data: [
        { id: 1, rating: 5, comment: 'Tuyệt vời ông mặt trời' },
        { id: 2, rating: 4, comment: 'Tốt, sẽ quay lại' },
      ],
      meta: { current_page: 1, last_page: 1, per_page: 10, total: 2 },
    },
  };

  function mockWithReviews() {
    apiGet.mockImplementation((url: string) => {
      if (url === '/hotels/1') return Promise.resolve({ data: { success: true, data: hotelFixture } });
      if (url.startsWith('/reviews')) return Promise.resolve({ data: reviewsEnvelope });
      return Promise.resolve({ data: { success: true, data: null } });
    });
  }

  it('lists reviews matching the header count instead of an empty state', async () => {
    mockWithReviews();
    renderPage('/hotels/1');
    await screen.findByText('Khách sạn Biển Xanh Hà Nội');
    // header shows the approved-reviews count from the hotel payload
    expect(screen.getByText('10 đánh giá')).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Đánh giá' }));
    // the tab parses data.data.data — the old wrapper-object bug left it empty
    expect(await screen.findByText('Tuyệt vời ông mặt trời')).toBeInTheDocument();
    expect(screen.getByText('Tốt, sẽ quay lại')).toBeInTheDocument();
  });

  it('shows the empty state only when the envelope really has no reviews', async () => {
    apiGet.mockImplementation((url: string) => {
      if (url === '/hotels/1') {
        return Promise.resolve({ data: { success: true, data: { ...hotelFixture, review_count: 0 } } });
      }
      if (url.startsWith('/reviews')) {
        return Promise.resolve({ data: { success: true, data: { data: [], meta: {} } } });
      }
      return Promise.resolve({ data: { success: true, data: null } });
    });
    renderPage('/hotels/1');
    await screen.findByText('Khách sạn Biển Xanh Hà Nội');
    fireEvent.click(screen.getByRole('button', { name: 'Đánh giá' }));
    expect(await screen.findByText('hotelDetail.reviews.noReviews')).toBeInTheDocument();
  });
});

describe('HotelDetail — rooms tab accessibility (redesign FE-10)', () => {
  it('has no axe violations on the rooms tab with filter bar and carousel', async () => {
    const { container } = renderPage('/hotels/1');
    await screen.findByText('Khách sạn Biển Xanh Hà Nội');
    fireEvent.click(screen.getByRole('button', { name: 'Phòng' }));
    await screen.findAllByRole('article');
    await expectNoAxeViolations(container);
  });
});
