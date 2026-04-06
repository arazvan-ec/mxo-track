import { render, screen } from '@testing-library/react';
import { describe, it, expect } from 'vitest';
import { StopPopup } from './StopPopup';

describe('StopPopup', () => {
  const defaultProps = {
    sequence: 3,
    address: '123 Main Street, Mexico City',
    status: 'PENDING',
  };

  it('renders sequence number', () => {
    render(<StopPopup {...defaultProps} />);
    expect(screen.getByText('3')).toBeInTheDocument();
  });

  it('renders address text', () => {
    render(<StopPopup {...defaultProps} />);
    expect(screen.getByText('123 Main Street, Mexico City')).toBeInTheDocument();
  });

  it('renders status text uppercased', () => {
    render(<StopPopup {...defaultProps} status="DELIVERED" />);
    expect(screen.getByText('DELIVERED')).toBeInTheDocument();
  });

  it('renders recipientName when provided', () => {
    render(<StopPopup {...defaultProps} recipientName="Juan Perez" />);
    expect(screen.getByText('Juan Perez')).toBeInTheDocument();
  });

  it('does NOT render recipientName div when not provided', () => {
    const { container } = render(<StopPopup {...defaultProps} />);
    const slateTexts = container.querySelectorAll('.text-slate-400');
    expect(slateTexts).toHaveLength(0);
  });

  it('renders truncated shipmentPublicId when provided', () => {
    render(<StopPopup {...defaultProps} shipmentPublicId="ABCDEFGHIJKLMNOP" />);
    expect(screen.getByText(/Envio: ABCDEFGHIJKL\.\.\./)).toBeInTheDocument();
  });

  it('applies correct status color for DELIVERED', () => {
    render(<StopPopup {...defaultProps} status="DELIVERED" />);
    const sequenceBadge = screen.getByText('3');
    expect(sequenceBadge).toHaveStyle({ backgroundColor: '#10B981' });
  });
});
