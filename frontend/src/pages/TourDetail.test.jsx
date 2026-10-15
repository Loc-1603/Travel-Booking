import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { expectNoAxeViolations } from '../test/axe';
import TourDetail from './TourDetail';

const { apiGet, apiPost } = vi.hoisted(() => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
}));

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key) => key, i18n: { language: 'en' } }),
}));

vi.mock('../contexts/useAuth', () => ({
  useAuth: () => ({ user: null }),
}));

vi.mock('../lib/api', () => ({
  api: {
    get: (...args) => apiGet(...args),
    post: (...args) => apiPost(...args),
  },
}));

vi.mock('../components/TourReviewList', () => ({
  TourReviewList: () => <div data-testid="review-list" />,
}));
vi.mock('../components/TourReviewForm', () => ({
  TourReviewForm: () => <div data-testid="review-form" />,
}));
vi.mock('../components/TourChatBox', () => ({
  TourChatBox: () => <div data-testid="chat-stub" />,
}));

const tour = {
  id: 7,
  uuid: 't-1',
  title: 'Hue Tour',
  description: 'Old capital ride',
  base_fixed: 100000,
  base_price_hourly: 200000,
  base_price_daily: 1500000,
  transport_fee: 50000,
  meeting_point: 'Gate',
  images: [],
  province: { name: 'Hue', slug: 'hue', attractions: [] },
  provider: { uuid: 'p-1', business_name: 'Guide A' },
  average_rating: 0,
  review_count: 0,
};

const breakdown = {
  subtotal: 300000,
  add_on_amount: 0,
  discount: 0,
  tax: 30000,
  total: 330000,
  currency: 'VND',
};

function renderDetail() {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false, refetchOnWindowFocus: false } },
  });
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={['/tours/t-1']}>
        <Routes>
          <Route path="/tours/:uuid" element={<TourDetail />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>
  );
}

beforeEach(() => {
  apiGet.mockReset();
  apiPost.mockReset();
  apiGet.mockImplementation((url, { params } = {}) => {
    if (url.includes('/availability')) {
      const from = params?.from ?? '2026-10-15';
      return Promise.resolve({
        data: {
          success: true,
          data: [
            { id: 9, date: from, start_time: '09:00', end_time: '12:00', status: 'available', price_override: null },
          ],
        },
      });
    }
    return Promise.resolve({ data: { success: true, data: tour } });
  });
  apiPost.mockResolvedValue({ data: { success: true, data: breakdown } });
});

describe('TourDetail rates (FE-4.1)', () => {
  it('shows a From total with per-mode rows in the correct units', async () => {
    const { container } = renderDetail();
    await screen.findByText('Hue Tour');
    expect(screen.getByTestId('tour-from-price')).toHaveTextContent('300.000');
    expect(screen.getByText('tours.detail.rateHour')).toBeInTheDocument();
    expect(screen.getByText('tours.detail.rateDay')).toBeInTheDocument();
    expect(container.textContent).not.toMatch(/from \/ hour/);
  });
});

describe('TourDetail gallery + sticky CTA (FE-5.1/5.2)', () => {
  it('renders the shared TourGallery and a mobile sticky CTA with the from-price', async () => {
    renderDetail();
    await screen.findByText('Hue Tour');
    expect(screen.getByTestId('tour-gallery')).toBeInTheDocument();
    const sticky = screen.getByTestId('tour-sticky-cta');
    expect(sticky).toHaveTextContent('tours.detail.fromPrice');
    expect(sticky).toHaveTextContent('300.000');
  });

  it('has no axe violations', async () => {
    const { container } = renderDetail();
    await screen.findByText('Hue Tour');
    await expectNoAxeViolations(container);
  });
});

describe('TourDetail guest estimate (FE-4.4)', () => {
  it('shows an estimated price for guests and asks to log in only to book', async () => {
    renderDetail();
    fireEvent.click(await screen.findByText('09:00 – 12:00'));
    await screen.findByTestId('tour-estimate');
    // Preview resolves async — wait for the computed total.
    await screen.findByText(/330\.000/);
    expect(screen.getByText('tours.detail.estimateTitle')).toBeInTheDocument();
    expect(screen.getByText('tours.detail.estimateGuestHint')).toBeInTheDocument();
    // Booking still requires login — preview itself did not.
    // (sidebar CTA + mobile sticky CTA share the same label).
    expect(screen.getAllByText('tours.detail.loginToBook')).toHaveLength(2);
    expect(apiPost).toHaveBeenCalledWith(
      '/tour-bookings/preview',
      expect.objectContaining({ pricing_mode: 'hour', duration_value: 1 })
    );
  });
});
