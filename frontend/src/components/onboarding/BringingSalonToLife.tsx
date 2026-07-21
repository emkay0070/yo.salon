'use client';

import { useEffect, useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Store, Users, Scissors, CreditCard, Sparkles, Check } from 'lucide-react';

interface Step {
  id: string;
  icon: any;
  label: string;
  description: string;
}

const steps: Step[] = [
  {
    id: 'salon',
    icon: Store,
    label: 'Building your workspace',
    description: 'Creating your salon...',
  },
  {
    id: 'team',
    icon: Users,
    label: 'Inviting your team',
    description: 'Welcoming your team...',
  },
  {
    id: 'services',
    icon: Scissors,
    label: 'Preparing your calendar',
    description: 'Setting up your services...',
  },
  {
    id: 'wallet',
    icon: CreditCard,
    label: 'Configuring Wallet',
    description: 'Adding payment methods...',
  },
  {
    id: 'sparkles',
    icon: Sparkles,
    label: 'Adding the finishing touches',
    description: 'Almost ready...',
  },
];

export default function BringingSalonToLife({ onComplete }: { onComplete: () => void }) {
  const [currentStep, setCurrentStep] = useState(0);
  const [completedSteps, setCompletedSteps] = useState<Set<string>>(new Set());

  useEffect(() => {
    const interval = setInterval(() => {
      if (currentStep < steps.length) {
        const step = steps[currentStep];
        setCompletedSteps((prev) => new Set([...prev, step.id]));
        
        if (currentStep < steps.length - 1) {
          setCurrentStep((prev) => prev + 1);
        } else {
          // All steps completed, wait a moment then complete
          setTimeout(() => {
            onComplete();
          }, 1500);
        }
      }
    }, 800); // 800ms per step = ~4 seconds total

    return () => clearInterval(interval);
  }, [currentStep, onComplete]);

  return (
    <div className="fixed inset-0 bg-[#070707] flex items-center justify-center z-50">
      {/* Background gradient */}
      <div className="absolute inset-0 bg-gradient-to-br from-[#FFD700]/5 via-transparent to-[#C9A227]/5" />

      {/* Content */}
      <div className="relative z-10 text-center">
        {/* Icon Assembly */}
        <div className="flex items-center justify-center gap-6 mb-12">
          {steps.map((step, index) => {
            const Icon = step.icon;
            const isCompleted = completedSteps.has(step.id);
            const isCurrent = index === currentStep;
            const isPending = index > currentStep;

            return (
              <motion.div
                key={step.id}
                initial={{ opacity: 0, scale: 0.5 }}
                animate={{
                  opacity: isPending ? 0.3 : 1,
                  scale: isCompleted || isCurrent ? 1 : 0.5,
                }}
                transition={{ duration: 0.5, delay: index * 0.1 }}
                className="relative"
              >
                <div
                  className={`w-16 h-16 rounded-2xl flex items-center justify-center transition-all duration-500 ${
                    isCompleted
                      ? 'bg-gradient-to-br from-[#FFD700] to-[#C9A227] shadow-lg shadow-[#FFD700]/30'
                      : isCurrent
                      ? 'bg-surface border-2 border-[#FFD700]/50'
                      : 'bg-surface/30 border-2 border-transparent'
                  }`}
                >
                  {isCompleted ? (
                    <Check className="w-8 h-8 text-black" />
                  ) : (
                    <Icon
                      className={`w-8 h-8 transition-colors ${
                        isCurrent ? 'text-[#FFD700]' : 'text-text-secondary/50'
                      }`}
                    />
                  )}
                </div>

                {/* Glow effect for current step */}
                {isCurrent && (
                  <motion.div
                    className="absolute inset-0 rounded-2xl bg-[#FFD700]/20 blur-xl -z-10"
                    animate={{
                      scale: [1, 1.2, 1],
                      opacity: [0.5, 0.8, 0.5],
                    }}
                    transition={{
                      duration: 2,
                      repeat: Infinity,
                      ease: 'easeInOut',
                    }}
                  />
                )}
              </motion.div>
            );
          })}
        </div>

        {/* Text */}
        <AnimatePresence mode="wait">
          <motion.div
            key={currentStep}
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -10 }}
            transition={{ duration: 0.3 }}
          >
            <h2 className="text-2xl font-bold text-white mb-2">
              {steps[currentStep]?.label}
            </h2>
            <p className="text-white/60">
              {steps[currentStep]?.description}
            </p>
          </motion.div>
        </AnimatePresence>

        {/* Progress dots */}
        <div className="flex items-center justify-center gap-2 mt-8">
          {steps.map((step, index) => (
            <motion.div
              key={step.id}
              initial={{ opacity: 0.3 }}
              animate={{
                opacity: completedSteps.has(step.id) ? 1 : 0.3,
                scale: completedSteps.has(step.id) ? 1.2 : 1,
              }}
              transition={{ duration: 0.3 }}
              className={`w-2 h-2 rounded-full transition-colors ${
                completedSteps.has(step.id)
                  ? 'bg-[#FFD700]'
                  : index === currentStep
                  ? 'bg-[#FFD700]/50'
                  : 'bg-white/30'
              }`}
            />
          ))}
        </div>
      </div>
    </div>
  );
}
