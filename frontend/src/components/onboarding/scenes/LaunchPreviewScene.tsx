'use client';

import { useOnboarding } from '@/contexts/OnboardingContext';
import { motion } from 'framer-motion';
import { Check, Sparkles, Eye } from 'lucide-react';
import SceneLayout from './SceneLayout';

export default function LaunchPreviewScene() {
  const { salonData, setShowMobilePreview } = useOnboarding();

  const benefits = [
    'Discover your salon online',
    'View your services & prices',
    'Book appointments 24/7',
    'Join your community'
  ];

  return (
    <SceneLayout nextDisabled={false} hint="This is a preview of your public presence.">
      <div className="text-center mb-10 flex flex-col items-center">
        <div className="w-16 h-16 rounded-full bg-white/[0.04] border border-white/10 flex items-center justify-center mb-6">
          <Sparkles className="w-8 h-8 text-[#FFD700]" />
        </div>
        <h2 className="text-3xl md:text-4xl font-bold text-white mb-3">
          Your salon is ready ✨
        </h2>
        <p className="text-white/40 text-base max-w-sm mx-auto">
          {salonData.name || 'Your Salon'} has been beautifully digitized. 
          {salonData.category && ` A premium ${salonData.category.toLowerCase()} experience.`}
        </p>
      </div>

      <div className="max-w-sm mx-auto w-full space-y-8">
        <div className="p-6 rounded-2xl bg-white/[0.02] border border-white/[0.04]">
          <h3 className="text-white/80 font-medium mb-4 text-center">Your customers can now:</h3>
          <ul className="space-y-4">
            {benefits.map((text, i) => (
              <motion.li 
                key={i}
                initial={{ opacity: 0, y: 10 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: 0.1 * i + 0.2 }}
                className="flex items-center gap-3 text-white/70 text-sm"
              >
                <div className="w-5 h-5 rounded-full bg-[#FFD700]/10 flex items-center justify-center shrink-0">
                  <Check className="w-3 h-3 text-[#FFD700]" />
                </div>
                {text}
              </motion.li>
            ))}
          </ul>
        </div>
        
        {/* On mobile, we show a preview hint */}
        <div className="lg:hidden text-center flex flex-col items-center gap-3">
          <p className="text-xs text-white/40">Tap the preview button below to see your site</p>
          <div 
            onClick={() => setShowMobilePreview(true)}
            className="w-12 h-12 rounded-full border border-white/20 bg-white/5 flex items-center justify-center animate-bounce cursor-pointer hover:bg-white/10 active:scale-95 transition-all"
          >
            <Eye className="w-5 h-5 text-white/60" />
          </div>
        </div>
      </div>
    </SceneLayout>
  );
}
