import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import { TourPriceSummary } from './TourPriceSummary';

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key) => key,
  }),
}));

describe('TourPriceSummary', () => {
  it('renders subtotal and total', () => {
    render(<TourPriceSummary subtotal={100000} total={100000} />);
    expect(screen.getByText('tours.price.subtotal')).toBeInTheDocument();
    expect(screen.getByText('tours.price.total')).toBeInTheDocument();
  });

  it('renders add-on when positive', () => {
    render(<TourPriceSummary subtotal={100000} addOn={50000} />);
    expect(screen.getByText('tours.price.addon')).toBeInTheDocument();
  });

  it('renders transport fee when positive', () => {
    render(<TourPriceSummary subtotal={100000} transport={20000} />);
    expect(screen.getByText('tours.price.transport')).toBeInTheDocument();
  });

  it('renders discount as negative', () => {
    render(<TourPriceSummary subtotal={100000} discount={10000} />);
    expect(screen.getByText('tours.price.discount')).toBeInTheDocument();
    expect(screen.getByText('-10.000 ₫')).toBeInTheDocument();
  });

  it('renders tax when showTax is true', () => {
    render(<TourPriceSummary subtotal={100000} tax={5000} showTax={true} />);
    expect(screen.getByText('tours.price.tax')).toBeInTheDocument();
  });

  it('does not render tax by default', () => {
    render(<TourPriceSummary subtotal={100000} />);
    expect(screen.queryByText('tours.price.tax')).not.toBeInTheDocument();
  });

  it('calculates total when not provided', () => {
    render(<TourPriceSummary subtotal={100000} transport={10000} discount={5000} total={undefined} />);
    expect(screen.getByText('105.000 ₫')).toBeInTheDocument();
  });
});