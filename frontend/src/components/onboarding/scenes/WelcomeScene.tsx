'use client';

import { useOnboarding } from '@/contexts/OnboardingContext';
import { motion } from 'framer-motion';
import { ArrowRight, Scissors } from 'lucide-react';

export default function WelcomeScene() {
  const { goNext } = useOnboarding();

  return (
    <div className="min-h-screen flex items-center justify-center relative overflow-hidden">
      {/* Background image */}
      <div
        className="absolute inset-0 bg-cover bg-center bg-no-repeat"
        style={{ backgroundImage: `url('/images/salon-auth.jpg')` }}
      />
      <div className="absolute inset-0 bg-gradient-to-br from-[#060608]/70 via-[#060608]/50 to-[#060608]/85" />
      <div className="absolute inset-0 bg-gradient-to-t from-[#060608]/95 via-transparent to-[#060608]/40" />

      <div className="relative z-10 text-center px-8 max-w-3xl">
        <motion.div
          initial={{ opacity: 0, y: 30 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.9, ease: [0.16, 1, 0.3, 1] }}
        >
          {/* Logo */}
          <div className="flex items-center justify-center gap-3 mb-10">
            <div className="w-14 h-14 rounded-2xl bg-gradient-to-br from-[#FFD700] to-[#C9A227] flex items-center justify-center shadow-2xl shadow-[#FFD700]/25">
              <Scissors className="w-7 h-7 text-black" />
            </div>
            <span className="text-white text-2xl font-bold tracking-tight">Yo.Salon</span>
          </div>

          {/* Headline */}
          <h1 className="text-5xl md:text-7xl font-bold text-white mb-6 leading-[1.05]">
            Let's build<br />
            <span className="text-transparent bg-clip-text bg-gradient-to-r from-[#FFD700] to-[#C9A227]">
              your salon.
            </span>
          </h1>

          <p className="text-xl md:text-2xl text-white/60 mb-14 leading-relaxed">
            A guided 4-minute setup. We'll have you fully operational before the coffee gets cold.
          </p>

          <motion.button
            initial={{ opacity: 0, y: 16 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.5, duration: 0.6, ease: [0.16, 1, 0.3, 1] }}
            onClick={goNext}
            className="group inline-flex items-center gap-3 px-8 py-4 bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black rounded-2xl font-semibold text-lg hover:brightness-110 active:scale-[0.97] transition-all duration-300 shadow-2xl shadow-[#FFD700]/20"
          >
            Start building
            <ArrowRight className="w-5 h-5 group-hover:translate-x-1 transition-transform duration-300" />
          </motion.button>
        </motion.div>
      </div>
    </div>
  );
}
