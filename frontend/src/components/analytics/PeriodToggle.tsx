'use client';
import { motion } from 'framer-motion';

interface PeriodToggleProps {
  periods: string[];
  activePeriod: string;
  onChange: (period: string) => void;
}

export function PeriodToggle({ periods, activePeriod, onChange }: PeriodToggleProps) {
  return (
    <div className="flex p-1 bg-card rounded-xl border border-border-light w-fit">
      {periods.map(period => (
        <button
          key={period}
          onClick={() => onChange(period)}
          className={`relative px-4 py-1.5 text-xs font-medium rounded-lg transition-colors ${
            activePeriod === period ? 'text-obsidian' : 'text-text-secondary hover:text-text-primary'
          }`}
        >
          {activePeriod === period && (
            <motion.div
              layoutId="periodToggle"
              className="absolute inset-0 bg-[#FFD700] rounded-lg"
              initial={false}
              transition={{ type: "spring", stiffness: 400, damping: 30 }}
            />
          )}
          <span className="relative z-10">{period}</span>
        </button>
      ))}
    </div>
  );
}
