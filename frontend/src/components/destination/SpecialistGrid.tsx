'use client';

import { motion } from 'framer-motion';
import { Star, Clock, MapPin, ArrowRight, Award } from 'lucide-react';

interface Specialist {
  id: string;
  name: string;
  role?: string;
  avatar?: string;
  rating?: number;
  reviewCount?: number;
  nextAvailable?: string;
  specializations?: string[];
  salon?: string;
  handle?: string;
}

interface SpecialistGridProps {
  specialists: Specialist[];
  onSpecialistClick?: (specialist: Specialist) => void;
  variant?: 'compact' | 'full';
  columns?: number;
}

export function SpecialistGrid({ specialists, onSpecialistClick, variant = 'full', columns = 3 }: SpecialistGridProps) {
  if (!specialists || specialists.length === 0) {
    return (
      <div className="text-center py-12 text-text-secondary">
        <p>No specialists available</p>
      </div>
    );
  }

  const gridCols = `grid-cols-1 md:grid-cols-${Math.min(columns, specialists.length)}`;

  return (
    <div className="space-y-4">
      <h3 className="text-xl font-semibold text-text-primary">Specialists</h3>
      
      <div className={`grid ${gridCols} gap-4`}>
        {specialists.map((specialist, index) => (
          <motion.div
            key={specialist.id}
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: index * 0.05 }}
            onClick={() => onSpecialistClick?.(specialist)}
            className="bg-surface border border-border-light rounded-xl p-4 cursor-pointer hover:border-gold/30 transition-all group"
            style={{
              borderRadius: 'var(--brand-border-radius, 16px)',
              boxShadow: 'var(--brand-shadow-sm, 0 2px 8px rgba(0,0,0,0.05))'
            }}
          >
            {/* Header */}
            <div className="flex items-start gap-3 mb-3">
              {specialist.avatar ? (
                <img
                  src={specialist.avatar}
                  alt={specialist.name}
                  className="w-12 h-12 rounded-full object-cover"
                />
              ) : (
                <div
                  className="w-12 h-12 rounded-full flex items-center justify-center text-white font-semibold"
                  style={{
                    background: 'linear-gradient(to bottom right, var(--brand-primary, #FFD700), var(--brand-secondary, #C9A227))',
                    borderRadius: 'var(--brand-border-radius, 16px)'
                  }}
                >
                  {specialist.name.charAt(0)}
                </div>
              )}
              <div className="flex-1">
                <h4 className="font-semibold text-text-primary">{specialist.name}</h4>
                {specialist.role && (
                  <p className="text-sm text-text-secondary">{specialist.role}</p>
                )}
              </div>
            </div>

            {/* Rating */}
            {specialist.rating && (
              <div className="flex items-center gap-1 mb-3">
                <Star className="w-4 h-4" style={{ color: 'var(--brand-primary, #FFD700)' }} />
                <span className="font-semibold text-text-primary">{specialist.rating}</span>
                {specialist.reviewCount && (
                  <span className="text-sm text-text-secondary">({specialist.reviewCount})</span>
                )}
              </div>
            )}

            {/* Specializations */}
            {specialist.specializations && specialist.specializations.length > 0 && (
              <div className="flex flex-wrap gap-1 mb-3">
                {specialist.specializations.slice(0, 3).map((spec, i) => (
                  <span
                    key={i}
                    className="text-xs px-2 py-1 rounded-full bg-surface/50 border border-border-light text-text-secondary"
                  >
                    {spec}
                  </span>
                ))}
                {specialist.specializations.length > 3 && (
                  <span className="text-xs text-text-secondary">+{specialist.specializations.length - 3}</span>
                )}
              </div>
            )}

            {/* Next Available */}
            {specialist.nextAvailable && variant === 'full' && (
              <div className="flex items-center gap-2 text-sm text-text-secondary mb-3">
                <Clock className="w-4 h-4" />
                <span>Next: {specialist.nextAvailable}</span>
              </div>
            )}

            {/* Salon */}
            {specialist.salon && variant === 'full' && (
              <div className="flex items-center gap-2 text-sm text-text-secondary mb-3">
                <MapPin className="w-4 h-4" />
                <span className="truncate">{specialist.salon}</span>
              </div>
            )}

            {/* Action */}
            <div className="flex items-center justify-between">
              {specialist.nextAvailable && (
                <button
                  className="text-sm font-medium px-3 py-1.5 rounded-lg bg-gold/10 border border-gold/20 hover:bg-gold/20 transition-colors"
                  style={{
                    color: 'var(--brand-primary, #FFD700)',
                    borderRadius: 'var(--brand-border-radius, 16px)'
                  }}
                >
                  Book
                </button>
              )}
              <ArrowRight className="w-5 h-5 text-text-secondary group-hover:text-gold transition-colors ml-auto" />
            </div>
          </motion.div>
        ))}
      </div>
    </div>
  );
}
