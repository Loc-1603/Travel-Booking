import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import TourCheckout from './TourCheckout';

const { apiGet, apiPost } = vi.hoisted(() => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
}));

const { toastMock } = vi.hoisted(() => ({
  toastMock: vi.fn(),
}));

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key, opts) => opts?.email ?? key }),
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

const futureExpires = new Date(Date.now() + 10 * 60 * 1000).toISOString();
const pastExpires = new Date(Date.now() - 60 * 1000).toISOString();

const pendingBooking = {
  uuid: 'b-1',
  status: 'pending_payment',
  total_price: 540000,
  currency: 'VND',
  expires_at: futureExpires,
  is_expired: false,
  can_pay: true,
  tour: { title: 'Hue Tour' },
  slot: { date: '2026-10-15', start_time: '09:00', end_time: '13:00' },
};

function renderCheckout(booking = pendingBooking, entry = '/tour-checkout/b-1') {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false, refetchOnWindowFocus: false } },
  });
  const initialEntry =
    typeof entry === 'string'
      ? { pathname: entry.split('?')[0], search: entry.includes('?') ? `?${entry.split('?')[1]}` : '', state: { booking } }
      : entry;
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={[initialEntry]}>
        <Routes>
          <Route path="/tour-checkout/:uuid" element={<TourCheckout />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>
  );
}

beforeEach(() => {
  apiGet.mockReset();
  apiPost.mockReset();
  toastMock.mockReset();
  apiPost.mockImplementation((url) => {
    if (url.endsWith('/cancel')) {
      return Promise.resolve({ data: { success: true } });
    }
    return Promise.resolve({ data: { success: true, data: { checkout_url: 'https://pay.example/1' } } });
  });
});

describe('TourCheckout stepper (Phase 2)', () => {
  it('renders the 3-step progress with payment as current', () => {
    const { container } = renderCheckout();
    const stepper = container.querySelector('ol[aria-label="tours.checkout.stepsAria"]');
    expect(stepper).toBeInTheDocument();
    expect(screen.getByText('tours.wizard.steps.slot')).toBeInTheDocument();
    expect(screen.getByText('tours.wizard.steps.details')).toBeInTheDocument();
    const paymentLi = screen.getByText('tours.wizard.steps.payment').closest('li');
    expect(paymentLi).toHaveAttribute('aria-current', 'step');
  });

  it('still shows the booking summary and pay button', () => {
    renderCheckout();
    expect(screen.getByText('Hue Tour')).toBeInTheDocument();
    expect(screen.getByText('tours.checkout.payNow')).toBeInTheDocument();
  });
});

describe('TourCheckout hold countdown + cancel (FE-3.1)', () => {
  it('shows a countdown banner while the hold is active', () => {
    renderCheckout();
    const banner = screen.getByTestId('hold-countdown');
    expect(banner).toBeInTheDocument();
    expect(banner.textContent).toMatch(/\d{2}:\d{2}/);
    expect(screen.getByText('tours.checkout.awaitingTitle')).toBeInTheDocument();
  });

  it('shows the expired state with no pay button once the hold lapses', () => {
    renderCheckout({ ...pendingBooking, expires_at: pastExpires, is_expired: true, can_pay: false });
    expect(screen.getByText('tours.checkout.expiredTitle')).toBeInTheDocument();
    expect(screen.queryByText('tours.checkout.payNow')).not.toBeInTheDocument();
    expect(screen.getByText('tours.checkout.backToTours')).toBeInTheDocument();
  });

  it('confirms and cancels the hold with a toast', async () => {
    renderCheckout();
    fireEvent.click(screen.getByText('tours.checkout.cancelBooking'));
    expect(screen.getByText('tours.checkout.cancelConfirmTitle')).toBeInTheDocument();
    fireEvent.click(screen.getByText('common.confirm'));
    await waitFor(() => {
      expect(apiPost).toHaveBeenCalledWith('/tour-bookings/b-1/cancel');
    });
    await waitFor(() => {
      expect(toastMock).toHaveBeenCalledWith('tours.checkout.cancelSuccess', { type: 'success' });
    });
  });
});

describe('TourCheckout payment failed + retry (FE-3.2)', () => {
  it('shows a failed banner and retries checkout on click', async () => {
    delete window.location;
    window.location = { href: '' };
    renderCheckout(pendingBooking, '/tour-checkout/b-1?payment=failed');
    expect(screen.getByRole('alert')).toHaveTextContent('tours.checkout.paymentFailedTitle');
    fireEvent.click(screen.getByText('tours.checkout.retryPayment'));
    await waitFor(() => {
      expect(apiPost).toHaveBeenCalledWith('/tour-bookings/b-1/checkout-session');
    });
  });
});

describe('TourCheckout payment method (FE-5.6)', () => {
  it('offers VNPay active and PayPal disabled as coming soon', () => {
    renderCheckout();
    expect(screen.getByText('tours.checkout.paymentMethod')).toBeInTheDocument();
    const vnpay = screen.getByRole('radio', { name: /tours\.checkout\.vnpay/ });
    expect(vnpay).toBeChecked();
    expect(vnpay).toBeEnabled();
    const paypal = screen.getByRole('radio', { name: /tours\.checkout\.paypal/ });
    expect(paypal).toBeDisabled();
    expect(screen.getByText('tours.checkout.comingSoon')).toBeInTheDocument();
  });
});

describe('TourCheckout success details (FE-3.3)', () => {
  it('shows tour, schedule, total, email note and invoice button', async () => {
    apiGet.mockResolvedValue({ data: new Blob(['<html></html>']) });
    URL.createObjectURL = vi.fn(() => 'blob:invoice');
    window.open = vi.fn();
    renderCheckout({ ...pendingBooking, status: 'confirmed' });
    expect(screen.getByText('tours.checkout.successTour')).toBeInTheDocument();
    expect(screen.getByText('Hue Tour')).toBeInTheDocument();
    expect(screen.getByText('tours.checkout.downloadInvoice')).toBeInTheDocument();
    // t mock returns interpolated email for emailNote key
    expect(screen.getByText('c@test.com')).toBeInTheDocument();
    fireEvent.click(screen.getByText('tours.checkout.downloadInvoice'));
    await waitFor(() => {
      expect(apiGet).toHaveBeenCalledWith('/tour-bookings/b-1/invoice', { responseType: 'blob' });
    });
    expect(window.open).toHaveBeenCalledWith('blob:invoice', '_blank', 'noopener');
  });
});
