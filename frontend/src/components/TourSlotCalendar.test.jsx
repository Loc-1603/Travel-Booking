import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { TourSlotCalendar } from './TourSlotCalendar';

const { apiGet } = vi.hoisted(() => ({ apiGet: vi.fn() }));

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key) => key, i18n: { language: 'en' } }),
}));

vi.mock('../lib/api', () => ({
  api: { get: (...args) => apiGet(...args) },
}));

function toISODateLocal(d) {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function addDaysLocal(d, n) {
  const c = new Date(d);
  c.setDate(c.getDate() + n);
  return c;
}

function renderCalendar(props = {}) {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false, refetchOnWindowFocus: false } },
  });
  const onSelect = vi.fn();
  const utils = render(
    <QueryClientProvider client={client}>
      <TourSlotCalendar tourUuid="tour-1" onSelect={onSelect} {...props} />
    </QueryClientProvider>
  );
  return { onSelect, ...utils };
}

beforeEach(() => {
  apiGet.mockReset();
  // One override slot on `from`, one plain slot the day after.
  apiGet.mockImplementation((url, { params } = {}) => {
    const from = params?.from;
    const next = (() => {
      const [y, m, d] = from.split('-').map(Number);
      return toISODateLocal(addDaysLocal(new Date(y, m - 1, d), 1));
    })();
    return Promise.resolve({
      data: {
        success: true,
        data: [
          { id: 1, date: from, start_time: '09:00', end_time: '12:00', status: 'available', price_override: 250000 },
          { id: 2, date: next, start_time: '14:00', end_time: '16:00', status: 'available', price_override: null },
        ],
      },
    });
  });
});

describe('TourSlotCalendar week view (FE-4.2)', () => {
  it('renders slots with a price badge on override slots and highlights today', async () => {
    renderCalendar();
    expect(await screen.findByText('09:00 – 12:00')).toBeInTheDocument();
    expect(screen.getByTestId('slot-price-badge')).toHaveTextContent('250.000');
    expect(screen.getByTestId('today-badge')).toBeInTheDocument();
  });

  it('selects a slot on click', async () => {
    const { onSelect } = renderCalendar();
    fireEvent.click(await screen.findByText('09:00 – 12:00'));
    expect(onSelect).toHaveBeenCalledWith(expect.objectContaining({ id: 1 }));
  });

  it('pages past the old 8-week cap', async () => {
    renderCalendar();
    await screen.findByText('09:00 – 12:00');
    const next = screen.getByLabelText('tours.slots.nextWeek');
    for (let i = 0; i < 8; i += 1) fireEvent.click(next);
    expect(next).not.toBeDisabled();
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const expectedFrom = toISODateLocal(addDaysLocal(today, 8 * 7));
    await waitFor(() => {
      const calls = apiGet.mock.calls.map((c) => c[1]?.params?.from);
      expect(calls).toContain(expectedFrom);
    });
  });
});

describe('TourSlotCalendar month view (FE-4.2)', () => {
  async function openMonth() {
    const utils = renderCalendar();
    fireEvent.click(screen.getByText('tours.slots.monthView'));
    await waitFor(() => {
      expect(screen.getAllByRole('button', { name: 'tours.slots.daySlots' }).length).toBeGreaterThan(27);
    });
    return utils;
  }

  function dayCells(container) {
    return [...container.querySelectorAll('button')].filter((b) =>
      /^\d{1,2}$/.test(b.children[0]?.textContent ?? '')
    );
  }

  it('locks past days and highlights today', async () => {
    const { container } = await openMonth();
    const dayNum = new Date().getDate();
    const cells = dayCells(container);
    expect(cells.length).toBeGreaterThanOrEqual(28);
    for (const cell of cells) {
      if (Number(cell.children[0].textContent) < dayNum) {
        expect(cell).toBeDisabled();
      }
    }
    const todayBadge = container.querySelector('[data-testid="today-badge"]');
    expect(todayBadge).toBeInTheDocument();
    expect(todayBadge.closest('button')).not.toBeDisabled();
  });

  it('shows a price badge on days with an override slot and opens day slots', async () => {
    const { container, onSelect } = await openMonth();
    expect(container.querySelector('[data-testid="day-price-badge"]')).toHaveTextContent('250.000');
    const todayBadge = container.querySelector('[data-testid="today-badge"]');
    fireEvent.click(todayBadge.closest('button'));
    fireEvent.click(await screen.findByText('09:00 – 12:00'));
    expect(onSelect).toHaveBeenCalledWith(expect.objectContaining({ id: 1 }));
  });
});
