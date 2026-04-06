import { forwardRef, type ReactNode } from 'react';

// Mock Map component - just renders children
const MockMap = forwardRef<unknown, { children?: ReactNode }>(function MockMap({ children, ...props }, _ref) {
  return <div data-testid="map-container" {...props}>{children}</div>;
});

// Mock Marker - renders children at position
function MockMarker({ children, longitude, latitude, onClick, anchor: _anchor, style: _style, ...props }: { children?: ReactNode; longitude?: number; latitude?: number; onClick?: () => void; anchor?: string; style?: React.CSSProperties; [key: string]: unknown }) {
  return <div data-testid="marker" data-lng={longitude} data-lat={latitude} onClick={onClick} {...props}>{children}</div>;
}

// Mock Popup - renders children
function MockPopup({ children, onClose, anchor: _anchor, offset: _offset, closeButton: _cb, closeOnClick: _coc, className: _cn, ...props }: { children?: ReactNode; onClose?: () => void; [key: string]: unknown }) {
  return <div data-testid="popup" {...props}>{children}<button onClick={onClose}>close</button></div>;
}

// Mock NavigationControl
function MockNavigationControl() {
  return <div data-testid="nav-control" />;
}

// Mock Source + Layer (for polylines, etc.)
function MockSource({ children, ...props }: { children?: ReactNode; [key: string]: unknown }) {
  return <div data-testid="source" {...props}>{children}</div>;
}
function MockLayer(props: Record<string, unknown>) {
  return <div data-testid="layer" {...props} />;
}

export default MockMap;
export { MockMap as Map, MockMarker as Marker, MockPopup as Popup, MockNavigationControl as NavigationControl, MockSource as Source, MockLayer as Layer };
