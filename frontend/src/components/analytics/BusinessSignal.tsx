'use client';
import { LucideIcon } from 'lucide-react';
import { motion } from 'framer-motion';

interface BusinessSignalProps {
  title: string;
  value: string | number;
  narrative: string;
  icon: LucideIcon;
  trend?: 'positive' | 'negative' | 'neutral';
}

export function BusinessSignal({ title, value, narrative, icon: Icon, trend = 'neutral' }: BusinessSignalProps) {
  const trendColors = {
    positive: 'text-emerald-400 bg-emerald-400/10 border-emerald-400/20',
    negative: 'text-rose-400 bg-rose-400/10 border-rose-400/20',
    neutral: 'text-zinc-400 bg-zinc-400/10 border-zinc-400/20',
  };

  return (
    <motion.div 
      initial={{ opacity: 0, scale: 0.95 }}
      animate={{ opacity: 1, scale: 1 }}
      className="bg-card border border-border-light rounded-2xl p-5 hover:bg-surface transition-colors"
    >
      <div className="flex justify-between items-start mb-4">
        <div className={`p-2.5 rounded-xl border ${trendColors[trend]}`}>
          <Icon className="w-5 h-5" />
        </div>
        <div className="text-xl font-semibold text-text-primary tracking-tight">{value}</div>
      </div>
      <h3 className="text-sm font-medium text-text-primary mb-1">{title}</h3>
      <p className="text-xs text-text-secondary leading-relaxed">{narrative}</p>
    </motion.div>
  );
}
