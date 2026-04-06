import { render, screen, fireEvent } from '@testing-library/react';
import { describe, it, expect, vi } from 'vitest';

vi.mock('react-map-gl/maplibre', () => import('../../../test/mocks/react-map-gl'));

import { StopMarker } from './StopMarker';

describe('StopMarker', () => {
  const defaultProps = {
    lng: -99.13,
    lat: 19.43,
    sequence: 5,
    status: 'PENDING',
    address: '456 Avenue, Guadalajara',
  };

  it('renders marker with correct sequence number', () => {
    render(<StopMarker {...defaultProps} />);
    expect(screen.getByText('5')).toBeInTheDocument();
  });

  it('uses routeColor for PENDING status', () => {
    render(<StopMarker {...defaultProps} status="PENDING" routeColor="#FF0000" />);
    const marker = screen.getByText('5');
    expect(marker).toHaveStyle({ backgroundColor: '#FF0000' });
  });

  it('uses status color for DELIVERED (ignores routeColor)', () => {
    render(<StopMarker {...defaultProps} status="DELIVERED" routeColor="#FF0000" />);
    const marker = screen.getByText('5');
    expect(marker).toHaveStyle({ backgroundColor: '#10B981' });
  });

  it('falls back to PENDING color for unknown status', () => {
    render(<StopMarker {...defaultProps} status="UNKNOWN_STATUS" />);
    const marker = screen.getByText('5');
    // Falls back to STOP_STATUS_COLORS.pending = '#3B82F6'
    expect(marker).toHaveStyle({ backgroundColor: '#3B82F6' });
  });

  it('calls onClick when marker is clicked', () => {
    const handleClick = vi.fn();
    render(<StopMarker {...defaultProps} onClick={handleClick} />);
    const markerWrapper = screen.getByTestId('marker');
    fireEvent.click(markerWrapper);
    expect(handleClick).toHaveBeenCalledTimes(1);
  });

  it('shows popup after click when popupContent provided', () => {
    render(<StopMarker {...defaultProps} popupContent={<span>Popup info</span>} />);
    // Popup should not be visible initially
    expect(screen.queryByTestId('popup')).not.toBeInTheDocument();
    // Click the marker
    fireEvent.click(screen.getByTestId('marker'));
    // Popup should now be visible
    expect(screen.getByTestId('popup')).toBeInTheDocument();
    expect(screen.getByText('Popup info')).toBeInTheDocument();
  });

  it('hides popup initially', () => {
    render(<StopMarker {...defaultProps} popupContent={<span>Hidden</span>} />);
    expect(screen.queryByTestId('popup')).not.toBeInTheDocument();
  });
});
