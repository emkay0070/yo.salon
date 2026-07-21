'use client';

import { Check } from 'lucide-react';

interface BookingStep {
  id: string;
  label: string;
  completed: boolean;
  active: boolean;
}

interface BookingStepsProps {
  currentStep: number;
  onStepClick: (step: number) => void;
}

const steps = [
  { id: 'service', label: 'Service' },
  { id: 'stylist', label: 'Stylist' },
  { id: 'schedule', label: 'Schedule' },
  { id: 'details', label: 'Details' },
];

export default function BookingSteps({ currentStep, onStepClick }: BookingStepsProps) {
  return (
    <div className="bg-card border border-border-light rounded-2xl p-3 lg:p-6 backdrop-blur-2xl">
      <div className="flex items-center justify-between overflow-x-auto pb-1">
        {steps.map((step, index) => {
          const isCompleted = index < currentStep;
          const isActive = index === currentStep;
          
          return (
            <div key={step.id} className="flex items-center flex-1 min-w-max">
              <button
                onClick={() => onStepClick(index)}
                className={`flex items-center gap-1.5 sm:gap-2 lg:gap-3 w-full ${
                  isActive ? 'cursor-pointer' : isCompleted ? 'cursor-pointer' : 'cursor-default'
                }`}
              >
                <div
                  className={`w-7 h-7 sm:w-8 sm:h-8 lg:w-10 lg:h-10 rounded-full flex items-center justify-center transition-all duration-300 flex-shrink-0 ${
                    isActive
                      ? 'bg-gradient-to-br from-[#FFD700] to-[#C9A227] text-obsidian scale-110'
                      : isCompleted
                      ? 'bg-gradient-to-br from-[#FFD700] to-[#C9A227] text-obsidian'
                      : 'bg-white/10 text-text-secondary'
                  }`}
                >
                  {isCompleted ? (
                    <Check className="w-3 h-3 sm:w-4 sm:h-4 lg:w-5 lg:h-5" />
                  ) : (
                    <span className="font-semibold text-xs sm:text-sm lg:text-base">{index + 1}</span>
                  )}
                </div>
                <span
                  className={`font-medium transition-colors text-xs sm:text-sm lg:text-base ${
                    isActive
                      ? 'text-gold'
                      : isCompleted
                      ? 'text-text-primary'
                      : 'text-text-secondary'
                  }`}
                >
                  {step.label}
                </span>
              </button>
              
              {index < steps.length - 1 && (
                <div
                  className={`flex-1 h-0.5 mx-1 sm:mx-2 lg:mx-4 transition-colors min-w-[12px] sm:min-w-[20px] ${
                    isCompleted ? 'bg-[#FFD700]' : 'bg-white/10'
                  }`}
                />
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}
