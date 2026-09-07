'use client';

import { useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import { User } from 'lucide-react';
import { Salon, Theme, Brand, Colors } from '@/services/PublicSalonExperienceResolver';
import { apiClient } from '@/lib/api-client';

interface Specialist {
  id: string;
  name: string;
  role?: string;
  specializations?: string[];
  photo?: string;
}

interface SpecialistsSectionProps {
  salon: Salon;
  theme: Theme;
  brand: Brand;
  colors: Colors;
  bookingUrl: string;
}

export default function SpecialistsSection({ salon, theme, brand, colors, bookingUrl }: SpecialistsSectionProps) {
  const [specialists, setSpecialists] = useState<Specialist[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    async function loadSpecialists() {
      try {
        const data = await apiClient.getSalonSpecialists(salon.id);
        setSpecialists(data);
      } catch (error) {
        console.error('Failed to load specialists:', error);
      } finally {
        setLoading(false);
      }
    }

    loadSpecialists();
  }, [salon.id]);

  if (loading) {
    return (
      <section 
        className="py-20 px-8"
        style={{ 
          background: colors.background || '#0A0A0A',
          fontFamily: brand.font_body,
        }}
      >
        <div className="max-w-6xl mx-auto text-center">
          <div className="animate-pulse">Loading team...</div>
        </div>
      </section>
    );
  }

  if (specialists.length === 0) {
    return null;
  }

  return (
    <section 
      className="py-20 px-8"
      style={{ 
        background: colors.background || '#0A0A0A',
        fontFamily: brand.font_body,
      }}
    >
      <div className="max-w-6xl mx-auto">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: 0.6 }}
          className="text-center mb-12"
        >
          <h2 
            className="text-3xl md:text-4xl font-bold mb-4"
            style={{ 
              color: colors.text || '#FFFFFF',
              fontFamily: brand.font_heading,
            }}
          >
            Our Team
          </h2>
          <p 
            className="text-lg"
            style={{ color: `${colors.text || '#FFFFFF'}80` }}
          >
            Meet our skilled professionals
          </p>
        </motion.div>

        <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
          {specialists.map((specialist, index) => (
            <motion.div
              key={specialist.id}
              initial={{ opacity: 0, y: 20 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              transition={{ delay: index * 0.1, duration: 0.5 }}
              className="p-6 rounded-2xl text-center transition-all hover:scale-105"
              style={{
                background: colors.surface || '#1A1A1A',
                border: `1px solid ${colors.primary}20`,
              }}
            >
              {/* Avatar placeholder */}
              <div 
                className="w-20 h-20 mx-auto mb-4 rounded-full flex items-center justify-center"
                style={{
                  background: `linear-gradient(135deg, ${colors.primary}40, ${colors.secondary}40)`,
                }}
              >
                <User className="w-10 h-10" style={{ color: colors.primary }} />
              </div>

              <h3 
                className="text-xl font-semibold mb-2"
                style={{ color: colors.text || '#FFFFFF' }}
              >
                {specialist.name}
              </h3>

              <p 
                className="text-sm mb-3"
                style={{ color: colors.primary }}
              >
                {specialist.role}
              </p>

              <div className="flex flex-wrap gap-2 justify-center">
                {specialist.specializations?.map((spec) => (
                  <span 
                    key={spec}
                    className="px-2 py-1 rounded-full text-xs"
                    style={{
                      background: `${colors.secondary}20`,
                      color: colors.secondary,
                    }}
                  >
                    {spec}
                  </span>
                )) || null}
              </div>
            </motion.div>
          ))}
        </div>

        <motion.div
          initial={{ opacity: 0, y: 20 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          transition={{ delay: 0.3, duration: 0.6 }}
          className="text-center mt-12"
        >
          <a
            href={bookingUrl}
            className="inline-flex items-center gap-2 px-6 py-3 rounded-xl font-semibold transition-all hover:scale-105"
            style={{
              background: `linear-gradient(135deg, ${colors.primary}, ${colors.secondary})`,
              color: colors.background || '#0A0A0A',
            }}
          >
            Book with a Specialist
          </a>
        </motion.div>
      </div>
    </section>
  );
}
