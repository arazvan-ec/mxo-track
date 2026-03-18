import { useState } from 'react';
import { useFleetMapData } from '@/api/hooks/useFleetMapData';
import { FleetMap } from '@/components/maps/FleetMap';

export function FleetMapPage() {
  const { vehicles, routes, isLoading, error } = useFleetMapData();
  const [selectedVehicle, setSelectedVehicle] = useState<string | null>(null);

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-full">
        <div className="text-gray-500">Loading fleet data...</div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="flex items-center justify-center h-full">
        <div className="text-red-500">Error loading fleet data: {error.message}</div>
      </div>
    );
  }

  return (
    <div className="flex h-full">
      {/* Side panel */}
      <div className="w-80 border-r border-gray-200 overflow-y-auto bg-white">
        <div className="p-4 border-b border-gray-200">
          <h2 className="text-lg font-semibold">Fleet Map</h2>
          <div className="mt-2 flex gap-4 text-sm text-gray-500">
            <span>{vehicles.length} vehicles</span>
            <span>{routes.length} routes</span>
          </div>
        </div>

        {/* Vehicle list */}
        <div className="p-2">
          <h3 className="px-2 py-1 text-xs font-medium text-gray-400 uppercase">
            Vehicles
          </h3>
          {vehicles.map((v) => (
            <button
              key={v.public_id}
              onClick={() => setSelectedVehicle(v.public_id)}
              className={`w-full text-left px-3 py-2 rounded text-sm hover:bg-gray-50 ${
                selectedVehicle === v.public_id ? 'bg-blue-50 text-blue-700' : ''
              }`}
            >
              <div className="font-medium">{v.name}</div>
              {v.driver_name && (
                <div className="text-xs text-gray-400">{v.driver_name}</div>
              )}
              {v.route_name && (
                <div className="text-xs text-gray-400">{v.route_name}</div>
              )}
            </button>
          ))}
        </div>
      </div>

      {/* Map */}
      <div className="flex-1">
        <FleetMap
          vehicles={vehicles}
          routes={routes}
          onVehicleClick={setSelectedVehicle}
        />
      </div>
    </div>
  );
}
