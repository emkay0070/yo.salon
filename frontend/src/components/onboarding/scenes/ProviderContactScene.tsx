'use client';

import { useOnboarding } from '@/contexts/OnboardingContext';
import { useCategory } from '@/contexts/CategoryContext';
import { Phone, Mail, Clock, DollarSign } from 'lucide-react';
import SceneLayout from './SceneLayout';
import LocationPicker from '@/components/shared/LocationPicker';
import { useCallback } from 'react';

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

export default function ProviderContactScene() {
  const { providerData, setProviderData } = useOnboarding();
  const { terminology } = useCategory();

  const update = (field: string, value: any) => {
    setProviderData({ ...providerData, [field]: value });
  };

  const handleLocationChange = useCallback((lat: number, lng: number) => {
    setProviderData({ ...providerData, lat, lng });
  }, [providerData, setProviderData]);

  const isValid = providerData.email && providerData.lat && providerData.lng;

  return (
    <SceneLayout nextDisabled={!isValid}>
      <div className="text-center mb-10">
        <p className="text-[#FFD700]/70 text-xs font-semibold tracking-widest uppercase mb-3">Your {terminology.business}</p>
        <h2 className="text-3xl md:text-4xl font-bold text-white mb-3">
          Where is your {terminology.business.toLowerCase()} located?
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
              value={providerData.phone}
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
              value={providerData.email}
              onChange={(e) => update('email', e.target.value)}
              placeholder="hello@yoursalon.com"
              className={`${inputClass} pl-11`}
            />
          </div>
        </div>

        {/* Geolocation Detection & Map */}
        <LocationPicker
          lat={providerData.lat}
          lng={providerData.lng}
          onLocationChange={handleLocationChange}
        />

        {/* Timezone + Currency row */}
        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-xs font-semibold text-white/40 tracking-widest uppercase mb-2">Timezone</label>
            <div className="relative">
              <Clock className={iconClass} />
              <select
                value={providerData.timezone}
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
                value={providerData.currency}
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
