import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import MyBookings from './MyBookings';

const { apiGet, apiPost } = vi.hoisted(() => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
}));

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key) => key, i18n: { language: 'en' } }),
}));

vi.mock('../contexts/useAuth', () => ({
  useAuth: () => ({ user: { id: 1, name: 'Cara', email: 'c@test.com' } }),
}));

vi.mock('../lib/api', () => ({
  api: {
    get: (...args) => apiGet(...args),
    post: (...args) => apiPost(...args),
  },
}));

const booking = {
  id: 7,
  uuid: 'b-1',
  status: 'confirmed',
  check_in: '2026-10-10',
  check_out: '2026-10-12',
  total_price: 1200000,
  currency: 'VND',
  hotel: { name: 'Sunrise Hotel' },
  booking_rooms: [{ room: { name: 'Deluxe' }, quantity: 1 }],
  review: null,
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

function renderPage(items = [booking]) {
  apiGet.mockImplementation((url) => {
    if (url === '/bookings') return Promise.resolve(listResponse(items));
    return Promise.reject(new Error(`unexpected GET ${url}`));
  });
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false, refetchOnWindowFocus: false } },
  });
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={['/bookings']}>
        <MyBookings />
      </MemoryRouter>
    </QueryClientProvider>
  );
}

beforeEach(() => {
  apiGet.mockReset();
  apiPost.mockReset();
});

describe('MyBookings hotel review form', () => {
  it('shows the write-review action for confirmed bookings without a review', async () => {
    renderPage();
    await screen.findByText('Sunrise Hotel');
    expect(screen.getByText('myBookings.actions.writeReview')).toBeInTheDocument();
    expect(screen.queryByText('myBookings.actions.reviewed')).not.toBeInTheDocument();
  });

  it('shows the reviewed badge instead of the action when a review exists', async () => {
    renderPage([{ ...booking, review: { id: 1, rating: 5 } }]);
    await screen.findByText('Sunrise Hotel');
    expect(screen.getByText('myBookings.actions.reviewed')).toBeInTheDocument();
    expect(screen.queryByText('myBookings.actions.writeReview')).not.toBeInTheDocument();
  });

  it('hides review controls for non-reviewable statuses', async () => {
    renderPage([{ ...booking, status: 'pending' }]);
    await screen.findByText('Sunrise Hotel');
    expect(screen.queryByText('myBookings.actions.writeReview')).not.toBeInTheDocument();
    expect(screen.queryByText('myBookings.actions.reviewed')).not.toBeInTheDocument();
  });

  it('opens the shared form in a modal and posts the hotel review', async () => {
    apiPost.mockResolvedValue({ data: { success: true } });
    renderPage();
    await screen.findByText('Sunrise Hotel');

    fireEvent.click(screen.getByText('myBookings.actions.writeReview'));
    expect(await screen.findByRole('dialog')).toBeInTheDocument();
    expect(screen.getByText('tours.reviews.writeTitle')).toBeInTheDocument();

    fireEvent.click(screen.getAllByRole('button', { name: 'tours.reviews.rateStar' })[3]);
    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'Great stay' } });
    fireEvent.click(screen.getByText('tours.reviews.submit'));

    await waitFor(() => {
      expect(apiPost).toHaveBeenCalledWith('/reviews', expect.any(FormData));
    });
    const body = apiPost.mock.calls[0][1];
    expect(body.get('booking_id')).toBe('7');
    expect(body.get('rating')).toBe('4');
    expect(body.get('comment')).toBe('Great stay');
    expect(body.get('tour_booking_id')).toBeNull();

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
  });
});
