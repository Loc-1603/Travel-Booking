import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import MyTourBookings from './MyTourBookings';

const { apiGet, apiPost } = vi.hoisted(() => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
}));

const { toastMock } = vi.hoisted(() => ({
  toastMock: vi.fn(),
}));

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key) => key }),
}));

vi.mock('../contexts/useAuth', () => ({
  useAuth: () => ({ user: { id: 1, email: 'c@test.com' } }),
}));

vi.mock('../contexts/useToast', () => ({
  useToast: () => ({ toast: toastMock, dismiss: vi.fn() }),
}));

vi.mock('../lib/api', () => ({
  api: {
    get: (...args) => apiGet(...args),
    post: (...args) => apiPost(...args),
  },
}));

vi.mock('../components/TourChatBox', () => ({
  TourChatBox: () => <div data-testid="chat-stub" />,
}));

const booking = {
  uuid: 'b-1',
  status: 'pending_payment',
  total_price: 540000,
  currency: 'VND',
  pricing_mode: 'hour',
  duration_value: 2,
  tour: { title: 'Hue Tour' },
  slot: { date: '2026-10-15', start_time: '09:00', end_time: '13:00' },
  can_open_dispute: false,
};

function listResponse(items = [booking]) {
  return {
    data: {
      success: true,
      data: {
        data: items,
        meta: { current_page: 1, last_page: 1, per_page: 10, total: items.length },
      },
    },
  };
}

function renderPage(listImpl = () => Promise.resolve(listResponse())) {
  apiGet.mockImplementation((url) => {
    if (url === '/tour-bookings') return listImpl();
    return Promise.reject(new Error(`unexpected GET ${url}`));
  });
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false, refetchOnWindowFocus: false } },
  });
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={['/tour-bookings']}>
        <Routes>
          <Route path="/tour-bookings" element={<MyTourBookings />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>
  );
}

beforeEach(() => {
  apiGet.mockReset();
  apiPost.mockReset();
  toastMock.mockReset();
});

describe('MyTourBookings (FE-3.4)', () => {
  it('shows skeleton placeholders while loading', () => {
    renderPage(() => new Promise(() => {}));
    expect(screen.getAllByTestId('booking-skeleton')).toHaveLength(3);
    expect(screen.getByText('tours.bookings.loading')).toBeInTheDocument();
  });

  it('opens a confirm dialog (no window.confirm) and toasts on cancel', async () => {
    const confirmSpy = vi.spyOn(window, 'confirm');
    apiPost.mockResolvedValue({ data: { success: true } });
    renderPage();
    await screen.findByText('Hue Tour');
    fireEvent.click(screen.getByText('tours.bookings.cancel'));
    expect(screen.getByText('tours.bookings.cancelTitle')).toBeInTheDocument();
    expect(confirmSpy).not.toHaveBeenCalled();
    fireEvent.click(screen.getByText('common.confirm'));
    await waitFor(() => {
      expect(apiPost).toHaveBeenCalledWith('/tour-bookings/b-1/cancel');
    });
    await waitFor(() => {
      expect(toastMock).toHaveBeenCalledWith('tours.bookings.cancelSuccess', { type: 'success' });
    });
    confirmSpy.mockRestore();
  });

  it('toasts an error when the invoice download fails', async () => {
    window.open = vi.fn();
    renderPage();
    await screen.findByText('Hue Tour');
    fireEvent.click(screen.getByText('tours.bookings.invoice'));
    await waitFor(() => {
      expect(toastMock).toHaveBeenCalledWith('tours.bookings.invoiceError', { type: 'error' });
    });
    expect(window.open).not.toHaveBeenCalled();
  });

  it('validates the dispute form and toasts on success via the shared modal', async () => {
    apiPost.mockResolvedValue({ data: { success: true } });
    renderPage(() =>
      Promise.resolve(listResponse([{ ...booking, status: 'completed', can_open_dispute: true }]))
    );
    await screen.findByText('Hue Tour');
    fireEvent.click(screen.getByText('tours.bookings.report'));
    expect(screen.getByRole('dialog')).toBeInTheDocument();
    // Too short -> inline min-length error, no API call
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'short' } });
    fireEvent.click(screen.getByText('common.submit'));
    expect(screen.getByText('tours.bookings.disputeMin')).toBeInTheDocument();
    expect(apiPost).not.toHaveBeenCalled();
    // Valid notes -> dispute posted + success toast
    fireEvent.change(screen.getByRole('textbox'), {
      target: { value: 'The guide never showed up at the meeting point.' },
    });
    fireEvent.click(screen.getByText('common.submit'));
    await waitFor(() => {
      expect(apiPost).toHaveBeenCalledWith(
        '/tour-bookings/b-1/dispute',
        expect.objectContaining({ customer_notes: expect.any(String) })
      );
    });
    await waitFor(() => {
      expect(toastMock).toHaveBeenCalledWith('tours.bookings.disputeSuccess', { type: 'success' });
    });
  });
});
