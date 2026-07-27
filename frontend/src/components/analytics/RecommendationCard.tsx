'use client';
import { Lightbulb, Brain } from 'lucide-react';
import { motion } from 'framer-motion';

interface RecommendationCardProps {
  title: string;
  description: string;
  actionText?: string;
  onAction?: () => void;
  isPredictive?: boolean;
}

export function RecommendationCard({ title, description, actionText, onAction, isPredictive = false }: RecommendationCardProps) {
  return (
    <motion.div 
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      className="bg-gradient-to-br from-[rgba(255,215,0,0.1)] to-transparent border border-[rgba(255,215,0,0.2)] rounded-2xl p-6 relative overflow-hidden"
    >
      <div className="absolute top-0 right-0 p-4 opacity-10">
        {isPredictive ? <Brain className="w-24 h-24 text-gold" /> : <Lightbulb className="w-24 h-24 text-gold" />}
      </div>
      <div className="relative z-10">
        <div className="flex items-center gap-2 mb-2">
          {isPredictive ? (
            <>
              <Brain className="w-5 h-5 text-gold" />
              <h3 className="text-sm font-semibold text-gold tracking-wide uppercase">Predictive Insight</h3>
            </>
          ) : (
            <>
              <Lightbulb className="w-5 h-5 text-gold" />
              <h3 className="text-sm font-semibold text-gold tracking-wide uppercase">Opportunity</h3>
            </>
          )}
        </div>
        <h4 className="text-lg font-medium text-text-primary mb-2">{title}</h4>
        <p className="text-sm text-text-secondary mb-4 max-w-md leading-relaxed">{description}</p>
        
        {actionText && onAction && (
          <button 
            onClick={onAction}
            className="px-4 py-2 bg-[#FFD700] text-obsidian rounded-xl text-sm font-semibold hover:bg-[#FFD700]/90 transition-colors"
          >
            {actionText}
          </button>
        )}
      </div>
    </motion.div>
  );
}
