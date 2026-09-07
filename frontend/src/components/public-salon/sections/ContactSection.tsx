'use client';

import { motion } from 'framer-motion';
import { MapPin, Phone, Mail, Clock } from 'lucide-react';
import { Salon, Theme, Brand, Colors } from '@/services/PublicSalonExperienceResolver';

interface ContactSectionProps {
  salon: Salon;
  theme: Theme;
  brand: Brand;
  colors: Colors;
  bookingUrl: string;
}

export default function ContactSection({ salon, theme, brand, colors, bookingUrl }: ContactSectionProps) {
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
              color: theme.text,
              fontFamily: theme.font_heading,
            }}
          >
            Get in Touch
          </h2>
          <p 
            className="text-lg"
            style={{ color: `${theme.text}80` }}
          >
            Visit us or book an appointment
          </p>
        </motion.div>

        <div className="grid md:grid-cols-2 gap-8">
          {/* Contact Info */}
          <motion.div
            initial={{ opacity: 0, x: -20 }}
            whileInView={{ opacity: 1, x: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.6 }}
            className="space-y-6"
          >
            {salon.address && (
              <div className="flex items-start gap-4">
                <div 
                  className="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0"
                  style={{ background: `${theme.primary}20` }}
                >
                  <MapPin className="w-5 h-5" style={{ color: theme.primary }} />
                </div>
                <div>
                  <h3 
                    className="font-semibold mb-1"
                    style={{ color: theme.text }}
                  >
                    Address
                  </h3>
                  <p 
                    className="text-sm"
                    style={{ color: `${theme.text}70` }}
                  >
                    {salon.address}
                    {salon.city && `, ${salon.city}`}
                  </p>
                </div>
              </div>
            )}

            {salon.phone && (
              <div className="flex items-start gap-4">
                <div 
                  className="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0"
                  style={{ background: `${theme.primary}20` }}
                >
                  <Phone className="w-5 h-5" style={{ color: theme.primary }} />
                </div>
                <div>
                  <h3 
                    className="font-semibold mb-1"
                    style={{ color: theme.text }}
                  >
                    Phone
                  </h3>
                  <a 
                    href={`tel:${salon.phone}`}
                    className="text-sm hover:underline"
                    style={{ color: `${theme.text}70` }}
                  >
                    {salon.phone}
                  </a>
                </div>
              </div>
            )}

            {salon.email && (
              <div className="flex items-start gap-4">
                <div 
                  className="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0"
                  style={{ background: `${theme.primary}20` }}
                >
                  <Mail className="w-5 h-5" style={{ color: theme.primary }} />
                </div>
                <div>
                  <h3 
                    className="font-semibold mb-1"
                    style={{ color: theme.text }}
                  >
                    Email
                  </h3>
                  <a 
                    href={`mailto:${salon.email}`}
                    className="text-sm hover:underline"
                    style={{ color: `${theme.text}70` }}
                  >
                    {salon.email}
                  </a>
                </div>
              </div>
            )}

            {salon.timezone && (
              <div className="flex items-start gap-4">
                <div 
                  className="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0"
                  style={{ background: `${theme.primary}20` }}
                >
                  <Clock className="w-5 h-5" style={{ color: theme.primary }} />
                </div>
                <div>
                  <h3 
                    className="font-semibold mb-1"
                    style={{ color: theme.text }}
                  >
                    Timezone
                  </h3>
                  <p 
                    className="text-sm"
                    style={{ color: `${theme.text}70` }}
                  >
                    {salon.timezone}
                  </p>
                </div>
              </div>
            )}
          </motion.div>

          {/* Book Now CTA */}
          <motion.div
            initial={{ opacity: 0, x: 20 }}
            whileInView={{ opacity: 1, x: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.6 }}
            className="p-8 rounded-2xl text-center"
            style={{
              background: theme.background,
              border: `1px solid ${theme.primary}20`,
            }}
          >
            <h3 
              className="text-2xl font-bold mb-4"
              style={{ 
                color: theme.text,
                fontFamily: theme.font_heading,
              }}
            >
              Ready to Book?
            </h3>
            <p 
              className="mb-6"
              style={{ color: `${theme.text}70` }}
            >
              Schedule your appointment online in just a few clicks.
            </p>
            <a
              href={bookingUrl}
              className="inline-flex items-center gap-2 px-8 py-4 rounded-xl font-semibold text-lg transition-all hover:scale-105"
              style={{
                background: `linear-gradient(135deg, ${theme.primary}, ${theme.secondary})`,
                color: theme.background,
              }}
            >
              Book Now
            </a>
          </motion.div>
        </div>
      </div>
    </section>
  );
}
