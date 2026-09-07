'use client';

import { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { X, ChevronLeft, ChevronRight, Heart, Share2 } from 'lucide-react';

interface GalleryProps {
  images: Array<{ url: string; alt?: string; type?: 'before' | 'after' | 'video' }>;
  variant?: 'service' | 'specialist' | 'salon' | 'provider';
}

export function Gallery({ images, variant = 'service' }: GalleryProps) {
  const [selectedIndex, setSelectedIndex] = useState<number | null>(null);

  const nextImage = () => {
    if (selectedIndex !== null) {
      setSelectedIndex((selectedIndex + 1) % images.length);
    }
  };

  const prevImage = () => {
    if (selectedIndex !== null) {
      setSelectedIndex((selectedIndex - 1 + images.length) % images.length);
    }
  };

  if (!images || images.length === 0) {
    return null;
  }

  return (
    <div className="space-y-4">
      <h3 className="text-xl font-semibold text-text-primary">Gallery</h3>
      
      {/* Grid */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        {images.slice(0, 8).map((image, index) => (
          <motion.div
            key={index}
            initial={{ opacity: 0, scale: 0.9 }}
            animate={{ opacity: 1, scale: 1 }}
            transition={{ delay: index * 0.05 }}
            className="relative aspect-square rounded-xl overflow-hidden cursor-pointer group"
            style={{
              borderRadius: 'var(--brand-border-radius, 16px)',
              boxShadow: 'var(--brand-shadow-sm, 0 2px 8px rgba(0,0,0,0.05))'
            }}
            onClick={() => setSelectedIndex(index)}
          >
            {image.type === 'video' ? (
              <div className="w-full h-full bg-surface flex items-center justify-center">
                <span className="text-text-secondary text-sm">Video</span>
              </div>
            ) : (
              <img
                src={image.url}
                alt={image.alt || `Gallery image ${index + 1}`}
                className="w-full h-full object-cover transition-transform duration-300 group-hover:scale-110"
              />
            )}
            
            {/* Type Badge */}
            {image.type && (
              <div className="absolute top-2 left-2 px-2 py-1 rounded-full text-xs font-medium bg-black/50 text-white">
                {image.type}
              </div>
            )}
            
            {/* Hover Overlay */}
            <div className="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
              <span className="text-white text-sm font-medium">View</span>
            </div>
          </motion.div>
        ))}
      </div>

      {/* Lightbox */}
      <AnimatePresence>
        {selectedIndex !== null && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 z-50 bg-black/90 flex items-center justify-center p-4"
            onClick={() => setSelectedIndex(null)}
          >
            <div className="relative max-w-4xl max-h-full">
              {/* Close Button */}
              <button
                onClick={(e) => { e.stopPropagation(); setSelectedIndex(null); }}
                className="absolute -top-12 right-0 text-white hover:text-gold transition-colors"
              >
                <X className="w-8 h-8" />
              </button>

              {/* Image */}
              <img
                src={images[selectedIndex].url}
                alt={images[selectedIndex].alt}
                className="max-w-full max-h-[80vh] object-contain rounded-lg"
              />

              {/* Navigation */}
              <div className="absolute inset-y-0 left-0 right-0 flex items-center justify-between px-4">
                <button
                  onClick={(e) => { e.stopPropagation(); prevImage(); }}
                  className="p-2 rounded-full bg-white/10 hover:bg-white/20 text-white transition-colors"
                >
                  <ChevronLeft className="w-6 h-6" />
                </button>
                <button
                  onClick={(e) => { e.stopPropagation(); nextImage(); }}
                  className="p-2 rounded-full bg-white/10 hover:bg-white/20 text-white transition-colors"
                >
                  <ChevronRight className="w-6 h-6" />
                </button>
              </div>

              {/* Counter */}
              <div className="absolute bottom-4 left-1/2 -translate-x-1/2 text-white text-sm">
                {selectedIndex + 1} / {images.length}
              </div>
            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
}
