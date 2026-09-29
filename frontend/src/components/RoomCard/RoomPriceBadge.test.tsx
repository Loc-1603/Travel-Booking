import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { RoomPriceBadge } from './RoomPriceBadge';

describe('RoomPriceBadge', () => {
  it('renders nothing when price is missing', () => {
    const { container } = render(<RoomPriceBadge price={null} />);
    expect(container).toBeEmptyDOMElement();
    expect(render(<RoomPriceBadge />).container).toBeEmptyDOMElement();
  });

  it('compact variant shows the per-night price', () => {
    render(<RoomPriceBadge price={1500000} variant="compact" />);
    expect(screen.getByText(/1\.500\.000/)).toBeInTheDocument();
    expect(screen.getByText('/đêm')).toBeInTheDocument();
  });

  it('compact variant shows the multi-night total when nights is known', () => {
    render(<RoomPriceBadge price={1500000} nights={2} variant="compact" />);
    // single text node: "Tổng 2 đêm: 3.000.000 ₫" → regex, not exact match
    expect(screen.getByText(/Tổng 2 đêm:/)).toBeInTheDocument();
    // 1.500.000 × 2
    expect(screen.getByText(/3\.000\.000/)).toBeInTheDocument();
  });

  it('detailed variant renders the larger per-night price and total', () => {
    render(<RoomPriceBadge price={900000} nights={3} variant="detailed" />);
    expect(screen.getByText(/Tổng 3 đêm:/)).toBeInTheDocument();
    expect(screen.getAllByText(/2\.700\.000/).length).toBeGreaterThan(0);
    expect(screen.getByText(/900\.000/)).toBeInTheDocument();
  });

  it('does not show a total for zero or negative nights', () => {
    render(<RoomPriceBadge price={1000} nights={0} />);
    expect(screen.queryByText(/Tổng/)).toBeNull();
  });});
