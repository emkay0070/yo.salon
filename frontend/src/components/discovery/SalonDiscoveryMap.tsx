'use client';

import { useState, useEffect, useMemo, useRef } from 'react';
import { useRouter } from 'next/navigation';
import Map, { Marker, Popup, NavigationControl, ViewStateChangeEvent } from 'react-map-gl';
import 'mapbox-gl/dist/mapbox-gl.css';
import { Search, MapPin, Star, ArrowRight, Loader2 } from 'lucide-react';
import { apiClient } from '@/lib/api-client';

const MAPBOX_TOKEN = process.env.NEXT_PUBLIC_MAPBOX_TOKEN;

interface Salon {
  id: string;
  name: string;
  slug: string;
  address?: string;
  city?: string;
  lat: number;
  lng: number;
}

export function SalonDiscoveryMap() {
  const router = useRouter();
  const [salons, setSalons] = useState<Salon[]>([]);
  const [loading, setLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedSalon, setSelectedSalon] = useState<Salon | null>(null);
  
  // Default to Kampala, Uganda
  const [viewState, setViewState] = useState({
    longitude: 32.5825,
    latitude: 0.3476,
    zoom: 12
  });

  useEffect(() => {
    async function loadSalons() {
      try {
        const data = await apiClient.getSalons();
        // Use real coordinates if available, otherwise fallback to mock coordinates near Kampala
        const mappedSalons = data.map((salon: any, i: number) => ({
          ...salon,
          lat: salon.lat ? parseFloat(salon.lat) : 0.3476 + (Math.sin(i) * 0.05),
          lng: salon.lng ? parseFloat(salon.lng) : 32.5825 + (Math.cos(i) * 0.05)
        }));
        setSalons(mappedSalons);
      } catch (error) {
        console.error("Failed to fetch salons:", error);
      } finally {
        setLoading(false);
      }
    }
    loadSalons();
  }, []);

  const filteredSalons = useMemo(() => {
    return salons.filter(salon => 
      salon.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
      (salon.city && salon.city.toLowerCase().includes(searchQuery.toLowerCase()))
    );
  }, [salons, searchQuery]);

  if (!MAPBOX_TOKEN) {
    return (
      <div className="flex flex-col items-center justify-center min-h-screen p-12 bg-black text-white">
        <p className="text-red-400 font-mono">Mapbox Token is missing in environment variables.</p>
      </div>
    );
  }

  return (
    <div className="relative w-full h-screen bg-[#070707] font-poppins flex flex-col md:flex-row">
      {/* Sidebar for Search and Results */}
      <div className="w-full md:w-[400px] h-[40vh] md:h-full bg-black/80 backdrop-blur-xl border-b md:border-b-0 md:border-r border-white/10 z-10 flex flex-col shadow-2xl relative order-2 md:order-1">
        <div className="p-6 border-b border-white/5">
          <h2 className="text-2xl font-sora font-extrabold text-white mb-2 tracking-tight">Discover Salons</h2>
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
          ) : filteredSalons.length === 0 ? (
            <div className="text-center p-8 text-white/40 text-sm font-medium">
              No salons found matching your search.
            </div>
          ) : (
            filteredSalons.map(salon => (
              <button 
                key={salon.id}
                onClick={() => {
                  setSelectedSalon(salon);
                  setViewState({ ...viewState, longitude: salon.lng, latitude: salon.lat, zoom: 14 });
                }}
                className={`w-full text-left p-4 rounded-xl border transition-all duration-300 ${
                  selectedSalon?.id === salon.id 
                    ? 'bg-gold/10 border-gold/50 shadow-[0_0_15px_rgba(255,215,0,0.1)]' 
                    : 'bg-white/[0.02] border-white/5 hover:border-white/20'
                }`}
              >
                <h3 className="text-white font-sora font-semibold text-base">{salon.name}</h3>
                <p className="text-white/50 text-xs mt-1 flex items-center gap-1.5">
                  <MapPin className="w-3 h-3 text-gold" />
                  {salon.address || salon.city || 'Kampala, Uganda'}
                </p>
                <div className="flex items-center justify-between mt-4">
                  <div className="flex items-center gap-1">
                    <Star className="w-3.5 h-3.5 text-gold fill-gold" />
                    <span className="text-white/80 text-[11px] font-medium">4.9</span>
                  </div>
                  {selectedSalon?.id === salon.id && (
                    <div 
                      onClick={(e) => { e.stopPropagation(); router.push(`/book?salon=${salon.slug}`); }}
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
        <Map
          {...viewState}
          onMove={(evt: ViewStateChangeEvent) => setViewState(evt.viewState)}
          mapStyle="mapbox://styles/mapbox/dark-v11"
          mapboxAccessToken={MAPBOX_TOKEN}
        >
          <NavigationControl position="top-right" />
          
          {filteredSalons.map(salon => (
            <Marker 
              key={salon.id} 
              longitude={salon.lng} 
              latitude={salon.lat} 
              anchor="bottom"
              onClick={(e: any) => {
                e.originalEvent.stopPropagation();
                setSelectedSalon(salon);
                setViewState({ ...viewState, longitude: salon.lng, latitude: salon.lat, zoom: 14 });
              }}
            >
              <div className={`cursor-pointer transition-transform duration-300 ${selectedSalon?.id === salon.id ? 'scale-125' : 'hover:scale-110'}`}>
                <div className="relative">
                  <div className="absolute inset-0 bg-gold rounded-full blur-sm opacity-50 animate-pulse"></div>
                  <div className="w-8 h-8 bg-black border-2 border-gold rounded-full flex items-center justify-center shadow-[0_0_15px_rgba(255,215,0,0.5)] relative z-10">
                    <MapPin className="w-4 h-4 text-gold" />
                  </div>
                </div>
              </div>
            </Marker>
          ))}

          {selectedSalon && (
            <Popup
              longitude={selectedSalon.lng}
              latitude={selectedSalon.lat}
              anchor="bottom"
              offset={40}
              onClose={() => setSelectedSalon(null)}
              closeButton={true}
              closeOnClick={false}
              className="salon-popup"
              maxWidth="300px"
            >
              <div className="p-2 font-poppins text-left">
                <h3 className="font-sora font-bold text-sm text-gray-900 mb-1">{selectedSalon.name}</h3>
                <p className="text-xs text-gray-500 mb-3">{selectedSalon.address || 'Premium Location'}</p>
                <button 
                  onClick={() => router.push(`/book?salon=${selectedSalon.slug}`)}
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
