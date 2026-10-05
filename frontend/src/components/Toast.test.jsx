import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { ToastViewport, Toast } from './Toast';

describe('Toast', () => {
  it('renders message', () => {
    render(<Toast message="Test message" type="info" />);
    expect(screen.getByText('Test message')).toBeInTheDocument();
  });

  it('has role status for info type', () => {
    render(<Toast message="Info" type="info" />);
    expect(screen.getByRole('status')).toBeInTheDocument();
  });

  it('has role alert for error type', () => {
    render(<Toast message="Error" type="error" />);
    expect(screen.getByRole('alert')).toBeInTheDocument();
  });

  it('can be dismissed', () => {
    const onClose = vi.fn();
    render(<Toast message="Test" type="info" onDismiss={onClose} dismissLabel="Close" />);
    fireEvent.click(screen.getByRole('button', { name: 'Close' }));
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('applies variant classes for success type', () => {
    render(<Toast message="Success" type="success" dismissLabel="Close" />);
    expect(screen.getByRole('status')).toHaveClass('border-success/30');
  });
});

describe('ToastViewport', () => {
  it('renders list of toasts', () => {
    const toasts = [
      { id: 1, message: 'First', type: 'info' },
      { id: 2, message: 'Second', type: 'warning' },
    ];
    render(<ToastViewport toasts={toasts} onDismiss={() => {}} />);
    expect(screen.getByText('First')).toBeInTheDocument();
    expect(screen.getByText('Second')).toBeInTheDocument();
  });

  it('calls onDismiss when toast is removed', () => {
    const onDismiss = vi.fn();
    render(<ToastViewport toasts={[{ id: 1, message: 'Test' }]} onDismiss={onDismiss} />);
    fireEvent.click(screen.getByRole('button'));
    expect(onDismiss).toHaveBeenCalledWith(1);
  });
});