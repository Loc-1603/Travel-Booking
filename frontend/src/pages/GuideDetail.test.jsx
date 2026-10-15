import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor, within } from '@testing-library/react';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import GuideDetail from './GuideDetail';

const { apiGet, apiPost } = vi.hoisted(() => ({
  apiGet: vi.fn(),
  apiPost: vi.fn(),
}));

const { mockAuth } = vi.hoisted(() => ({
  mockAuth: { user: { id: 1, email: 'c@test.com' } },
}));

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key) => key }),
}));

vi.mock('../contexts/useAuth', () => ({
  useAuth: () => ({ user: mockAuth.user }),
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

const guide = { uuid: 'g-1', business_name: 'Guide A', languages: ['vi'], price_from: 500000 };
const slots = [{ id: 1, tour: { meeting_point: 'Gate' } }];
const breakdown = { subtotal: 500000, total: 500000, currency: 'VND' };

function renderGuide(initialEntry) {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false, refetchOnWindowFocus: false } },
  });
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={[initialEntry]}>
        <Routes>
          <Route path="/guides/:uuid" element={<GuideDetail />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>
  );
}

beforeEach(() => {
  mockAuth.user = { id: 1, email: 'c@test.com' };
  apiGet.mockReset();
  apiPost.mockReset();
  apiGet.mockImplementation((url) => {
    if (url.includes('/availability')) {
      return Promise.resolve({ data: { success: true, data: slots } });
    }
    if (url === '/saved-guides') {
      return Promise.resolve({ data: { success: true, data: { data: [] } } });
    }
    return Promise.resolve({ data: { success: true, data: guide } });
  });
  apiPost.mockResolvedValue({ data: { success: true, data: breakdown } });
});

describe('GuideDetail date editing (Phase 2)', () => {
  it('renders an editable date picker prefilled with the URL date', async () => {
    renderGuide('/guides/g-1?province=hue&date=2026-10-15');
    const input = await screen.findByLabelText('tours.guides.fixedDate:');
    expect(input).toHaveValue('2026-10-15');
    expect(screen.getByText('tours.guides.updateDate')).toBeInTheDocument();
  });

  it('update button is disabled until the date changes, then updates ?date=', async () => {
    renderGuide('/guides/g-1?province=hue&date=2026-10-15');
    const input = await screen.findByLabelText('tours.guides.fixedDate:');
    const btn = screen.getByText('tours.guides.updateDate');
    expect(btn).toBeDisabled();
    fireEvent.change(input, { target: { value: '2026-10-20' } });
    expect(btn).not.toBeDisabled();
    fireEvent.click(btn);
    await waitFor(() => {
      expect(screen.getByLabelText('tours.guides.fixedDate:')).toHaveValue('2026-10-20');
    });
    // Availability refetch issued for the new date
    expect(apiGet).toHaveBeenCalledWith(
      expect.stringContaining('/availability'),
      expect.objectContaining({ params: expect.objectContaining({ date: '2026-10-20' }) })
    );
  });

  it('shows picker + update even without an initial date', async () => {
    renderGuide('/guides/g-1?province=hue');
    const input = await screen.findByLabelText('tours.guides.travelDateLabel:');
    expect(input).toBeInTheDocument();
    fireEvent.change(input, { target: { value: '2026-11-01' } });
    fireEvent.click(screen.getByText('tours.guides.updateDate'));
    await waitFor(() => {
      expect(apiGet).toHaveBeenCalledWith(
        expect.stringContaining('/availability'),
        expect.objectContaining({ params: expect.objectContaining({ date: '2026-11-01' }) })
      );
    });
  });
});

describe('GuideDetail sticky CTA (FE-5.2)', () => {
  it('renders a mobile sticky CTA with the daily price', async () => {
    renderGuide('/guides/g-1?province=hue&date=2026-10-15');
    await screen.findByText('Guide A');
    const sticky = screen.getByTestId('guide-sticky-cta');
    expect(sticky).toHaveTextContent('tours.guides.priceFrom');
    expect(sticky).toHaveTextContent('500.000');
  });
});

describe('GuideDetail guest estimate (FE-4.4)', () => {
  it('shows the price breakdown to guests and asks to log in only to book', async () => {
    mockAuth.user = null;
    renderGuide('/guides/g-1?province=hue&date=2026-10-15');
    const estimate = await screen.findByTestId('guide-estimate');
    // Subtotal and total both render 500.000 — wait for at least one.
    const hits = await within(estimate).findAllByText(/500\.000/);
    expect(hits.length).toBeGreaterThan(0);
    expect(within(estimate).getByText('tours.guides.loginToBook')).toBeInTheDocument();
    expect(screen.queryByText('tours.guides.loginToSeePrice')).not.toBeInTheDocument();
  });
});

describe('GuideDetail wishlist heart', () => {
  it('saves the guide to favorites when the heart is clicked', async () => {
    renderGuide('/guides/g-1?province=hue&date=2026-10-15');
    await screen.findByText('Guide A');
    fireEvent.click(screen.getByRole('button', { name: 'tours.guides.wishlist.add' }));
    await waitFor(() => {
      expect(apiPost).toHaveBeenCalledWith('/saved-guides', { provider_uuid: 'g-1' });
    });
  });

  it('shows a login link for guests instead of the toggle', async () => {
    mockAuth.user = null;
    renderGuide('/guides/g-1?province=hue&date=2026-10-15');
    await screen.findByText('Guide A');
    const link = screen.getByRole('link', { name: 'tours.guides.wishlist.loginToSave' });
    expect(link).toHaveAttribute('href', '/login');
    expect(screen.queryByRole('button', { name: 'tours.guides.wishlist.add' })).not.toBeInTheDocument();
  });
});
