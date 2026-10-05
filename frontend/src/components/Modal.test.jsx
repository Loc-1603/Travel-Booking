import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import '@testing-library/jest-dom';
import { expectNoAxeViolations } from '../test/axe';
import { Modal } from './Modal';

describe('Modal', () => {
  beforeEach(() => {
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.useRealTimers();
    vi.clearAllMocks();
  });

  it('renders when open', () => {
    render(<Modal open title="Test Modal">Content</Modal>);
    expect(screen.getByRole('dialog')).toBeInTheDocument();
    expect(screen.getByText('Test Modal')).toBeInTheDocument();
  });

  it('does not render when closed', () => {
    render(<Modal open={false} title="Test Modal">Content</Modal>);
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('renders children', () => {
    render(<Modal open>Modal content</Modal>);
    expect(screen.getByText('Modal content')).toBeInTheDocument();
  });

  it('renders footer', () => {
    render(
      <Modal open footer={<button>Action</button>}>
        Content
      </Modal>
    );
    expect(screen.getByRole('button', { name: 'Action' })).toBeInTheDocument();
  });

  it('closes on ESC key', () => {
    const onClose = vi.fn();
    render(<Modal open title="Test" onClose={onClose}>Content</Modal>);
    fireEvent.keyDown(document, { key: 'Escape' });
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('hides close button when hideClose is true', () => {
    render(<Modal open title="Test" hideClose>Content</Modal>);
    expect(screen.queryByLabelText(/close/i)).not.toBeInTheDocument();
  });

  it('calls onClose on backdrop click', () => {
    const onClose = vi.fn();
    render(<Modal open title="Test" onClose={onClose}>Content</Modal>);
    fireEvent.click(screen.getByTestId('modal-backdrop'));
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('has no axe violations', async () => {
    vi.useRealTimers();
    render(
      <Modal open title="Test" onClose={() => {}} footer={<button>Action</button>}>
        Content
      </Modal>
    );
    await expectNoAxeViolations(document.body);
  });
});