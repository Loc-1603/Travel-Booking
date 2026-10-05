import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import TourCheckout from './TourCheckout';

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key) => key }),
}));

vi.mock('../contexts/useAuth', () => ({
  useAuth: () => ({ user: { id: 1, email: 'c@test.com' } }),
}));

vi.mock('../lib/api', () => ({
  api: { get: vi.fn(), post: vi.fn() },
}));

const booking = {
  uuid: 'b-1',
  status: 'pending_payment',
  total_price: 540000,
  currency: 'VND',
  tour: { title: 'Hue Tour' },
  slot: { date: '2026-10-15', start_time: '09:00', end_time: '13:00' },
};

function renderCheckout() {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false, refetchOnWindowFocus: false } },
  });
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={[{ pathname: '/tour-checkout/b-1', state: { booking } }]}>
        <Routes>
          <Route path="/tour-checkout/:uuid" element={<TourCheckout />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>
  );
}

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
