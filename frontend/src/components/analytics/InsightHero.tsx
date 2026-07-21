'use client';
import { motion } from 'framer-motion';
import { ArrowUp, ArrowDown } from 'lucide-react';

interface InsightHeroProps {
  title: string;
  narrative: string;
  metric?: string;
  trend?: {
    value: string;
    isPositive: boolean;
  };
  imagePath?: string;
}

export function InsightHero({ title, narrative, metric, trend, imagePath }: InsightHeroProps) {
  return (
    <motion.div 
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      className={`mb-8 relative overflow-hidden rounded-3xl ${imagePath ? 'p-8 sm:p-10' : ''}`}
    >
      {imagePath && (
        <>
          <div 
            className="absolute inset-0 bg-cover bg-center"
            style={{ backgroundImage: `url('${imagePath}')` }}
          />
          <div className="absolute inset-0 bg-gradient-to-r from-[var(--color-background)]/95 via-[var(--color-background)]/80 to-transparent" />
          <div className="absolute inset-0 bg-gradient-to-t from-[var(--color-background)]/60 via-transparent to-transparent" />
          <div className="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-[#FFD700]/50 via-[#FFD700]/10 to-transparent" />
        </>
      )}

      <div className="relative z-10">
        <h2 className={`${imagePath ? 'text-gold' : 'text-text-secondary'} text-xs font-semibold mb-3 tracking-[0.2em] uppercase`}>{title}</h2>
        <div className="flex flex-col md:flex-row md:items-end justify-between gap-6">
          <p className="text-3xl md:text-4xl font-semibold text-text-primary leading-tight max-w-2xl text-balance tracking-tight">
            {narrative}
          </p>
          {(metric || trend) && (
            <div className="flex flex-col items-start md:items-end flex-shrink-0 bg-card border border-border-light p-5 rounded-2xl backdrop-blur-md">
              {metric && <div className="text-4xl font-bold text-gold tracking-tight">{metric}</div>}
              {trend && (
                <div className={`flex items-center gap-1.5 text-sm font-medium mt-2 ${trend.isPositive ? 'text-emerald-400' : 'text-rose-400'}`}>
                  {trend.isPositive ? <ArrowUp className="w-4 h-4" /> : <ArrowDown className="w-4 h-4" />}
                  {trend.value}
                </div>
              )}
            </div>
          )}
        </div>
      </div>
    </motion.div>
  );
}
