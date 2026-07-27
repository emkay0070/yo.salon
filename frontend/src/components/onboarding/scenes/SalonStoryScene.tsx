'use client';

import { useOnboarding } from '@/contexts/OnboardingContext';
import SceneLayout from './SceneLayout';

const CATEGORIES = [
  'Hair Salon',
  'Barbershop',
  'Beauty Salon',
  'Nail Studio',
  'Spa',
  'Wellness Center',
  'Mobile Stylist',
];

const VIBES = [
  'Premium & Luxurious',
  'Modern & Minimalist',
  'Relaxed & Casual',
  'Fast & Efficient',
  'Creative & Edgy',
];

const TEAM_SIZES = ['Just me', '2-5 people', '6-10 people', '11+ people'];
const BUSINESS_TYPES = ['Independent', 'Franchise', 'Booth Renter'];
const BRANCHES_OPTIONS = ['Single location', 'Multiple locations'];

export default function SalonStoryScene() {
  const { salonData, setSalonData } = useOnboarding();

  const isValid = !!salonData.category;

  const handleChange = (field: keyof typeof salonData, value: string) => {
    setSalonData({ ...salonData, [field]: value });
  };

  return (
    <SceneLayout nextDisabled={!isValid} hint="Only category is strictly required right now.">
      <div className="text-center mb-10">
        <p className="text-[#FFD700]/70 text-xs font-semibold tracking-widest uppercase mb-3">Your Story</p>
        <h2 className="text-3xl md:text-4xl font-bold text-white mb-3">
          Tell us about your business
        </h2>
        <p className="text-white/40 text-base">
          This helps us personalize your experience and helps clients discover you.
        </p>
      </div>

      <div className="space-y-8 pb-10">
        {/* Story Section */}
        <div className="space-y-5">
          <h3 className="text-lg font-medium text-white/90">Public Profile</h3>
          
          <div>
            <label className="block text-xs font-semibold text-white/40 tracking-widest uppercase mb-2">
              Primary Category *
            </label>
            <select
              value={salonData.category}
              onChange={(e) => handleChange('category', e.target.value)}
              className="w-full px-5 py-4 rounded-2xl bg-white/[0.04] border border-white/[0.08] text-white focus:outline-none focus:border-[#FFD700]/40 focus:bg-white/[0.06] transition-all duration-300 appearance-none cursor-pointer"
            >
              <option value="" disabled className="text-black">Select category...</option>
              {CATEGORIES.map(c => (
                <option key={c} value={c} className="text-black">{c}</option>
              ))}
            </select>
          </div>

          <div>
            <label className="block text-xs font-semibold text-white/40 tracking-widest uppercase mb-2">
              Vibe / Atmosphere (Optional)
            </label>
            <select
              value={salonData.vibe}
              onChange={(e) => handleChange('vibe', e.target.value)}
              className="w-full px-5 py-4 rounded-2xl bg-white/[0.04] border border-white/[0.08] text-white focus:outline-none focus:border-[#FFD700]/40 focus:bg-white/[0.06] transition-all duration-300 appearance-none cursor-pointer"
            >
              <option value="" disabled className="text-black">Select vibe...</option>
              {VIBES.map(v => (
                <option key={v} value={v} className="text-black">{v}</option>
              ))}
            </select>
          </div>

          <div>
            <label className="block text-xs font-semibold text-white/40 tracking-widest uppercase mb-2">
              Description (Optional)
            </label>
            <textarea
              value={salonData.description ?? ''}
              onChange={(e) => handleChange('description', e.target.value)}
              placeholder="A short bio about what makes your salon special..."
              rows={3}
              className="w-full px-5 py-4 rounded-2xl bg-white/[0.04] border border-white/[0.08] text-white placeholder-white/20 focus:outline-none focus:border-[#FFD700]/40 focus:bg-white/[0.06] transition-all duration-300 resize-none"
            />
          </div>
        </div>

        <div className="w-full h-px bg-white/5" />

        {/* Business Intelligence Section */}
        <div className="space-y-5">
          <h3 className="text-lg font-medium text-white/90">Business Details (Optional)</h3>
          
          <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
              <label className="block text-xs font-semibold text-white/40 tracking-widest uppercase mb-2">
                Business Type
              </label>
              <select
                value={salonData.businessType}
                onChange={(e) => handleChange('businessType', e.target.value)}
                className="w-full px-5 py-4 rounded-2xl bg-white/[0.04] border border-white/[0.08] text-white text-sm focus:outline-none focus:border-[#FFD700]/40 appearance-none cursor-pointer"
              >
                <option value="" disabled className="text-black">Select...</option>
                {BUSINESS_TYPES.map(t => <option key={t} value={t} className="text-black">{t}</option>)}
              </select>
            </div>

            <div>
              <label className="block text-xs font-semibold text-white/40 tracking-widest uppercase mb-2">
                Team Size
              </label>
              <select
                value={salonData.teamSize}
                onChange={(e) => handleChange('teamSize', e.target.value)}
                className="w-full px-5 py-4 rounded-2xl bg-white/[0.04] border border-white/[0.08] text-white text-sm focus:outline-none focus:border-[#FFD700]/40 appearance-none cursor-pointer"
              >
                <option value="" disabled className="text-black">Select...</option>
                {TEAM_SIZES.map(s => <option key={s} value={s} className="text-black">{s}</option>)}
              </select>
            </div>
            
            <div className="md:col-span-2">
              <label className="block text-xs font-semibold text-white/40 tracking-widest uppercase mb-2">
                Locations
              </label>
              <div className="flex gap-3">
                {BRANCHES_OPTIONS.map(opt => (
                  <button
                    key={opt}
                    onClick={() => handleChange('branches', opt)}
                    className={`flex-1 py-4 rounded-xl border text-sm font-medium transition-all duration-300 ${
                      salonData.branches === opt 
                        ? 'border-[#FFD700]/50 bg-[#FFD700]/10 text-[#FFD700]' 
                        : 'border-white/[0.08] bg-white/[0.02] text-white/60 hover:bg-white/[0.05]'
                    }`}
                  >
                    {opt}
                  </button>
                ))}
              </div>
            </div>
          </div>
        </div>

      </div>
    </SceneLayout>
  );
}
