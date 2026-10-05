import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { Stepper } from './Stepper';

describe('Stepper', () => {
  it('renders all steps with correct indices', () => {
    render(<Stepper steps={['Step 1', 'Step 2', 'Step 3']} />);
    const indicators = screen.getAllByTestId('step-indicator');
    expect(indicators).toHaveLength(3);
    expect(screen.getByText('Step 1')).toBeInTheDocument();
    expect(screen.getByText('Step 2')).toBeInTheDocument();
    expect(screen.getByText('Step 3')).toBeInTheDocument();
  });

  it('marks current step with aria-current', () => {
    render(<Stepper steps={['A', 'B', 'C']} current={1} />);
    expect(screen.getByText('B').closest('li')).toHaveAttribute('aria-current', 'step');
    expect(screen.getByText('A').closest('li')).not.toHaveAttribute('aria-current');
    expect(screen.getByText('C').closest('li')).not.toHaveAttribute('aria-current');
  });

  it('shows checkmark for completed steps', () => {
    render(<Stepper steps={['Done', 'Active', 'Pending']} current={1} />);
    const indicators = screen.getAllByTestId('step-indicator');
    expect(indicators[0]).toHaveTextContent('✓');
  });

  it('shows step number for pending steps', () => {
    render(<Stepper steps={['Done', 'Next', 'Last']} current={1} />);
    const indicators = screen.getAllByTestId('step-indicator');
    expect(indicators[1]).toHaveTextContent('2');
    expect(indicators[2]).toHaveTextContent('3');
  });

  it('applies custom className', () => {
    const { container } = render(<Stepper steps={['A']} className="custom-class" />);
    expect(container.firstChild).toHaveClass('custom-class');
  });
});
