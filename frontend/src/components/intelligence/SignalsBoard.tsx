'use client';

import { motion } from 'framer-motion';
import { AlertTriangle, ArrowRight } from 'lucide-react';
import Link from 'next/link';

interface Signal {
  id: string;
  priority: 'critical' | 'high' | 'medium' | 'low';
  title: string;
  summary: string;
  impact: string;
  recommended_action: string;
  deep_link: string;
}

interface SignalsBoardProps {
  signals: Signal[];
}

export default function SignalsBoard({ signals }: SignalsBoardProps) {
  // Group signals by priority
  const grouped = {
    critical: signals.filter(s => s.priority === 'critical'),
    high: signals.filter(s => s.priority === 'high'),
    medium: signals.filter(s => s.priority === 'medium'),
    low: signals.filter(s => s.priority === 'low'),
  };

  const priorityConfig = {
    critical: { label: 'CRITICAL', color: 'text-red-500', border: 'border-red-500/30' },
    high: { label: 'HIGH', color: 'text-orange-500', border: 'border-orange-500/30' },
    medium: { label: 'MEDIUM', color: 'text-yellow-500', border: 'border-yellow-500/30' },
    low: { label: 'LOW', color: 'text-blue-500', border: 'border-blue-500/30' },
  };

  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      className="bg-card border border-border-light rounded-2xl p-6 h-full backdrop-blur-2xl"
    >
      <div className="flex items-center gap-2 mb-6">
        <AlertTriangle className="w-5 h-5 text-gold" />
        <h2 className="text-lg font-semibold text-text-primary">Intelligence Signals</h2>
      </div>

      <div className="space-y-6">
        {Object.entries(grouped).map(([priority, items]) => {
          if (items.length === 0) return null;
          const config = priorityConfig[priority as keyof typeof priorityConfig];
          
          return (
            <div key={priority} className="space-y-3">
              <div className={`text-xs font-bold tracking-widest ${config.color} border-b ${config.border} pb-1 uppercase`}>
                {config.label}
              </div>
              <div className="space-y-4">
                {items.map((signal) => (
                  <div key={signal.id} className="pl-2 border-l-2 border-border-medium space-y-1 relative group">
                    <h3 className="text-sm font-medium text-text-primary flex items-center gap-2">
                      <span className={`w-1.5 h-1.5 rounded-full ${config.color.replace('text-', 'bg-')}`}></span>
                      {signal.title}
                    </h3>
                    <p className="text-xs text-text-secondary">{signal.summary}</p>
                    <p className="text-xs text-text-muted italic">{signal.impact}</p>
                    <Link href={signal.deep_link} className="text-xs font-medium text-gold hover:underline inline-flex items-center gap-1 mt-1 transition-transform group-hover:translate-x-1">
                      {signal.recommended_action} <ArrowRight className="w-3 h-3" />
                    </Link>
                  </div>
                ))}
              </div>
            </div>
          );
        })}

        {signals.length === 0 && (
          <div className="py-8 text-center text-text-secondary text-sm">
            All systems nominal. No alerts active.
          </div>
        )}
      </div>
    </motion.div>
  );
}
