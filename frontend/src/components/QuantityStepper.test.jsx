import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { QuantityStepper } from './QuantityStepper';

const LABELS = { ariaLabel: 'Quantity', decreaseLabel: 'Decrease', increaseLabel: 'Increase' };

describe('QuantityStepper', () => {
  it('renders current value', () => {
    render(<QuantityStepper value={5} {...LABELS} />);
    expect(screen.getByRole('spinbutton', { name: 'Quantity' })).toHaveValue(5);
  });

  it('increments on plus button click', () => {
    const onChange = vi.fn();
    render(<QuantityStepper value={5} onChange={onChange} {...LABELS} />);
    fireEvent.click(screen.getByRole('button', { name: 'Increase' }));
    expect(onChange).toHaveBeenCalledWith(6);
  });

  it('decrements on minus button click', () => {
    const onChange = vi.fn();
    render(<QuantityStepper value={5} onChange={onChange} {...LABELS} />);
    fireEvent.click(screen.getByRole('button', { name: 'Decrease' }));
    expect(onChange).toHaveBeenCalledWith(4);
  });

  it('does not go below min', () => {
    render(<QuantityStepper value={0} onChange={vi.fn()} min={0} {...LABELS} />);
    expect(screen.getByRole('button', { name: 'Decrease' })).toBeDisabled();
  });

  it('does not go above max', () => {
    render(<QuantityStepper value={10} onChange={vi.fn()} max={10} {...LABELS} />);
    expect(screen.getByRole('button', { name: 'Increase' })).toBeDisabled();
  });

  it('commits typed value on blur', () => {
    const onChange = vi.fn();
    render(<QuantityStepper value={5} onChange={onChange} {...LABELS} />);
    const input = screen.getByRole('spinbutton', { name: 'Quantity' });
    fireEvent.change(input, { target: { value: '7' } });
    expect(input).toHaveValue(7);
    expect(onChange).not.toHaveBeenCalled();
    fireEvent.blur(input);
    expect(onChange).toHaveBeenCalledWith(7);
  });

  it('clamps to max on blur for oversized input', () => {
    const onChange = vi.fn();
    render(<QuantityStepper value={5} onChange={onChange} max={10} {...LABELS} />);
    const input = screen.getByRole('spinbutton', { name: 'Quantity' });
    fireEvent.change(input, { target: { value: '100' } });
    fireEvent.blur(input);
    expect(onChange).toHaveBeenCalledWith(10);
  });
});
