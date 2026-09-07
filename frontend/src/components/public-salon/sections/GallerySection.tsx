'use client';

import { useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import { Image as ImageIcon } from 'lucide-react';
import { Salon, Theme, Brand, Colors } from '@/services/PublicSalonExperienceResolver';
import { apiClient } from '@/lib/api-client';

interface GalleryImage {
  id: string;
  url: string;
  alt?: string;
  caption?: string;
}

interface GallerySectionProps {
  salon: Salon;
  theme: Theme;
  brand: Brand;
  colors: Colors;
  bookingUrl: string;
}

export default function GallerySection({ salon, theme, brand, colors, bookingUrl }: GallerySectionProps) {
  const [images, setImages] = useState<GalleryImage[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    async function loadGallery() {
      try {
        // TODO: Create gallery API endpoint
        // const data = await apiClient.getSalonGallery(salon.id);
        // setImages(data);
        
        // For now, return empty to hide section if no gallery
        setImages([]);
      } catch (error) {
        console.error('Failed to load gallery:', error);
        setImages([]);
      } finally {
        setLoading(false);
      }
    }

    loadGallery();
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
          <div className="animate-pulse">Loading gallery...</div>
        </div>
      </section>
    );
  }

  if (images.length === 0) {
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
            Our Gallery
          </h2>
          <p 
            className="text-lg"
            style={{ color: `${colors.text || '#FFFFFF'}80` }}
          >
            Take a look at our work
          </p>
        </motion.div>

        <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
          {images.map((image, index) => (
            <motion.div
              key={image.id}
              initial={{ opacity: 0, scale: 0.9 }}
              whileInView={{ opacity: 1, scale: 1 }}
              viewport={{ once: true }}
              transition={{ delay: index * 0.1, duration: 0.5 }}
              className="aspect-square rounded-2xl overflow-hidden relative group"
              style={{
                background: colors.background || '#0A0A0A',
                border: `1px solid ${colors.primary}20`,
              }}
            >
              {/* Placeholder for image */}
              <div 
                className="w-full h-full flex items-center justify-center"
                style={{ background: `${colors.primary}10` }}
              >
                <ImageIcon className="w-12 h-12" style={{ color: colors.primary }} />
              </div>
              
              {/* Hover overlay */}
              <div 
                className="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center"
                style={{ background: `${colors.primary}40` }}
              >
                <span 
                  className="text-sm font-medium"
                  style={{ color: colors.background || '#0A0A0A' }}
                >
                  {image.alt}
                </span>
              </div>
            </motion.div>
          ))}
        </div>
      </div>
    </section>
  );
}
