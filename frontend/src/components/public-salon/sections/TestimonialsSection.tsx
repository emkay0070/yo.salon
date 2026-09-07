'use client';

import { useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import { Star, Quote } from 'lucide-react';
import { Salon, Theme, Brand, Colors } from '@/services/PublicSalonExperienceResolver';
import { apiClient } from '@/lib/api-client';

interface Testimonial {
  id: string;
  name: string;
  rating: number;
  text: string;
}

interface TestimonialsSectionProps {
  salon: Salon;
  theme: Theme;
  brand: Brand;
  colors: Colors;
  bookingUrl: string;
}

export default function TestimonialsSection({ salon, theme, brand, colors, bookingUrl }: TestimonialsSectionProps) {
  const [testimonials, setTestimonials] = useState<Testimonial[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    async function loadTestimonials() {
      try {
        // TODO: Create testimonials API endpoint
        // const data = await apiClient.getSalonTestimonials(salon.id);
        // setTestimonials(data);
        
        // For now, return empty to hide section if no testimonials
        setTestimonials([]);
      } catch (error) {
        console.error('Failed to load testimonials:', error);
        setTestimonials([]);
      } finally {
        setLoading(false);
      }
    }

    loadTestimonials();
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
          <div className="animate-pulse">Loading reviews...</div>
        </div>
      </section>
    );
  }

  if (testimonials.length === 0) {
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
            What Our Clients Say
          </h2>
          <p 
            className="text-lg"
            style={{ color: `${colors.text || '#FFFFFF'}80` }}
          >
            Real reviews from real customers
          </p>
        </motion.div>

        <div className="grid md:grid-cols-3 gap-6">
          {testimonials.map((testimonial, index) => (
            <motion.div
              key={testimonial.id}
              initial={{ opacity: 0, y: 20 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              transition={{ delay: index * 0.1, duration: 0.5 }}
              className="p-6 rounded-2xl relative"
              style={{
                background: colors.surface || '#1A1A1A',
                border: `1px solid ${colors.primary}20`,
              }}
            >
              <Quote 
                className="absolute top-4 right-4 w-8 h-8 opacity-20"
                style={{ color: colors.primary }}
              />

              <div className="flex gap-1 mb-4">
                {[...Array(testimonial.rating)].map((_, i) => (
                  <Star 
                    key={i} 
                    className="w-4 h-4 fill-current"
                    style={{ color: colors.primary }}
                  />
                ))}
              </div>

              <p 
                className="mb-4 leading-relaxed"
                style={{ color: colors.text || '#FFFFFF' }}
              >
                "{testimonial.text}"
              </p>

              <p 
                className="text-sm font-medium"
                style={{ color: colors.secondary }}
              >
                — {testimonial.name}
              </p>
            </motion.div>
          ))}
        </div>
      </div>
    </section>
  );
}
