import { render, screen, fireEvent } from '@testing-library/react';
import { describe, it, expect, vi } from 'vitest';

vi.mock('react-map-gl/maplibre', () => import('../../../test/mocks/react-map-gl'));

import { VehicleMarker } from './VehicleMarker';

describe('VehicleMarker', () => {
  const defaultProps = {
    lng: -99.13,
    lat: 19.43,
    name: 'Truck Alpha',
  };

  it('renders marker with vehicle name in title', () => {
    render(<VehicleMarker {...defaultProps} />);
    expect(screen.getByTitle('Truck Alpha')).toBeInTheDocument();
  });

  it('includes speed in title when provided', () => {
    render(<VehicleMarker {...defaultProps} speed={85.7} />);
    expect(screen.getByTitle('Truck Alpha - 86 km/h')).toBeInTheDocument();
  });

  it('calls onClick when clicked', () => {
    const handleClick = vi.fn();
    render(<VehicleMarker {...defaultProps} onClick={handleClick} />);
    fireEvent.click(screen.getByTestId('marker'));
    expect(handleClick).toHaveBeenCalledTimes(1);
  });

  it('shows popup on click when popupContent provided', () => {
    render(<VehicleMarker {...defaultProps} popupContent={<span>Vehicle details</span>} />);
    expect(screen.queryByTestId('popup')).not.toBeInTheDocument();
    fireEvent.click(screen.getByTestId('marker'));
    expect(screen.getByTestId('popup')).toBeInTheDocument();
    expect(screen.getByText('Vehicle details')).toBeInTheDocument();
  });

  it('uses provided color prop', () => {
    render(<VehicleMarker {...defaultProps} color="#FF5500" />);
    const markerDiv = screen.getByTitle('Truck Alpha');
    // border should use the color directly
    expect(markerDiv).toHaveStyle({ border: '2px solid #FF5500' });
  });

  it('falls back to getVehicleColor when no color prop', () => {
    // With REFRIGERATED skill, should use SKILL_COLORS.REFRIGERATED = '#0ea5e9'
    render(<VehicleMarker {...defaultProps} skills={['REFRIGERATED']} />);
    const markerDiv = screen.getByTitle('Truck Alpha');
    expect(markerDiv).toHaveStyle({ border: '2px solid #0ea5e9' });
  });
});
