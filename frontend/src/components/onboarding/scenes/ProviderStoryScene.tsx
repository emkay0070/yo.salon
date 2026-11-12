'use client';

import { useOnboarding } from '@/contexts/OnboardingContext';
import SceneLayout from './SceneLayout';

export default function ProviderStoryScene() {
  const { providerData, setProviderData } = useOnboarding();

  const handleChange = (field: keyof typeof providerData, value: string) => {
    setProviderData({ ...providerData, [field]: value });
  };

  return (
    <SceneLayout nextDisabled={false} hint="This field is optional. You can skip for now.">
      <div className="text-center mb-10">
        <p className="text-[#FFD700]/70 text-xs font-semibold tracking-widest uppercase mb-3">Your Story</p>
        <h2 className="text-3xl md:text-4xl font-bold text-white mb-3">
          Tell us about your business
        </h2>
        <p className="text-white/40 text-base">
          This helps clients discover you and understand what makes your space unique.
        </p>
      </div>

      <div className="space-y-8 pb-10">
        {/* Story Section */}
        <div className="space-y-5">
          <h3 className="text-lg font-medium text-white/90">Public Profile</h3>

          <div>
            <label className="block text-xs font-semibold text-white/40 tracking-widest uppercase mb-2">Description</label>
            <textarea
              value={providerData.description}
              onChange={(e) => handleChange('description', e.target.value)}
              placeholder="Tell your story..."
              className="w-full px-5 py-4 rounded-2xl bg-white/[0.04] border border-white/[0.08] text-white placeholder-white/20 focus:outline-none focus:border-[#FFD700]/40 focus:bg-white/[0.06] transition-all duration-300 resize-none h-32"
            />
          </div>
        </div>
      </div>
    </SceneLayout>
  );
}
