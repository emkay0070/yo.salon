'use client';

import { motion } from 'framer-motion';
import { ArrowRight, Scissors } from 'lucide-react';
import { Salon, Theme, Brand, Colors } from '@/services/PublicSalonExperienceResolver';

interface HeroSectionProps {
  salon: Salon;
  theme: Theme;
  brand: Brand;
  colors: Colors;
  bookingUrl: string;
}

export default function HeroSection({ salon, theme, brand, colors, bookingUrl }: HeroSectionProps) {
  return (
    <section 
      className="relative min-h-[80vh] flex items-center justify-center overflow-hidden"
      style={{ 
        background: colors.background || '#0A0A0A',
        fontFamily: brand.font_heading,
      }}
    >
      {/* Background gradient overlay */}
      <div 
        className="absolute inset-0 opacity-20"
        style={{
          background: `radial-gradient(circle at 30% 50%, ${colors.primary} 0%, transparent 50%)`,
        }}
      />
      
      {/* Content */}
      <div className="relative z-10 text-center px-8 max-w-4xl">
        <motion.div
          initial={{ opacity: 0, y: 30 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.8, ease: [0.16, 1, 0.3, 1] }}
        >
          {/* Logo/Icon */}
          {salon.logo ? (
            <img 
              src={salon.logo} 
              alt={salon.name}
              className="w-20 h-20 mx-auto mb-8 rounded-2xl object-cover"
            />
          ) : (
            <div 
              className="w-20 h-20 mx-auto mb-8 rounded-2xl flex items-center justify-center"
              style={{
                background: `linear-gradient(135deg, ${theme.primary}, ${theme.secondary})`,
              }}
            >
              <Scissors className="w-10 h-10" style={{ color: theme.background }} />
            </div>
          )}

          {/* Name */}
          <h1 
            className="text-5xl md:text-7xl font-bold mb-6 leading-tight"
            style={{ 
              color: theme.text,
              fontFamily: theme.font_heading,
            }}
          >
            {salon.name}
          </h1>

          {/* Description */}
          {salon.description && (
            <p 
              className="text-xl md:text-2xl mb-8 leading-relaxed max-w-2xl mx-auto"
              style={{ 
                color: `${theme.text}99`,
                fontFamily: theme.font_body,
              }}
            >
              {salon.description}
            </p>
          )}

          {/* Category/Vibe badges */}
          <div className="flex items-center justify-center gap-3 mb-10">
            {salon.category && (
              <span 
                className="px-4 py-2 rounded-full text-sm font-medium"
                style={{
                  background: `${theme.primary}20`,
                  color: theme.primary,
                  border: `1px solid ${theme.primary}40`,
                }}
              >
                {salon.category}
              </span>
            )}
            {salon.vibe && (
              <span 
                className="px-4 py-2 rounded-full text-sm font-medium"
                style={{
                  background: `${theme.secondary}20`,
                  color: theme.secondary,
                  border: `1px solid ${theme.secondary}40`,
                }}
              >
                {salon.vibe}
              </span>
            )}
          </div>

          {/* Book Now CTA */}
          <motion.a
            href={bookingUrl}
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.3, duration: 0.6, ease: [0.16, 1, 0.3, 1] }}
            className="inline-flex items-center gap-3 px-8 py-4 rounded-2xl font-semibold text-lg transition-all duration-300 hover:scale-105 active:scale-95 shadow-2xl"
            style={{
              background: `linear-gradient(135deg, ${theme.primary}, ${theme.secondary})`,
              color: theme.background,
              fontFamily: theme.font_body,
            }}
          >
            Book Now
            <ArrowRight className="w-5 h-5" />
          </motion.a>
        </motion.div>
      </div>
    </section>
  );
}
