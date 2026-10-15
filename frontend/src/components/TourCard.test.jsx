import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { TourCard } from './TourCard';

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key) => key }),
}));

const tour = {
  uuid: 't-1',
  title: 'Hue Tour',
  base_fixed: 100000,
  base_price_hourly: 200000,
  base_price_daily: 1500000,
};

function renderCard(props = {}) {
  return render(
    <MemoryRouter>
      <TourCard tour={tour} {...props} />
    </MemoryRouter>
  );
}

describe('TourCard price labels (FE-4.1)', () => {
  it('shows hourly and daily totals with the correct unit', () => {
    renderCard();
    const box = screen.getByTestId('tour-price');
    expect(box).toHaveTextContent('300.000');
    expect(box).toHaveTextContent('1.600.000');
    expect(screen.getByText('tours.card.perHourUnit')).toBeInTheDocument();
    expect(screen.getByText('tours.card.perDayUnit')).toBeInTheDocument();
  });

  it('never shows the old ambiguous label', () => {
    const { container } = renderCard();
    expect(container.textContent).not.toMatch(/from \/ hour/);
  });

  it('hides modes with no rate', () => {
    renderCard({ tour: { ...tour, base_price_daily: 0 } });
    expect(screen.queryByText('tours.card.perDayUnit')).not.toBeInTheDocument();
    expect(screen.getByText('tours.card.perHourUnit')).toBeInTheDocument();
  });
});
