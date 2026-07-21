'use client';

import { ReactNode } from 'react';
import { motion } from 'framer-motion';
import { ArrowLeft, ArrowRight, Loader2 } from 'lucide-react';
import { useOnboarding } from '@/contexts/OnboardingContext';

interface SceneLayoutProps {
  children: ReactNode;
  onNext?: () => void;
  onBack?: () => void;
  nextLabel?: string;
  nextDisabled?: boolean;
  hideBack?: boolean;
  loading?: boolean;
  hint?: string;
}

export default function SceneLayout({
  children,
  onNext,
  onBack,
  nextLabel = 'Continue',
  nextDisabled = false,
  hideBack = false,
  loading = false,
  hint,
}: SceneLayoutProps) {
  const { goNext, goPrev } = useOnboarding();

  const handleNext = onNext ?? goNext;
  const handleBack = onBack ?? goPrev;

  return (
    <div className="flex-1 flex flex-col items-center justify-center px-4 py-6 pt-24 min-h-[calc(100vh-80px)]">
      {/* Content card */}
      <div className="w-full max-w-xl">
        <motion.div
          initial={{ opacity: 0, y: 16 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.4, ease: [0.16, 1, 0.3, 1] }}
        >
          {children}
        </motion.div>

        {/* Navigation */}
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ delay: 0.2, duration: 0.4 }}
          className="flex items-center justify-between mt-10"
        >
          {/* Back */}
          {!hideBack ? (
            <button
              onClick={handleBack}
              className="flex items-center gap-2 px-4 py-2.5 rounded-xl text-white/30 hover:text-white/70 hover:bg-white/[0.04] transition-all duration-200 text-sm"
            >
              <ArrowLeft className="w-4 h-4" />
              Back
            </button>
          ) : (
            <div />
          )}

          {/* Hint */}
          {hint && (
            <p className="text-white/25 text-xs text-center flex-1 mx-4">{hint}</p>
          )}

          {/* Next */}
          <button
            onClick={handleNext}
            disabled={nextDisabled || loading}
            className="flex items-center gap-2 px-6 py-3 rounded-2xl bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black font-semibold text-sm hover:brightness-110 active:scale-[0.97] transition-all duration-200 shadow-xl shadow-[#FFD700]/10 disabled:opacity-40 disabled:cursor-not-allowed disabled:shadow-none"
          >
            {loading ? (
              <>
                <Loader2 className="w-4 h-4 animate-spin" />
                Working…
              </>
            ) : (
              <>
                {nextLabel}
                <ArrowRight className="w-4 h-4" />
              </>
            )}
          </button>
        </motion.div>
      </div>
    </div>
  );
}
