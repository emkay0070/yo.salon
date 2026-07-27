'use client';

import { motion } from 'framer-motion';
import { Calendar as CalendarIcon, TrendingUp } from 'lucide-react';
import { useState } from 'react';

interface DemandDay {
  day: string;
  bookings: number;
  intensity: number;
}

interface DemandHeatmapProps {
  demand: {
    by_day_of_week: DemandDay[];
  };
}

export default function DemandHeatmap({ demand }: DemandHeatmapProps) {
  const [view, setView] = useState<'week' | 'month' | 'quarter'>('week');

  const days = demand.by_day_of_week || [];
  
  // Normalize intensity to 0-4 scale for heatmap colors
  const getColorClass = (intensity: number) => {
    if (intensity === 0) return 'bg-surface-light border-border-light text-text-muted';
    if (intensity < 0.3) return 'bg-orange-500/20 border-orange-500/30 text-orange-400';
    if (intensity < 0.6) return 'bg-orange-500/40 border-orange-500/50 text-orange-300';
    if (intensity < 0.9) return 'bg-red-500/60 border-red-500/70 text-red-200 font-medium';
    return 'bg-red-500/80 border-red-500 text-white font-bold shadow-[0_0_10px_rgba(239,68,68,0.4)]';
  };

  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      className="bg-card border border-border-light rounded-2xl p-6 h-full backdrop-blur-2xl flex flex-col"
    >
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center gap-2">
          <TrendingUp className="w-5 h-5 text-gold" />
          <h2 className="text-lg font-semibold text-text-primary">Demand Heatmap</h2>
        </div>
        
        <div className="flex items-center gap-1 bg-surface-light p-1 rounded-lg border border-border-light">
          {(['week', 'month', 'quarter'] as const).map((v) => (
            <button
              key={v}
              onClick={() => setView(v)}
              className={`px-3 py-1 text-xs rounded-md transition-colors ${
                view === v ? 'bg-surface border border-border-medium text-gold' : 'text-text-secondary hover:text-text-primary'
              }`}
            >
              {v.charAt(0).toUpperCase() + v.slice(1)}
            </button>
          ))}
        </div>
      </div>

      <div className="flex-1 flex flex-col justify-center">
        {view === 'week' ? (
          <div className="grid grid-cols-7 gap-2">
            {days.map((d) => (
              <div key={d.day} className="flex flex-col gap-2">
                <div className={`h-24 rounded-xl border flex flex-col items-center justify-center transition-colors ${getColorClass(d.intensity)}`}>
                  <span className="text-lg">{d.bookings}</span>
                  <span className="text-[10px] uppercase opacity-70">Appts</span>
                </div>
                <span className="text-center text-xs text-text-secondary">{d.day.substring(0, 3)}</span>
              </div>
            ))}
          </div>
        ) : (
          <div className="flex flex-col items-center justify-center text-center h-24 border border-dashed border-border-medium rounded-xl">
            <CalendarIcon className="w-6 h-6 text-text-muted mb-2" />
            <p className="text-text-secondary text-sm">Long-term views coming soon.</p>
          </div>
        )}
      </div>

      <div className="mt-6 pt-4 border-t border-border-light flex items-center justify-between text-xs text-text-muted">
        <span>Cold</span>
        <div className="flex gap-1">
          <div className="w-4 h-4 rounded-sm bg-surface-light border border-border-light"></div>
          <div className="w-4 h-4 rounded-sm bg-orange-500/20 border border-orange-500/30"></div>
          <div className="w-4 h-4 rounded-sm bg-orange-500/40 border border-orange-500/50"></div>
          <div className="w-4 h-4 rounded-sm bg-red-500/60 border border-red-500/70"></div>
          <div className="w-4 h-4 rounded-sm bg-red-500/80 border border-red-500"></div>
        </div>
        <span>Hot</span>
      </div>
    </motion.div>
  );
}
