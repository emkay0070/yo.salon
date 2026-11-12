'use client';

import { useState, useEffect, useMemo, useRef } from 'react';
import { useRouter } from 'next/navigation';
import Map, { Marker, Popup, NavigationControl, FullscreenControl, ScaleControl, GeolocateControl, ViewStateChangeEvent } from 'react-map-gl/mapbox';
import { Search, MapPin, Star, ArrowRight, Loader2 } from 'lucide-react';
import { apiClient } from '@/lib/api-client';

const MAPBOX_TOKEN = process.env.NEXT_PUBLIC_MAPBOX_TOKEN;

interface Provider {
  id: string;
  name: string;
  display_name?: string; // Backend returns display_name
  slug: string;
  address?: string;
  city?: string;
  lat: number;
  lng: number;
}

interface ProviderDiscoveryMapProps {
  onProviderSelect?: (provider: Provider) => void;
}

export function ProviderDiscoveryMap({ onProviderSelect }: ProviderDiscoveryMapProps = {}) {
  const router = useRouter();
  const [providers, setProviders] = useState<Provider[]>([]);
  const [loading, setLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedProvider, setSelectedProvider] = useState<Provider | null>(null);
  const [mapStyle, setMapStyle] = useState('dark-v11');
  const [mapError, setMapError] = useState(false);
  
  // Default to Kampala, Uganda
  const [viewState, setViewState] = useState({
    longitude: 32.5825,
    latitude: 0.3476,
    zoom: 12
  });

  useEffect(() => {
    async function loadProviders() {
      try {
        const response = await apiClient.get('/v1/salons');
        // Handle paginated response - extract data array
        const data = Array.isArray(response) ? response : (response?.data || []);
        // Use real coordinates if available, otherwise fallback to mock coordinates near Kampala
        const mappedProviders = data.map((provider: any, i: number) => ({
          ...provider,
          name: provider.display_name || provider.name,
          lat: provider.lat ? parseFloat(provider.lat) : 0.3476 + (Math.sin(i) * 0.05),
          lng: provider.lng ? parseFloat(provider.lng) : 32.5825 + (Math.cos(i) * 0.05)
        }));
        setProviders(mappedProviders);
      } catch (error) {
        console.error("Failed to fetch providers:", error);
      } finally {
        setLoading(false);
      }
    }
    loadProviders();
  }, []);

  const filteredProviders = useMemo(() => {
    return providers.filter(provider => 
      provider.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
      (provider.city && provider.city.toLowerCase().includes(searchQuery.toLowerCase()))
    );
  }, [providers, searchQuery]);

  const mapStyles = [
    { id: 'streets-v12', name: 'Streets', url: 'mapbox://styles/mapbox/streets-v12' },
    { id: 'dark-v11', name: 'Dark', url: 'mapbox://styles/mapbox/dark-v11' },
    { id: 'light-v11', name: 'Light', url: 'mapbox://styles/mapbox/light-v11' },
    { id: 'satellite-v9', name: 'Satellite', url: 'mapbox://styles/mapbox/satellite-v9' },
    { id: 'satellite-streets-v12', name: 'Satellite + Labels', url: 'mapbox://styles/mapbox/satellite-streets-v12' },
    { id: 'outdoors-v12', name: 'Terrain', url: 'mapbox://styles/mapbox/outdoors-v12' },
  ];

  if (!MAPBOX_TOKEN) {
    return (
      <div className="flex flex-col items-center justify-center min-h-screen p-12 bg-black text-white">
        <p className="text-red-400 font-mono">Mapbox Token is missing in environment variables.</p>
      </div>
    );
  }

  if (mapError) {
    return (
      <div className="flex flex-col items-center justify-center min-h-screen p-12 bg-black text-white">
        <p className="text-white/70 mb-4">Map unavailable due to connection error</p>
        <button
          onClick={() => setMapError(false)}
          className="px-4 py-2 bg-gold text-black rounded-lg font-semibold hover:bg-[#C9A227] transition-colors"
        >
          Retry
        </button>
      </div>
    );
  }

  return (
    <div className="relative w-full h-screen bg-[#070707] font-poppins flex flex-col md:flex-row">
      {/* Sidebar for Search and Results */}
      <div className="w-full md:w-[400px] h-[40vh] md:h-full bg-black/80 backdrop-blur-xl border-b md:border-b-0 md:border-r border-white/10 z-10 flex flex-col shadow-2xl relative order-2 md:order-1">
        <div className="p-6 border-b border-white/5">
          <h2 className="text-2xl font-sora font-extrabold text-white mb-2 tracking-tight">Discover Providers</h2>
          <p className="text-white/50 text-sm mb-6">Find luxury experiences near you.</p>
          
          <div className="relative">
            <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-white/40" />
            <input 
              type="text" 
              placeholder="Search by name or location..." 
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="w-full pl-10 pr-4 py-3 bg-white/[0.03] border border-white/10 rounded-xl text-white placeholder:text-white/30 focus:outline-none focus:border-gold focus:bg-white/[0.05] transition-all text-sm font-medium"
            />
          </div>
        </div>

        <div className="flex-1 overflow-y-auto p-4 space-y-3 custom-scrollbar">
          {loading ? (
            <div className="flex items-center justify-center p-8">
              <Loader2 className="w-6 h-6 text-gold animate-spin" />
            </div>
          ) : filteredProviders.length === 0 ? (
            <div className="text-center p-8 text-white/40 text-sm font-medium">
              No providers found matching your search.
            </div>
          ) : (
            filteredProviders.map(provider => (
              <button 
                key={provider.id}
                onClick={() => {
                  setSelectedProvider(provider);
                  setViewState({ ...viewState, longitude: provider.lng, latitude: provider.lat, zoom: 14 });
                }}
                className={`w-full text-left p-4 rounded-xl border transition-all duration-300 ${
                  selectedProvider?.id === provider.id 
                    ? 'bg-gold/10 border-gold/50 shadow-[0_0_15px_rgba(255,215,0,0.1)]' 
                    : 'bg-white/[0.02] border-white/5 hover:border-white/20'
                }`}
              >
                <h3 className="text-white font-sora font-semibold text-base">{provider.name}</h3>
                <p className="text-white/50 text-xs mt-1 flex items-center gap-1.5">
                  <MapPin className="w-3 h-3 text-gold" />
                  {provider.address || provider.city || 'Kampala, Uganda'}
                </p>
                <div className="flex items-center justify-between mt-4">
                  <div className="flex items-center gap-1">
                    <Star className="w-3.5 h-3.5 text-gold fill-gold" />
                    <span className="text-white/80 text-[11px] font-medium">4.9</span>
                  </div>
                  {selectedProvider?.id === provider.id && (
                    <div
                      onClick={(e) => {
                        e.stopPropagation();
                        if (onProviderSelect) {
                          onProviderSelect(provider);
                        } else {
                          router.push(`/book?provider_slug=${provider.slug}`);
                        }
                      }}
                      className="flex items-center gap-1.5 text-black bg-gradient-to-r from-gold to-[#C9A227] px-3 py-1.5 rounded-lg text-xs font-semibold hover:brightness-110 active:scale-95 transition-all shadow-md shadow-gold/20"
                    >
                      Book Now <ArrowRight className="w-3 h-3" />
                    </div>
                  )}
                </div>
              </button>
            ))
          )}
        </div>
      </div>

      {/* Map Area */}
      <div className="flex-1 h-[60vh] md:h-full relative order-1 md:order-2 bg-[#1a1a1a]">
        {/* Map Style Selector */}
        <div className="absolute top-4 left-4 z-10 bg-black/80 backdrop-blur-xl border border-white/10 rounded-xl p-2 flex gap-2">
          {mapStyles.map((style) => (
            <button
              key={style.id}
              onClick={() => setMapStyle(style.url)}
              className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-colors ${
                mapStyle === style.url
                  ? 'bg-gold text-black'
                  : 'text-white/70 hover:bg-white/10 hover:text-white'
              }`}
            >
              {style.name}
            </button>
          ))}
        </div>

        <Map
          {...viewState}
          onMove={(evt: ViewStateChangeEvent) => setViewState(evt.viewState)}
          mapStyle={mapStyle}
          mapboxAccessToken={MAPBOX_TOKEN}
          onError={() => setMapError(true)}
          performanceMetricsCollection={false}
        >
          <NavigationControl position="top-right" />
          <FullscreenControl position="top-right" />
          <ScaleControl position="bottom-left" maxWidth={100} unit="metric" />
          <GeolocateControl
            position="top-right"
            trackUserLocation
            showUserHeading
          />
          
          {filteredProviders.map(provider => (
            <Marker 
              key={provider.id} 
              longitude={provider.lng} 
              latitude={provider.lat} 
              anchor="bottom"
              onClick={(e: any) => {
                e.originalEvent.stopPropagation();
                setSelectedProvider(provider);
                setViewState({ ...viewState, longitude: provider.lng, latitude: provider.lat, zoom: 14 });
              }}
            >
              <div className={`cursor-pointer transition-transform duration-300 ${selectedProvider?.id === provider.id ? 'scale-125' : 'hover:scale-110'}`}>
                <div className="relative">
                  <div className="absolute inset-0 bg-gold rounded-full blur-sm opacity-50 animate-pulse"></div>
                  <div className="w-8 h-8 bg-black border-2 border-gold rounded-full flex items-center justify-center shadow-[0_0_15px_rgba(255,215,0,0.5)] relative z-10">
                    <MapPin className="w-4 h-4 text-gold" />
                  </div>
                </div>
              </div>
            </Marker>
          ))}

          {selectedProvider && (
            <Popup
              longitude={selectedProvider.lng}
              latitude={selectedProvider.lat}
              anchor="bottom"
              offset={40}
              onClose={() => setSelectedProvider(null)}
              closeButton={true}
              closeOnClick={false}
              className="provider-popup"
              maxWidth="300px"
            >
              <div className="p-2 font-poppins text-left">
                <h3 className="font-sora font-bold text-sm text-gray-900 mb-1">{selectedProvider.name}</h3>
                <p className="text-xs text-gray-500 mb-3">{selectedProvider.address || 'Premium Location'}</p>
                <button
                  onClick={() => {
                    if (onProviderSelect) {
                      onProviderSelect(selectedProvider);
                    } else {
                      router.push(`/book?provider_slug=${selectedProvider.slug}`);
                    }
                  }}
                  className="w-full bg-black text-gold py-2 rounded-lg text-xs font-semibold flex items-center justify-center gap-2 hover:bg-gray-900 transition-colors cursor-pointer"
                >
                  Book Appointment <ArrowRight className="w-3 h-3" />
                </button>
              </div>
            </Popup>
          )}
        </Map>
        
        {/* Global UI styling overrides for Mapbox popups to make them fit the theme slightly better */}
        <style dangerouslySetInnerHTML={{__html: `
          .mapboxgl-popup-content {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            border: 1px solid rgba(0,0,0,0.1);
          }
          .mapboxgl-popup-tip {
            border-top-color: rgba(255, 255, 255, 0.95) !important;
          }
          .mapboxgl-popup-close-button {
            color: #666;
            font-size: 16px;
            padding: 4px 8px;
            border-radius: 0 12px 0 8px;
          }
          .mapboxgl-popup-close-button:hover {
            background-color: rgba(0,0,0,0.05);
            color: #000;
          }
        `}} />
      </div>
    </div>
  );
}
