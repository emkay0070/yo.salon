'use client';

import { useState } from 'react';
import { useOnboarding } from '@/contexts/OnboardingContext';
import { motion } from 'framer-motion';
import { Upload } from 'lucide-react';
import SceneLayout from './SceneLayout';

export default function SalonIdentityScene() {
  const { salonData, setSalonData } = useOnboarding();
  const [logoPreview, setLogoPreview] = useState<string | null>(salonData.logo || null);

  const handleLogoUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      const reader = new FileReader();
      reader.onloadend = () => {
        const result = reader.result as string;
        setLogoPreview(result);
        setSalonData({ ...salonData, logo: result });
      };
      reader.readAsDataURL(file);
    }
  };

  const handleNameChange = (name: string) => {
    setSalonData({ ...salonData, name });
  };

  const isValid = !!salonData.name.trim();

  return (
    <SceneLayout nextDisabled={!isValid} hint="You can change this at any time from Settings.">
      <div className="text-center mb-10">
        <p className="text-[#FFD700]/70 text-xs font-semibold tracking-widest uppercase mb-3">Your Salon</p>
        <h2 className="text-3xl md:text-4xl font-bold text-white mb-3">
          What's your salon called?
        </h2>
        <p className="text-white/40 text-base">
          This is what clients will see when booking. Make it memorable.
        </p>
      </div>

      <div className="space-y-6">
        {/* Logo Upload */}
        <div className="flex flex-col items-center">
          <label className="cursor-pointer group">
            <input
              type="file"
              accept="image/*"
              onChange={handleLogoUpload}
              className="hidden"
            />
            <motion.div
              whileHover={{ scale: 1.03 }}
              whileTap={{ scale: 0.97 }}
              className="w-24 h-24 rounded-2xl border-2 border-dashed border-white/10 group-hover:border-[#FFD700]/40 transition-colors duration-300 flex items-center justify-center overflow-hidden bg-white/[0.03] relative"
            >
              {logoPreview ? (
                <img src={logoPreview} alt="Logo" className="w-full h-full object-cover" />
              ) : (
                <div className="text-center">
                  <Upload className="w-6 h-6 text-white/20 mx-auto mb-1 group-hover:text-[#FFD700]/40 transition-colors" />
                  <span className="text-white/20 text-[10px] group-hover:text-[#FFD700]/40 transition-colors">Logo</span>
                </div>
              )}
            </motion.div>
          </label>
          <p className="text-white/20 text-xs mt-2">Optional — you can skip this</p>
        </div>

        {/* Salon Name */}
        <div>
          <label className="block text-xs font-semibold text-white/40 tracking-widest uppercase mb-2">
            Salon Name
          </label>
          <input
            type="text"
            value={salonData.name}
            onChange={(e) => handleNameChange(e.target.value)}
            placeholder="e.g. Elite Cuts, Luxe Studio…"
            autoFocus
            className="w-full px-5 py-4 rounded-2xl bg-white/[0.04] border border-white/[0.08] text-white placeholder-white/20 text-lg focus:outline-none focus:border-[#FFD700]/40 focus:bg-white/[0.06] transition-all duration-300"
          />
          {salonData.name && (
            <div className="mt-2 flex items-center justify-between text-xs px-1">
              <span className="text-white/40">
                yosalon.com/<span className="text-white/80">{salonData.draftSlug}</span>
              </span>
              {salonData.slugAvailable === false ? (
                <span className="text-red-400">Unavailable</span>
              ) : salonData.slugAvailable === true ? (
                <span className="text-green-400">Available</span>
              ) : (
                <span className="text-white/40">Checking...</span>
              )}
            </div>
          )}
        </div>
      </div>
    </SceneLayout>
  );
}
