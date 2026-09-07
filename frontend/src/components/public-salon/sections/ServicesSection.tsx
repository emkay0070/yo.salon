'use client';

import { useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import { Clock } from 'lucide-react';
import { Salon, Theme, Brand, Colors } from '@/services/PublicSalonExperienceResolver';
import { apiClient } from '@/lib/api-client';

interface Service {
  id: string;
  name: string;
  description?: string;
  price: number;
  duration: number;
  category?: string;
  active?: boolean;
  image_url?: string;
}

interface ServicesSectionProps {
  salon: Salon;
  theme: Theme;
  brand: Brand;
  colors: Colors;
  bookingUrl: string;
}

export default function ServicesSection({ salon, theme, brand, colors, bookingUrl }: ServicesSectionProps) {
  const [services, setServices] = useState<Service[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    async function loadServices() {
      try {
        const data = await apiClient.getSalonServices(salon.id);
        setServices(data.filter((s: Service) => s.active !== false));
      } catch (error) {
        console.error('Failed to load services:', error);
      } finally {
        setLoading(false);
      }
    }

    loadServices();
  }, [salon.id]);

  if (loading) {
    return (
      <section 
        className="py-20 px-8"
        style={{ 
          background: colors.surface || '#1A1A1A',
          fontFamily: brand.font_body,
        }}
      >
        <div className="max-w-6xl mx-auto text-center">
          <div className="animate-pulse">Loading services...</div>
        </div>
      </section>
    );
  }

  if (services.length === 0) {
    return null;
  }

  return (
    <section 
      className="py-20 px-8"
      style={{ 
        background: colors.surface || '#1A1A1A',
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
            Our Services
          </h2>
          <p 
            className="text-lg"
            style={{ color: `${colors.text || '#FFFFFF'}80` }}
          >
            Professional grooming tailored to you
          </p>
        </motion.div>

        <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
          {services.map((service, index) => (
            <motion.div
              key={service.id}
              initial={{ opacity: 0, y: 20 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              transition={{ delay: index * 0.1, duration: 0.5 }}
              className="p-6 rounded-2xl transition-all hover:scale-105"
              style={{
                background: colors.background || '#0A0A0A',
                border: `1px solid ${colors.primary}20`,
              }}
            >
              <div className="flex justify-between items-start mb-4">
                <h3 
                  className="text-xl font-semibold"
                  style={{ color: colors.text || '#FFFFFF' }}
                >
                  {service.name}
                </h3>
                <span 
                  className="text-lg font-bold"
                  style={{ color: colors.primary }}
                >
                  UGX {service.price.toLocaleString('en-UG', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}
                </span>
              </div>
              
              <div className="flex items-center gap-2 mb-4">
                <Clock className="w-4 h-4" style={{ color: colors.secondary }} />
                <span 
                  className="text-sm"
                  style={{ color: `${colors.text || '#FFFFFF'}70` }}
                >
                  {service.duration} min
                </span>
              </div>

              <span 
                className="inline-block px-3 py-1 rounded-full text-xs font-medium"
                style={{
                  background: `${colors.secondary}20`,
                  color: colors.secondary,
                }}
              >
                {service.category}
              </span>
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
            View All Services
          </a>
        </motion.div>
      </div>
    </section>
  );
}
