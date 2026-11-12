'use client';

import { useState, useEffect } from 'react';
import Map, { Marker, NavigationControl, GeolocateControl, ViewStateChangeEvent } from 'react-map-gl/mapbox';
import { Search, MapPin, X, Check, Layers } from 'lucide-react';
import { motion, AnimatePresence } from 'framer-motion';

const MAPBOX_TOKEN = process.env.NEXT_PUBLIC_MAPBOX_TOKEN;

interface LocationPickerProps {
  initialLocation?: {
    address?: string;
    city?: string;
    country?: string;
    lat?: number;
    lng?: number;
  };
  onLocationSelect: (location: {
    address: string;
    city: string;
    country: string;
    lat: number;
    lng: number;
  }) => void;
  onCancel: () => void;
  brandColors?: {
    primary?: string;
    secondary?: string;
    accent?: string;
  };
}

interface GeocodeResult {
  place_name: string;
  center: [number, number];
  context: Array<{
    id: string;
    text: string;
  }>;
}

export function LocationPicker({ initialLocation, onLocationSelect, onCancel, brandColors }: LocationPickerProps) {
  // Use brand colors with iOS fallbacks
  const colors = {
    primary: brandColors?.primary || '#007AFF',
    secondary: brandColors?.secondary || '#5856D6',
    accent: brandColors?.accent || '#FF9500',
  };

  const [viewState, setViewState] = useState({
    longitude: initialLocation?.lng || 32.5825,
    latitude: initialLocation?.lat || 0.3476,
    zoom: 16
  });
  
  const [markerPosition, setMarkerPosition] = useState<{
    lng: number;
    lat: number;
  } | null>(initialLocation?.lat && initialLocation?.lng ? {
    lng: initialLocation.lng,
    lat: initialLocation.lat
  } : null);
  
  const [searchQuery, setSearchQuery] = useState('');
  const [searchResults, setSearchResults] = useState<GeocodeResult[]>([]);
  const [searching, setSearching] = useState(false);
  const [selectedAddress, setSelectedAddress] = useState({
    address: initialLocation?.address || '',
    city: initialLocation?.city || '',
    country: initialLocation?.country || '',
  });
  const [mapStyle, setMapStyle] = useState('streets-v12');
  const [showStyleSelector, setShowStyleSelector] = useState(false);
  const [mapError, setMapError] = useState(false);

  useEffect(() => {
    if (initialLocation?.lat && initialLocation?.lng) {
      setMarkerPosition({ lng: initialLocation.lng, lat: initialLocation.lat });
      setViewState({
        longitude: initialLocation.lng,
        latitude: initialLocation.lat,
        zoom: 16
      });
    }
  }, [initialLocation]);

  const handleSearch = async (query: string) => {
    setSearchQuery(query);
    if (!query || query.length < 3) {
      setSearchResults([]);
      return;
    }

    setSearching(true);
    try {
      const response = await fetch(
        `https://api.mapbox.com/geocoding/v5/mapbox.places/${encodeURIComponent(query)}.json?access_token=${MAPBOX_TOKEN}&limit=5&autocomplete=true`
      );
      const data = await response.json();
      setSearchResults(data.features || []);
    } catch (error) {
      console.error('Geocoding error:', error);
    } finally {
      setSearching(false);
    }
  };

  const handleSelectPlace = (place: GeocodeResult) => {
    const [lng, lat] = place.center;
    setMarkerPosition({ lng, lat });
    setViewState({ longitude: lng, latitude: lat, zoom: 18 });
    setSearchQuery('');
    setSearchResults([]);

    // Extract address components
    const parts = place.place_name.split(', ');
    const address = parts[0] || '';
    const city = parts[1] || '';
    const country = parts[parts.length - 1] || '';

    setSelectedAddress({ address, city, country });
  };

  const handleMapClick = async (e: any) => {
    const { lng, lat } = e.lngLat;
    setMarkerPosition({ lng, lat });

    // Reverse geocode
    try {
      const response = await fetch(
        `https://api.mapbox.com/geocoding/v5/mapbox.places/${lng},${lat}.json?access_token=${MAPBOX_TOKEN}`
      );
      const data = await response.json();
      if (data.features && data.features.length > 0) {
        const place = data.features[0];
        const parts = place.place_name.split(', ');
        const address = parts[0] || '';
        const city = parts[1] || '';
        const country = parts[parts.length - 1] || '';
        setSelectedAddress({ address, city, country });
      }
    } catch (error) {
      console.error('Reverse geocoding error:', error);
    }
  };

  const handleConfirm = () => {
    if (markerPosition) {
      onLocationSelect({
        address: selectedAddress.address,
        city: selectedAddress.city,
        country: selectedAddress.country,
        lat: markerPosition.lat,
        lng: markerPosition.lng,
      });
    }
  };

  if (!MAPBOX_TOKEN) {
    return (
      <div className="bg-black/50 backdrop-blur-xl border border-white/10 rounded-2xl p-6">
        <p className="text-red-400">Mapbox Token is missing in environment variables.</p>
      </div>
    );
  }

  if (mapError) {
    return (
      <div className="bg-[#1c1c1e] rounded-3xl overflow-hidden shadow-2xl p-6">
        <div className="text-center space-y-4">
          <p className="text-white/70">Map unavailable due to connection error</p>
          <button
            onClick={() => setMapError(false)}
            className="px-4 py-2 bg-[#007AFF] rounded-xl text-white font-semibold hover:bg-[#5856D6] transition-colors"
          >
            Retry
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="bg-[#1c1c1e] rounded-3xl overflow-hidden shadow-2xl">
      {/* Header */}
      <div className="p-4 bg-[#2c2c2e] border-b border-[#3a3a3c]">
        <h3 className="text-lg font-semibold text-white mb-1">Select Location</h3>
        <p className="text-sm text-[#8e8e93]">Search for an address or tap on the map</p>
      </div>

      {/* Search Bar - iOS Style */}
      <div className="p-4 bg-[#2c2c2e]">
        <div className="relative">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[#8e8e93]" />
          <input
            type="text"
            placeholder="Search places..."
            value={searchQuery}
            onChange={(e) => handleSearch(e.target.value)}
            className="w-full pl-10 pr-10 py-3 bg-[#1c1c1e] rounded-xl text-white placeholder-[#8e8e93] focus:outline-none focus:ring-2 transition-all text-sm font-medium"
            style={{ '--tw-ring-color': colors.primary } as React.CSSProperties}
          />
          {searchQuery && (
            <button
              onClick={() => {
                setSearchQuery('');
                setSearchResults([]);
              }}
              className="absolute right-3 top-1/2 -translate-y-1/2 text-[#8e8e93] hover:text-white transition-colors"
            >
              <X className="w-4 h-4" />
            </button>
          )}
        </div>

        {/* Search Results - iOS Bottom Sheet Style */}
        <AnimatePresence>
          {searchResults.length > 0 && (
            <motion.div
              initial={{ opacity: 0, y: -10 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -10 }}
              className="mt-3 bg-[#2c2c2e] rounded-2xl overflow-hidden shadow-xl"
            >
              {searchResults.map((place, index) => (
                <button
                  key={place.place_name}
                  onClick={() => handleSelectPlace(place)}
                  className={`w-full text-left px-4 py-3 hover:bg-[#3a3a3c] transition-colors ${
                    index !== searchResults.length - 1 ? 'border-b border-[#3a3a3c]' : ''
                  }`}
                >
                  <div className="flex items-start gap-3">
                    <MapPin className="w-4 h-4 mt-0.5 shrink-0" style={{ color: colors.primary }} />
                    <div className="flex-1 min-w-0">
                      <p className="text-sm text-white font-medium truncate">{place.place_name.split(',')[0]}</p>
                      <p className="text-xs text-[#8e8e93] truncate">{place.place_name}</p>
                    </div>
                  </div>
                </button>
              ))}
            </motion.div>
          )}
        </AnimatePresence>
      </div>

      {/* Map */}
      <div className="h-[50vh] min-h-[400px] max-h-[600px] relative bg-[#1c1c1e]">
        {/* Map Style Selector - iOS Style */}
        <div className="absolute top-4 left-4 z-10">
          <AnimatePresence>
            {showStyleSelector && (
              <motion.div
                initial={{ opacity: 0, scale: 0.9, y: -10 }}
                animate={{ opacity: 1, scale: 1, y: 0 }}
                exit={{ opacity: 0, scale: 0.9, y: -10 }}
                className="bg-[#2c2c2e]/95 backdrop-blur-xl rounded-2xl p-2 shadow-xl border border-[#3a3a3c] mb-2"
              >
                {[
                  { id: 'streets-v12', name: 'Streets', url: 'mapbox://styles/mapbox/streets-v12' },
                  { id: 'dark-v11', name: 'Dark', url: 'mapbox://styles/mapbox/dark-v11' },
                  { id: 'light-v11', name: 'Light', url: 'mapbox://styles/mapbox/light-v11' },
                  { id: 'satellite-v9', name: 'Satellite', url: 'mapbox://styles/mapbox/satellite-v9' },
                  { id: 'satellite-streets-v12', name: 'Satellite + Labels', url: 'mapbox://styles/mapbox/satellite-streets-v12' },
                ].map((style) => (
                  <button
                    key={style.id}
                    onClick={() => {
                      setMapStyle(style.url);
                      setShowStyleSelector(false);
                    }}
                    className={`w-full text-left px-4 py-2.5 rounded-xl text-sm font-medium transition-colors mb-1 last:mb-0 ${
                      mapStyle === style.url
                        ? 'text-white'
                        : 'text-white hover:bg-[#3a3a3c]'
                    }`}
                    style={mapStyle === style.url ? { backgroundColor: colors.primary } : {}}
                  >
                    {style.name}
                  </button>
                ))}
              </motion.div>
            )}
          </AnimatePresence>
          <button
            onClick={() => setShowStyleSelector(!showStyleSelector)}
            className="bg-[#2c2c2e]/95 backdrop-blur-xl rounded-2xl p-3 shadow-lg border border-[#3a3a3c] hover:bg-[#3a3a3c] transition-colors"
          >
            <Layers className="w-5 h-5 text-white" />
          </button>
        </div>

        <Map
          {...viewState}
          onMove={(evt: ViewStateChangeEvent) => setViewState(evt.viewState)}
          onClick={handleMapClick}
          mapStyle={mapStyle}
          mapboxAccessToken={MAPBOX_TOKEN}
          maxZoom={20}
          onError={() => setMapError(true)}
          performanceMetricsCollection={false}
        >
          <NavigationControl 
            position="top-right" 
            showCompass={false}
            style={{ position: 'absolute', top: '16px', right: '16px' }}
          />
          <GeolocateControl 
            position="top-right" 
            trackUserLocation
            showUserHeading
            style={{ position: 'absolute', top: '80px', right: '16px' }}
          />
          
          {markerPosition && (
            <Marker
              longitude={markerPosition.lng}
              latitude={markerPosition.lat}
              anchor="bottom"
            >
              <motion.div
                initial={{ scale: 0 }}
                animate={{ scale: 1 }}
                className="relative"
              >
                <div 
                  className="w-10 h-10 rounded-full flex items-center justify-center shadow-lg"
                  style={{ backgroundColor: colors.primary, boxShadow: `0 10px 25px -5px ${colors.primary}50` }}
                >
                  <MapPin className="w-5 h-5 text-white" />
                </div>
              </motion.div>
            </Marker>
          )}
        </Map>
      </div>

      {/* Selected Location Info - iOS Card Style */}
      <AnimatePresence>
        {markerPosition && (
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: 20 }}
            className="p-4 bg-[#2c2c2e] border-t border-[#3a3a3c]"
          >
            <div className="bg-[#1c1c1e] rounded-2xl p-4 space-y-3">
              <div className="flex items-start gap-3">
                <MapPin className="w-5 h-5 mt-0.5 shrink-0" style={{ color: colors.primary }} />
                <div className="flex-1 min-w-0">
                  <p className="text-sm text-white font-medium truncate">{selectedAddress.address || 'Selected Location'}</p>
                  <p className="text-xs text-[#8e8e93]">{selectedAddress.city}, {selectedAddress.country}</p>
                </div>
              </div>
              <div className="pt-2 border-t border-[#3a3a3c]">
                <p className="text-xs text-[#8e8e93] font-mono">
                  {markerPosition.lat.toFixed(6)}, {markerPosition.lng.toFixed(6)}
                </p>
              </div>
            </div>
          </motion.div>
        )}
      </AnimatePresence>

      {/* Actions - iOS Style */}
      <div className="p-4 bg-[#2c2c2e] border-t border-[#3a3a3c] flex gap-3">
        <button
          onClick={onCancel}
          className="flex-1 py-3.5 bg-[#1c1c1e] rounded-xl text-sm font-semibold text-[#007AFF] hover:bg-[#2c2c2e] transition-colors"
        >
          Cancel
        </button>
        <button
          onClick={handleConfirm}
          disabled={!markerPosition}
          className="flex-1 py-3.5 rounded-xl text-sm font-semibold text-white transition-colors disabled:opacity-50 disabled:cursor-not-allowed shadow-lg"
          style={{ 
            backgroundColor: colors.primary,
            boxShadow: `0 10px 25px -5px ${colors.primary}50`
          }}
        >
          Confirm
        </button>
      </div>
    </div>
  );
}
