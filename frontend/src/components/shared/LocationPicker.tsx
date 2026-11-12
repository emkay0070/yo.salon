'use client';

import { useState, useCallback } from 'react';
import Map, { Marker, NavigationControl } from 'react-map-gl/mapbox';
import 'mapbox-gl/dist/mapbox-gl.css';
import { MapPin } from 'lucide-react';

const MAPBOX_TOKEN = process.env.NEXT_PUBLIC_MAPBOX_TOKEN;

interface LocationPickerProps {
  lat?: number;
  lng?: number;
  onLocationChange: (lat: number, lng: number) => void;
  readOnly?: boolean;
  className?: string;
}

export default function LocationPicker({ 
  lat, 
  lng, 
  onLocationChange, 
  readOnly = false,
  className = ''
}: LocationPickerProps) {
  const [viewState, setViewState] = useState({
    longitude: lng || 32.5825,
    latitude: lat || 0.3476,
    zoom: 12
  });
  const [mapError, setMapError] = useState(false);

  const handleMapClick = useCallback((e: any) => {
    if (readOnly) return;
    const { lng, lat } = e.lngLat;
    onLocationChange(lat, lng);
  }, [readOnly, onLocationChange]);

  const handleDetectLocation = useCallback(() => {
    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(
        (position) => {
          const { latitude, longitude } = position.coords;
          onLocationChange(latitude, longitude);
          setViewState({
            longitude,
            latitude,
            zoom: 15
          });
        },
        (error) => {
          console.error('Error detecting location:', error);
          const defaultLat = 0.3476;
          const defaultLng = 32.5825;
          onLocationChange(defaultLat, defaultLng);
          setViewState({
            longitude: defaultLng,
            latitude: defaultLat,
            zoom: 12
          });
        }
      );
    }
  }, [onLocationChange]);

  return (
    <div className={`space-y-3 ${className}`}>
      <div className="flex items-center justify-between">
        <label className="block text-xs font-semibold text-white/40 tracking-widest uppercase">Location</label>
        {!readOnly && (
          <button
            type="button"
            onClick={handleDetectLocation}
            className="text-xs text-[#FFD700] hover:text-[#FFD700]/80 font-semibold flex items-center gap-1.5 transition-colors"
          >
            <MapPin className="w-3.5 h-3.5" />
            Detect Location Automatically
          </button>
        )}
      </div>

      {MAPBOX_TOKEN && !mapError ? (
        <div className="w-full h-64 rounded-2xl overflow-hidden border border-white/[0.08] relative">
          <Map
            {...viewState}
            onMove={evt => setViewState(evt.viewState)}
            onClick={handleMapClick}
            mapboxAccessToken={MAPBOX_TOKEN}
            mapStyle="mapbox://styles/mapbox/dark-v11"
            cursor={readOnly ? 'grab' : 'crosshair'}
            onError={() => setMapError(true)}
            performanceMetricsCollection={false}
          >
            <NavigationControl position="bottom-right" />
            
            {lat && lng && (
              <Marker 
                longitude={lng} 
                latitude={lat} 
                anchor="bottom"
              >
                <div className="relative">
                  <div className="absolute inset-0 bg-gold rounded-full blur-sm opacity-50 animate-pulse"></div>
                  <div className="w-8 h-8 bg-black border-2 border-gold rounded-full flex items-center justify-center shadow-[0_0_15px_rgba(255,215,0,0.5)] relative z-10">
                    <MapPin className="w-4 h-4 text-gold" />
                  </div>
                </div>
              </Marker>
            )}
          </Map>
          {!lat && (
            <div className="absolute inset-0 pointer-events-none flex items-center justify-center bg-black/40">
              <div className="bg-black/80 backdrop-blur-md px-4 py-2.5 rounded-lg border border-white/10 text-white/70 text-sm font-medium">
                Click "Detect Location" or click on the map to place your pin
              </div>
            </div>
          )}
        </div>
      ) : mapError ? (
        <div className="w-full h-32 rounded-2xl bg-white/[0.02] border border-white/[0.08] flex items-center justify-center text-white/30 text-sm p-4 text-center">
          <div className="space-y-2">
            <p>Map unavailable due to connection error</p>
            <button
              onClick={() => setMapError(false)}
              className="text-[#FFD700] hover:text-[#FFD700]/80 text-xs font-semibold"
            >
              Retry
            </button>
          </div>
        </div>
      ) : (
        <div className="w-full h-32 rounded-2xl bg-white/[0.02] border border-white/[0.08] flex items-center justify-center text-white/30 text-sm">
          Mapbox token not configured
        </div>
      )}

      {lat && lng && (
        <div className="text-[10px] text-white/40 flex items-center gap-1.5 px-1">
          <span className="w-1.5 h-1.5 rounded-full bg-[#FFD700] animate-pulse"></span>
          Pinned coordinates: {lat.toFixed(5)}, {lng.toFixed(5)} {!readOnly && '(Click map to adjust)'}
        </div>
      )}
    </div>
  );
}
