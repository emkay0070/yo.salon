'use client';

import { motion } from 'framer-motion';
import { Calendar, Clock, DollarSign, ArrowRight } from 'lucide-react';

interface BookCTAProps {
  serviceName?: string;
  specialistName?: string;
  price?: number;
  duration?: number;
  onBook: () => void;
  variant?: 'floating' | 'inline' | 'hero';
}

export function BookCTA({
  serviceName,
  specialistName,
  price,
  duration,
  onBook,
  variant = 'floating'
}: BookCTAProps) {
  const baseStyles = "flex items-center justify-between gap-4 p-4 rounded-xl";
  const floatingStyles = "fixed bottom-6 left-6 right-6 md:left-auto md:right-6 md:w-auto md:min-w-[400px] z-40";
  
  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      className={`${baseStyles} ${variant === 'floating' ? floatingStyles : ''}`}
      style={{
        background: 'linear-gradient(135deg, var(--brand-primary, #FFD700)10, var(--brand-secondary, #C9A227)5)',
        border: `1px solid var(--brand-primary, #FFD700)30`,
        borderRadius: 'var(--brand-border-radius, 16px)',
        boxShadow: 'var(--brand-shadow-lg, 0 8px 24px rgba(0,0,0,0.10))'
      }}
    >
      {/* Info */}
      <div className="flex-1">
        {serviceName && (
          <p className="font-semibold text-text-primary">{serviceName}</p>
        )}
        {specialistName && (
          <p className="text-sm text-text-secondary">with {specialistName}</p>
        )}
        <div className="flex items-center gap-4 mt-2">
          {duration && (
            <div className="flex items-center gap-1 text-sm text-text-secondary">
              <Clock className="w-4 h-4" />
              <span>{duration} min</span>
            </div>
          )}
          {price && (
            <div className="flex items-center gap-1 text-sm font-semibold" style={{ color: 'var(--brand-primary, #FFD700)' }}>
              <DollarSign className="w-4 h-4" />
              <span>{price.toLocaleString('en-UG', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}</span>
            </div>
          )}
        </div>
      </div>

      {/* Book Button */}
      <button
        onClick={onBook}
        className="px-6 py-3 text-white font-semibold rounded-xl flex items-center gap-2 transition-all hover:scale-105"
        style={{
          background: `linear-gradient(to right, var(--brand-primary, #FFD700), var(--brand-secondary, #C9A227))`,
          borderRadius: 'var(--brand-border-radius, 16px)',
          boxShadow: 'var(--brand-shadow-sm, 0 2px 8px rgba(0,0,0,0.05))'
        }}
      >
        Book Now
        <ArrowRight className="w-5 h-5" />
      </button>
    </motion.div>
  );
}
