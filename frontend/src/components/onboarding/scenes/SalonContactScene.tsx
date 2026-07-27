'use client';

import { useOnboarding } from '@/contexts/OnboardingContext';
import { Phone, Mail, MapPin, Clock, DollarSign } from 'lucide-react';
import SceneLayout from './SceneLayout';
import { useState, useCallback } from 'react';
import Map, { Marker, NavigationControl } from 'react-map-gl';
import 'mapbox-gl/dist/mapbox-gl.css';

const MAPBOX_TOKEN = process.env.NEXT_PUBLIC_MAPBOX_TOKEN;

const timezones = [
  'Africa/Kampala',
  'Africa/Nairobi',
  'Africa/Lagos',
  'Africa/Johannesburg',
  'Europe/London',
  'America/New_York',
  'America/Los_Angeles',
];

const currencies = [
  { code: 'UGX', name: 'Ugandan Shilling' },
  { code: 'KES', name: 'Kenyan Shilling' },
  { code: 'NGN', name: 'Nigerian Naira' },
  { code: 'USD', name: 'US Dollar' },
  { code: 'EUR', name: 'Euro' },
  { code: 'GBP', name: 'British Pound' },
];

const inputClass =
  'w-full px-5 py-4 rounded-2xl bg-white/[0.04] border border-white/[0.08] text-white placeholder-white/20 focus:outline-none focus:border-[#FFD700]/40 focus:bg-white/[0.06] transition-all duration-300';

const iconClass = 'absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-white/25 pointer-events-none';

export default function SalonContactScene() {
  const { salonData, setSalonData } = useOnboarding();

  const update = (field: string, value: any) => {
    setSalonData({ ...salonData, [field]: value });
  };

  const [viewState, setViewState] = useState({
    longitude: salonData.lng || 32.5825,
    latitude: salonData.lat || 0.3476,
    zoom: 12
  });

  const handleMapClick = useCallback((e: any) => {
    const { lng, lat } = e.lngLat;
    setSalonData({ ...salonData, lat, lng });
  }, [salonData, setSalonData]);

  const isValid = salonData.phone && salonData.email && salonData.address && salonData.lat && salonData.lng;

  return (
    <SceneLayout nextDisabled={!isValid}>
      <div className="text-center mb-10">
        <p className="text-[#FFD700]/70 text-xs font-semibold tracking-widest uppercase mb-3">Your Salon</p>
        <h2 className="text-3xl md:text-4xl font-bold text-white mb-3">
          How do clients find you?
        </h2>
        <p className="text-white/40 text-base">
          Contact details and location — shown to clients when they book.
        </p>
      </div>

      <div className="space-y-4">
        {/* Phone */}
        <div>
          <label className="block text-xs font-semibold text-white/40 tracking-widest uppercase mb-2">Phone</label>
          <div className="relative">
            <Phone className={iconClass} />
            <input
              type="tel"
              value={salonData.phone}
              onChange={(e) => update('phone', e.target.value)}
              placeholder="+256 700 000 000"
              className={`${inputClass} pl-11`}
            />
          </div>
        </div>

        {/* Email */}
        <div>
          <label className="block text-xs font-semibold text-white/40 tracking-widest uppercase mb-2">Email</label>
          <div className="relative">
            <Mail className={iconClass} />
            <input
              type="email"
              value={salonData.email}
              onChange={(e) => update('email', e.target.value)}
              placeholder="hello@yoursalon.com"
              className={`${inputClass} pl-11`}
            />
          </div>
        </div>

        {/* Geolocation Detection & Map */}
        <div>
          <div className="flex items-center justify-between mb-2">
            <label className="block text-xs font-semibold text-white/40 tracking-widest uppercase">Salon Location</label>
            <button
              type="button"
              onClick={() => {
                if (navigator.geolocation) {
                  navigator.geolocation.getCurrentPosition(
                    (position) => {
                      const { latitude, longitude } = position.coords;
                      setSalonData({
                        ...salonData,
                        lat: latitude,
                        lng: longitude,
                        address: `GPS: ${latitude.toFixed(4)}, ${longitude.toFixed(4)}`
                      });
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
                      setSalonData({
                        ...salonData,
                        lat: defaultLat,
                        lng: defaultLng,
                        address: 'Kampala, Uganda (Default)'
                      });
                      setViewState({
                        longitude: defaultLng,
                        latitude: defaultLat,
                        zoom: 12
                      });
                    }
                  );
                }
              }}
              className="text-xs text-[#FFD700] hover:text-[#FFD700]/80 font-semibold flex items-center gap-1.5 transition-colors"
            >
              <MapPin className="w-3.5 h-3.5" />
              Detect Location Automatically
            </button>
          </div>

          {MAPBOX_TOKEN ? (
            <div className="w-full h-64 rounded-2xl overflow-hidden border border-white/[0.08] relative mb-3">
              <Map
                {...viewState}
                onMove={evt => setViewState(evt.viewState)}
                onClick={handleMapClick}
                mapboxAccessToken={MAPBOX_TOKEN}
                mapStyle="mapbox://styles/mapbox/dark-v11"
                cursor="crosshair"
              >
                <NavigationControl position="bottom-right" />
                
                {salonData.lat && salonData.lng && (
                  <Marker 
                    longitude={salonData.lng} 
                    latitude={salonData.lat} 
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
              {!salonData.lat && (
                <div className="absolute inset-0 pointer-events-none flex items-center justify-center bg-black/40">
                  <div className="bg-black/80 backdrop-blur-md px-4 py-2.5 rounded-lg border border-white/10 text-white/70 text-sm font-medium">
                    Click "Detect Location" or click on the map to place your pin
                  </div>
                </div>
              )}
            </div>
          ) : (
            <div className="w-full h-32 rounded-2xl bg-white/[0.02] border border-white/[0.08] flex items-center justify-center text-white/30 text-sm mb-3">
              Mapbox token not configured
            </div>
          )}

          {salonData.lat && salonData.lng && (
            <div className="text-[10px] text-white/40 flex items-center gap-1.5 px-1">
              <span className="w-1.5 h-1.5 rounded-full bg-[#FFD700] animate-pulse"></span>
              Pinned coordinates: {salonData.lat.toFixed(5)}, {salonData.lng.toFixed(5)} (Click map to adjust)
            </div>
          )}
        </div>

        {/* Timezone + Currency row */}
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-xs font-semibold text-white/40 tracking-widest uppercase mb-2">Timezone</label>
            <div className="relative">
              <Clock className={iconClass} />
              <select
                value={salonData.timezone}
                onChange={(e) => update('timezone', e.target.value)}
                className={`${inputClass} pl-11 appearance-none cursor-pointer`}
              >
                {timezones.map((tz) => (
                  <option key={tz} value={tz} className="bg-[#0e0e12]">{tz}</option>
                ))}
              </select>
            </div>
          </div>

          <div>
            <label className="block text-xs font-semibold text-white/40 tracking-widest uppercase mb-2">Currency</label>
            <div className="relative">
              <DollarSign className={iconClass} />
              <select
                value={salonData.currency}
                onChange={(e) => update('currency', e.target.value)}
                className={`${inputClass} pl-11 appearance-none cursor-pointer`}
              >
                {currencies.map((c) => (
                  <option key={c.code} value={c.code} className="bg-[#0e0e12]">{c.code} — {c.name}</option>
                ))}
              </select>
            </div>
          </div>
        </div>
      </div>
    </SceneLayout>
  );
}
