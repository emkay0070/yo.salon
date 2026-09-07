'use client';

import { motion } from 'framer-motion';
import { Star, Clock, DollarSign, Award, MapPin, Calendar, Shield } from 'lucide-react';

interface HeroCardProps {
  title: string;
  subtitle?: string;
  description?: string;
  image?: string;
  badges?: Array<{ icon: any; label: string; value: string | number }>;
  rating?: number;
  reviewCount?: number;
  verified?: boolean;
  primaryAction?: { label: string; onClick: () => void };
  secondaryActions?: Array<{ label: string; onClick: () => void }>;
  variant?: 'service' | 'specialist' | 'salon' | 'provider';
}

export function HeroCard({
  title,
  subtitle,
  description,
  image,
  badges,
  rating,
  reviewCount,
  verified,
  primaryAction,
  secondaryActions,
  variant = 'service'
}: HeroCardProps) {
  const getGradient = () => {
    switch (variant) {
      case 'service':
        return 'linear-gradient(135deg, var(--brand-primary, #FFD700)20, var(--brand-secondary, #C9A227)20)';
      case 'specialist':
        return 'linear-gradient(135deg, var(--brand-primary, #FFD700)15, var(--brand-secondary, #C9A227)10)';
      case 'salon':
        return 'linear-gradient(135deg, var(--brand-primary, #FFD700)10, var(--brand-secondary, #C9A227)5)';
      case 'provider':
        return 'linear-gradient(135deg, var(--brand-primary, #FFD700)20, var(--brand-secondary, #C9A227)15)';
      default:
        return 'linear-gradient(135deg, var(--brand-primary, #FFD700)20, var(--brand-secondary, #C9A227)20)';
    }
  };

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      className="relative overflow-hidden rounded-2xl"
      style={{
        background: getGradient(),
        border: `1px solid var(--brand-primary, #FFD700)30`,
        borderRadius: 'var(--brand-border-radius, 16px)',
        boxShadow: 'var(--brand-shadow-lg, 0 8px 24px rgba(0,0,0,0.10))'
      }}
    >
      <div className="relative z-10 p-8">
        {/* Header */}
        <div className="flex flex-col md:flex-row gap-8 items-start">
          {/* Image/Avatar */}
          {image && (
            <div className="flex-shrink-0">
              <div
                className="w-32 h-32 md:w-48 md:h-48 rounded-2xl overflow-hidden"
                style={{
                  background: 'linear-gradient(to bottom right, var(--brand-primary, #FFD700), var(--brand-secondary, #C9A227))',
                  borderRadius: 'var(--brand-border-radius, 16px)'
                }}
              >
                <img
                  src={image}
                  alt={title}
                  className="w-full h-full object-cover"
                />
              </div>
            </div>
          )}

          {/* Content */}
          <div className="flex-1">
            {/* Badges */}
            {badges && badges.length > 0 && (
              <div className="flex flex-wrap gap-2 mb-4">
                {badges.map((badge, index) => (
                  <div
                    key={index}
                    className="flex items-center gap-2 px-3 py-1.5 rounded-full border text-xs font-medium"
                    style={{
                      backgroundColor: 'var(--brand-primary, #FFD700)10',
                      borderColor: 'var(--brand-primary, #FFD700)20',
                      color: 'var(--brand-primary, #FFD700)'
                    }}
                  >
                    <badge.icon className="w-4 h-4" />
                    <span>{badge.label}: {badge.value}</span>
                  </div>
                ))}
              </div>
            )}

            {/* Title */}
            <h1
              className="text-3xl md:text-4xl font-bold text-text-primary mb-2"
              style={{ fontFamily: 'var(--brand-font-heading, var(--font-sora))' }}
            >
              {title}
            </h1>

            {/* Subtitle */}
            {subtitle && (
              <p className="text-lg text-text-secondary mb-3">{subtitle}</p>
            )}

            {/* Description */}
            {description && (
              <p className="text-text-secondary mb-4 leading-relaxed">{description}</p>
            )}

            {/* Rating & Verification */}
            {(rating || verified) && (
              <div className="flex items-center gap-4 mb-6">
                {rating && (
                  <div className="flex items-center gap-2">
                    <Star className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
                    <span className="font-semibold text-text-primary">{rating}</span>
                    {reviewCount && (
                      <span className="text-sm text-text-secondary">({reviewCount} reviews)</span>
                    )}
                  </div>
                )}
                {verified && (
                  <div className="flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20">
                    <Shield className="w-4 h-4 text-emerald-500" />
                    <span className="text-xs font-medium text-emerald-500">Verified</span>
                  </div>
                )}
              </div>
            )}

            {/* Actions */}
            <div className="flex flex-wrap gap-3">
              {primaryAction && (
                <button
                  onClick={primaryAction.onClick}
                  className="px-6 py-3 text-white font-semibold rounded-xl flex items-center gap-2 transition-all"
                  style={{
                    background: `linear-gradient(to right, var(--brand-primary, #FFD700), var(--brand-secondary, #C9A227))`,
                    borderRadius: 'var(--brand-border-radius, 16px)',
                    boxShadow: 'var(--brand-shadow-sm, 0 2px 8px rgba(0,0,0,0.05))'
                  }}
                >
                  {primaryAction.label}
                </button>
              )}
              {secondaryActions && secondaryActions.map((action, index) => (
                <button
                  key={index}
                  onClick={action.onClick}
                  className="px-6 py-3 font-semibold rounded-xl border border-border-light text-text-primary hover:border-gold/30 transition-all"
                  style={{
                    borderRadius: 'var(--brand-border-radius, 16px)',
                    boxShadow: 'var(--brand-shadow-sm, 0 2px 8px rgba(0,0,0,0.05))'
                  }}
                >
                  {action.label}
                </button>
              ))}
            </div>
          </div>
        </div>
      </div>
    </motion.div>
  );
}
