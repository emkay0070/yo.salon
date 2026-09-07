'use client';

import { motion } from 'framer-motion';
import { MapPin, Phone, Clock, Star, Navigation } from 'lucide-react';

interface LocationCardProps {
  name: string;
  address: string;
  phone?: string;
  distance?: string;
  rating?: number;
  isOpen?: boolean;
  openingHours?: string;
  onBook?: () => void;
  onNavigate?: () => void;
  variant?: 'compact' | 'full';
}

export function LocationCard({
  name,
  address,
  phone,
  distance,
  rating,
  isOpen,
  openingHours,
  onBook,
  onNavigate,
  variant = 'full'
}: LocationCardProps) {
  if (variant === 'compact') {
    return (
      <motion.div
        initial={{ opacity: 0, scale: 0.95 }}
        animate={{ opacity: 1, scale: 1 }}
        className="bg-surface border border-border-light rounded-xl p-4 cursor-pointer hover:border-gold/30 transition-all"
        style={{
          borderRadius: 'var(--brand-border-radius, 16px)',
          boxShadow: 'var(--brand-shadow-sm, 0 2px 8px rgba(0,0,0,0.05))'
        }}
        onClick={onBook}
      >
        <div className="flex items-start justify-between">
          <div className="flex-1">
            <h4 className="font-semibold text-text-primary mb-1">{name}</h4>
            <div className="flex items-center gap-2 text-sm text-text-secondary">
              <MapPin className="w-4 h-4" />
              <span className="truncate">{address}</span>
            </div>
            {distance && (
              <p className="text-sm text-text-secondary mt-1">{distance}</p>
            )}
          </div>
          {isOpen !== undefined && (
            <div className={`px-2 py-1 rounded-full text-xs font-medium ${
              isOpen ? 'bg-emerald-500/10 text-emerald-500' : 'bg-red-500/10 text-red-500'
            }`}>
              {isOpen ? 'Open' : 'Closed'}
            </div>
          )}
        </div>
      </motion.div>
    );
  }

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      className="bg-surface border border-border-light rounded-xl p-6"
      style={{
        borderRadius: 'var(--brand-border-radius, 16px)',
        boxShadow: 'var(--brand-shadow-md, 0 4px 12px rgba(0,0,0,0.08))'
      }}
    >
      {/* Header */}
      <div className="flex items-start justify-between mb-4">
        <div className="flex-1">
          <h4 className="font-semibold text-text-primary text-lg mb-2">{name}</h4>
          <div className="flex items-center gap-2 text-text-secondary">
            <MapPin className="w-4 h-4" />
            <span>{address}</span>
          </div>
        </div>
        {rating && (
          <div className="flex items-center gap-1 px-3 py-1 rounded-full bg-gold/10 border border-gold/20">
            <Star className="w-4 h-4" style={{ color: 'var(--brand-primary, #FFD700)' }} />
            <span className="font-semibold" style={{ color: 'var(--brand-primary, #FFD700)' }}>{rating}</span>
          </div>
        )}
      </div>

      {/* Details */}
      <div className="space-y-3 mb-4">
        {phone && (
          <div className="flex items-center gap-3 text-text-secondary">
            <Phone className="w-4 h-4" />
            <span>{phone}</span>
          </div>
        )}
        {openingHours && (
          <div className="flex items-center gap-3 text-text-secondary">
            <Clock className="w-4 h-4" />
            <span>{openingHours}</span>
          </div>
        )}
        {distance && (
          <div className="flex items-center gap-3 text-text-secondary">
            <Navigation className="w-4 h-4" />
            <span>{distance}</span>
          </div>
        )}
      </div>

      {/* Status */}
      {isOpen !== undefined && (
        <div className={`inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-sm font-medium mb-4 ${
          isOpen ? 'bg-emerald-500/10 text-emerald-500 border border-emerald-500/20' : 'bg-red-500/10 text-red-500 border border-red-500/20'
        }`}>
          <div className={`w-2 h-2 rounded-full ${isOpen ? 'bg-emerald-500' : 'bg-red-500'}`} />
          {isOpen ? 'Currently Open' : 'Currently Closed'}
        </div>
      )}

      {/* Actions */}
      <div className="flex gap-3">
        {onBook && (
          <button
            onClick={onBook}
            className="flex-1 px-4 py-2.5 text-white font-semibold rounded-xl transition-all"
            style={{
              background: `linear-gradient(to right, var(--brand-primary, #FFD700), var(--brand-secondary, #C9A227))`,
              borderRadius: 'var(--brand-border-radius, 16px)',
              boxShadow: 'var(--brand-shadow-sm, 0 2px 8px rgba(0,0,0,0.05))'
            }}
          >
            Book Here
          </button>
        )}
        {onNavigate && (
          <button
            onClick={onNavigate}
            className="px-4 py-2.5 font-semibold rounded-xl border border-border-light text-text-primary hover:border-gold/30 transition-all"
            style={{
              borderRadius: 'var(--brand-border-radius, 16px)'
            }}
          >
            Directions
          </button>
        )}
      </div>
    </motion.div>
  );
}
