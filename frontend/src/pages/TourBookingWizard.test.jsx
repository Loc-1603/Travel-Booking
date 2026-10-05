import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import TourBookingWizard from './TourBookingWizard';

const { apiGet, apiPost } = vi.hoisted(() => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
}));

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key) => key }),
}));

vi.mock('../contexts/useAuth', () => ({
  useAuth: () => ({ user: { id: 1, email: 'c@test.com' } }),
}));

vi.mock('../lib/api', () => ({
  api: {
    get: (...args) => apiGet(...args),
    post: (...args) => apiPost(...args),
  },
}));

vi.mock('../components/TourSlotCalendar', () => ({
  TourSlotCalendar: ({ selectedSlotId, onSelect }) => (
    <div data-testid="slot-calendar">
      <button type="button" onClick={() => onSelect({ id: 9, date: '2026-10-20', start_time: '08:00', end_time: '12:00' })}>
        pick-slot-9
      </button>
      <span data-testid="calendar-selected">{selectedSlotId ?? 'none'}</span>
    </div>
  ),
}));

const tour = { id: 1, title: 'Hue Tour', province: { name: 'Hue' }, meeting_point: 'Gate' };
const preselectedSlot = { id: 5, date: '2026-10-15', start_time: '09:00', end_time: '13:00' };
const breakdown = { subtotal: 500000, add_on_amount: 0, discount: 0, tax: 40000, total: 540000, currency: 'VND' };

function setupClient() {
  return new QueryClient({ defaultOptions: { queries: { retry: false, refetchOnWindowFocus: false } } });
}

function renderWizard(initialEntry) {
  const client = setupClient();
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={[initialEntry]}>
        <Routes>
          <Route path="/tours/book/:uuid" element={<TourBookingWizard />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>
  );
}

beforeEach(() => {
  apiGet.mockReset();
  apiPost.mockReset();
  apiGet.mockImplementation((url) => {
    if (url.startsWith('/tours/') && url.endsWith('/availability')) {
      return Promise.resolve({ data: { success: true, data: [preselectedSlot] } });
    }
    return Promise.resolve({ data: { success: true, data: tour } });
  });
  apiPost.mockResolvedValue({ data: { success: true, data: breakdown } });
});

describe('TourBookingWizard (Phase 2)', () => {
  it('shows selected slot + change button and hides calendar when deep-linked with slot_id', async () => {
    renderWizard('/tours/book/uuid-1?slot_id=5');
    expect(await screen.findByTestId('selected-slot')).toHaveTextContent('2026-10-15');
    expect(screen.getByText('tours.wizard.changeSlot')).toBeInTheDocument();
    // Calendar collapsed by default once a slot is chosen
    expect(screen.queryByTestId('slot-calendar')).not.toBeInTheDocument();
  });

  it('reopens the calendar via change button and selects a new slot', async () => {
    renderWizard('/tours/book/uuid-1?slot_id=5');
    await screen.findByTestId('selected-slot');
    fireEvent.click(screen.getByText('tours.wizard.changeSlot'));
    expect(screen.getByTestId('slot-calendar')).toBeInTheDocument();
    fireEvent.click(screen.getByText('pick-slot-9'));
    await waitFor(() => {
      expect(screen.getByTestId('selected-slot')).toHaveTextContent('2026-10-20');
    });
    // Calendar collapses again after picking
    expect(screen.queryByTestId('slot-calendar')).not.toBeInTheDocument();
  });

  it('shows calendar by default and a no-slot hint when deep-linked without slot_id', async () => {
    renderWizard('/tours/book/uuid-1');
    expect(await screen.findByText('tours.wizard.noSlotSelected')).toBeInTheDocument();
    expect(screen.getByTestId('slot-calendar')).toBeInTheDocument();
  });

  it('renders stepper progress and a back-to-tour link', async () => {
    const { container } = renderWizard('/tours/book/uuid-1?slot_id=5');
    await screen.findByTestId('selected-slot');
    const stepper = container.querySelector('ol[aria-label="tours.wizard.stepsAria"]');
    expect(stepper).toBeInTheDocument();
    expect(screen.getByText('tours.wizard.steps.slot')).toBeInTheDocument();
    const backLink = screen.getByText('tours.wizard.backToTour').closest('a');
    expect(backLink?.getAttribute('href')).toBe('/tours/uuid-1');
  });
});
