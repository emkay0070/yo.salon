'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { useOnboarding } from '@/contexts/OnboardingContext';
import { useCategory } from '@/contexts/CategoryContext';
import { motion, AnimatePresence } from 'framer-motion';
import { Crown, CheckCircle2, ArrowRight, Loader2, Copy, Check } from 'lucide-react';
import BringingProviderToLife from '../BringingProviderToLife';

export default function CelebrationScene() {
  const router = useRouter();
  const { providerData, staff, services, resetOnboarding, completeOnboarding, loading } = useOnboarding();
  const { terminology } = useCategory();
  const [isBringingToLife, setIsBringingToLife] = useState(false);
  const [error, setError] = useState('');
  const [copied, setCopied] = useState(false);

  // Resolve the domain dynamically based on current window location
  const getSalonLink = () => {
    if (typeof window !== 'undefined') {
      const origin = window.location.origin; // e.g. http://localhost:3000
      // Strip protocol to match the display aesthetic
      const cleanDomain = origin.replace(/https?:\/\//, '');
      return `${cleanDomain}/${providerData.draftSlug || 'your-salon'}`;
    }
    return `yosalon.com/${providerData.draftSlug || 'your-salon'}`;
  };

  const providerLink = getSalonLink();

  // Optional things they might have skipped
  const finishLater = [
    { id: 'logo', label: 'Add your logo', done: !!providerData.logo },
    { id: 'desc', label: `Add a ${terminology.business.toLowerCase()} description`, done: !!providerData.description },
    { id: 'staff-photos', label: 'Add team photos', done: staff.every(s => s.photo) && staff.length > 0 },
    { id: 'more-services', label: 'Add more services', done: services.length > 2 },
  ].filter(item => !item.done);

  const handleCopy = () => {
    navigator.clipboard.writeText(providerLink);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  const handleLaunch = async () => {
    setError('');
    setIsBringingToLife(true);
    try {
      await completeOnboarding();
      setTimeout(() => {
        resetOnboarding();
        router.push(`/${providerData.draftSlug}/dashboard`);
      }, 4000);
    } catch (err) {
      setError('Something went wrong. Please try again.');
      setIsBringingToLife(false);
    }
  };

  return (
    <>
      <AnimatePresence>
        {isBringingToLife && <BringingProviderToLife onComplete={() => {}} />}
      </AnimatePresence>

      {!isBringingToLife && (
        <div className="min-h-screen flex items-center justify-center p-6 relative overflow-hidden">
          {/* Gold burst background */}
          <div className="absolute inset-0 bg-gradient-to-br from-[#FFD700]/15 via-[#C9A227]/8 to-transparent" />
          <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] rounded-full bg-[#FFD700]/[0.04] blur-3xl pointer-events-none" />

          <motion.div
            initial={{ opacity: 0, scale: 0.96 }}
            animate={{ opacity: 1, scale: 1 }}
            transition={{ duration: 0.6, ease: [0.16, 1, 0.3, 1] }}
            className="relative z-10 w-full max-w-lg text-center flex flex-col items-center"
          >
            {/* Logo icon */}
            <motion.div
              initial={{ opacity: 0, y: -20, scale: 0.8 }}
              animate={{ opacity: 1, y: 0, scale: 1 }}
              transition={{ delay: 0.2, duration: 0.5, ease: [0.16, 1, 0.3, 1] }}
              className="flex justify-center mb-8"
            >
              <div className="w-20 h-20 rounded-3xl bg-gradient-to-br from-[#FFD700] to-[#C9A227] flex items-center justify-center shadow-2xl shadow-[#FFD700]/25">
                <Crown className="w-10 h-10 text-black" />
              </div>
            </motion.div>

            {/* Headline */}
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.3, duration: 0.5 }}
            >
              <h1 className="text-4xl md:text-5xl font-bold text-white mb-4 leading-tight">
                Your {terminology.business.toLowerCase()} is live 🎉
              </h1>
              <p className="text-white/70 text-lg mb-8 max-w-sm mx-auto">
                <span className="font-semibold text-white">{providerData.name || `Your ${terminology.business}`}</span> is now discoverable on Yo.Salon.
              </p>
            </motion.div>

            {/* Link Sharing */}
            <motion.div
              initial={{ opacity: 0, y: 16 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.45, duration: 0.5 }}
              className="w-full bg-white/[0.03] border border-white/[0.07] rounded-2xl p-6 mb-8 text-left"
            >
              <p className="text-xs text-white/50 tracking-widest uppercase font-semibold mb-3">Share your {terminology.business.toLowerCase()}</p>
              <div className="flex items-center gap-3">
                <div className="flex-1 bg-black/40 border border-white/10 rounded-xl px-4 py-3 font-mono text-sm text-[#FFD700] overflow-hidden text-ellipsis">
                  {providerLink}
                </div>
                <button
                  onClick={handleCopy}
                  className="w-12 h-12 flex-shrink-0 flex items-center justify-center bg-white/10 hover:bg-white/15 border border-white/10 rounded-xl transition"
                >
                  {copied ? <Check className="w-5 h-5 text-green-400" /> : <Copy className="w-5 h-5 text-white/70" />}
                </button>
              </div>
            </motion.div>

            {/* Finish Later Checklist */}
            {finishLater.length > 0 && (
              <motion.div
                initial={{ opacity: 0, y: 16 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: 0.55, duration: 0.5 }}
                className="w-full mb-10 text-left"
              >
                <p className="text-xs text-white/50 tracking-widest uppercase font-semibold mb-3">Finish these later</p>
                <div className="space-y-3">
                  {finishLater.map(item => (
                    <div key={item.id} className="flex items-center gap-3 text-white/60 text-sm">
                      <div className="w-4 h-4 rounded-full border border-white/20" />
                      {item.label}
                    </div>
                  ))}
                </div>
              </motion.div>
            )}

            {/* Error */}
            {error && (
              <p className="text-red-400 text-sm mb-4">{error}</p>
            )}

            {/* CTA */}
            <motion.button
              initial={{ opacity: 0, y: 16 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.7, duration: 0.5 }}
              onClick={handleLaunch}
              disabled={loading}
              className="w-full sm:w-auto inline-flex items-center justify-center gap-3 px-10 py-4 bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black rounded-2xl font-semibold text-lg hover:brightness-110 active:scale-[0.97] transition-all duration-300 shadow-2xl shadow-[#FFD700]/20 disabled:opacity-60 disabled:cursor-not-allowed"
            >
              {loading ? (
                <>
                  <Loader2 className="w-5 h-5 animate-spin" />
                  Creating Dashboard…
                </>
              ) : (
                <>
                  Go to Dashboard
                  <ArrowRight className="w-5 h-5" />
                </>
              )}
            </motion.button>
          </motion.div>
        </div>
      )}
    </>
  );
}
