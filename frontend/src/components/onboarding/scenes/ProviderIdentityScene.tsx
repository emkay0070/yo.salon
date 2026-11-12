'use client';

import { useState } from 'react';
import { useOnboarding } from '@/contexts/OnboardingContext';
import { useCategory } from '@/contexts/CategoryContext';
import { motion } from 'framer-motion';
import { Upload } from 'lucide-react';
import SceneLayout from './SceneLayout';

export default function ProviderIdentityScene() {
  const { providerData, setProviderData } = useOnboarding();
  const { terminology, category } = useCategory();
  const [logoPreview, setLogoPreview] = useState<string | null>(providerData.logo || null);

  const handleLogoUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      const reader = new FileReader();
      reader.onloadend = () => {
        const result = reader.result as string;
        setLogoPreview(result);
        setProviderData({ ...providerData, logo: result });
      };
      reader.readAsDataURL(file);
    }
  };

  const handleNameChange = (name: string) => {
    setProviderData({ ...providerData, name });
  };

  const isValid = !!providerData.name.trim();

  return (
    <SceneLayout nextDisabled={!isValid} hint="You can change this at any time from Settings.">
      <div className="text-center mb-10">
        <p className="text-[#FFD700]/70 text-xs font-semibold tracking-widest uppercase mb-3">Your Space</p>
        <h2 className="text-3xl md:text-4xl font-bold text-white mb-3">
          What's your {category?.short_name?.toLowerCase() || category?.name?.toLowerCase() || 'space'} called?
        </h2>
        <p className="text-white/40 text-base">
          This is how your clients will discover you. Make it memorable.
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
            <div className="w-24 h-24 rounded-2xl bg-white/[0.04] border-2 border-dashed border-white/10 group-hover:border-[#FFD700]/30 transition-all duration-300 flex flex-col items-center justify-center">
              {logoPreview ? (
                <img src={logoPreview} alt="Logo preview" className="w-full h-full object-cover rounded-2xl" />
              ) : (
                <>
                  <Upload className="w-6 h-6 text-white/30 group-hover:text-[#FFD700] transition-colors duration-300" />
                  <p className="text-xs text-white/30 mt-2">Logo</p>
                </>
              )}
            </div>
          </label>
          <p className="text-white/30 text-xs mt-3">Optional — you can skip this</p>
        </div>

        {/* Name Input */}
        <div>
          <label className="block text-xs font-semibold text-white/40 tracking-widest uppercase mb-3">Space Name</label>
          <input
            type="text"
            value={providerData.name}
            onChange={(e) => handleNameChange(e.target.value)}
            placeholder={`e.g. Elite ${category?.short_name || category?.name || 'Studio'}, Luxe ${category?.short_name || category?.name || 'Salon'}…`}
            className="w-full px-5 py-4 rounded-2xl bg-white/[0.04] border border-white/[0.08] text-white placeholder-white/20 focus:outline-none focus:border-[#FFD700]/40 focus:bg-white/[0.06] transition-all duration-300"
          />
        </div>
      </div>
      {providerData.name && (
        <div className="mt-2 flex items-center justify-between text-xs px-1">
          <span className="text-white/40">
            yosalon.com/<span className="text-white/80">{providerData.draftSlug}</span>
          </span>
          {providerData.slugAvailable === false ? (
            <span className="text-red-400">Unavailable</span>
          ) : providerData.slugAvailable === true ? (
            <span className="text-green-400">Available</span>
          ) : (
            <span className="text-white/40">Checking...</span>
          )}
        </div>
      )}
    </SceneLayout>
  );
}
