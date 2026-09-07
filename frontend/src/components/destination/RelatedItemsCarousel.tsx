'use client';

import { useRef, useState } from 'react';
import { motion } from 'framer-motion';
import { ChevronLeft, ChevronRight } from 'lucide-react';

interface RelatedItem {
  id: string;
  name: string;
  image?: string;
  subtitle?: string;
  rating?: number;
  onClick?: () => void;
}

interface RelatedItemsCarouselProps {
  title: string;
  items: RelatedItem[];
  variant?: 'services' | 'specialists' | 'salons';
}

export function RelatedItemsCarousel({ title, items, variant = 'services' }: RelatedItemsCarouselProps) {
  const scrollRef = useRef<HTMLDivElement>(null);
  const [canScrollLeft, setCanScrollLeft] = useState(false);
  const [canScrollRight, setCanScrollRight] = useState(true);

  const scroll = (direction: 'left' | 'right') => {
    if (scrollRef.current) {
      const scrollAmount = 300;
      scrollRef.current.scrollBy({
        left: direction === 'left' ? -scrollAmount : scrollAmount,
        behavior: 'smooth'
      });
    }
  };

  const handleScroll = () => {
    if (scrollRef.current) {
      setCanScrollLeft(scrollRef.current.scrollLeft > 0);
      setCanScrollRight(
        scrollRef.current.scrollLeft <
        scrollRef.current.scrollWidth - scrollRef.current.clientWidth
      );
    }
  };

  if (!items || items.length === 0) {
    return null;
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h3 className="text-xl font-semibold text-text-primary">{title}</h3>
        <div className="flex gap-2">
          <button
            onClick={() => scroll('left')}
            disabled={!canScrollLeft}
            className="p-2 rounded-full bg-surface border border-border-light hover:border-gold/30 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <ChevronLeft className="w-5 h-5 text-text-primary" />
          </button>
          <button
            onClick={() => scroll('right')}
            disabled={!canScrollRight}
            className="p-2 rounded-full bg-surface border border-border-light hover:border-gold/30 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <ChevronRight className="w-5 h-5 text-text-primary" />
          </button>
        </div>
      </div>

      <div
        ref={scrollRef}
        onScroll={handleScroll}
        className="flex gap-4 overflow-x-auto pb-4 scrollbar-hide"
        style={{ scrollSnapType: 'x mandatory' }}
      >
        {items.map((item, index) => (
          <motion.div
            key={item.id}
            initial={{ opacity: 0, x: 20 }}
            animate={{ opacity: 1, x: 0 }}
            transition={{ delay: index * 0.05 }}
            onClick={item.onClick}
            className="flex-shrink-0 w-64 bg-surface border border-border-light rounded-xl p-4 cursor-pointer hover:border-gold/30 transition-all group"
            style={{
              borderRadius: 'var(--brand-border-radius, 16px)',
              boxShadow: 'var(--brand-shadow-sm, 0 2px 8px rgba(0,0,0,0.05))',
              scrollSnapAlign: 'start'
            }}
          >
            {item.image && (
              <div className="w-full h-32 rounded-lg mb-3 overflow-hidden bg-surface/50">
                <img
                  src={item.image}
                  alt={item.name}
                  className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                />
              </div>
            )}

            <h4 className="font-semibold text-text-primary mb-1">{item.name}</h4>
            
            {item.subtitle && (
              <p className="text-sm text-text-secondary mb-2">{item.subtitle}</p>
            )}

            {item.rating && (
              <div className="flex items-center gap-1">
                <span className="text-sm font-semibold" style={{ color: 'var(--brand-primary, #FFD700)' }}>
                  ⭐ {item.rating}
                </span>
              </div>
            )}
          </motion.div>
        ))}
      </div>
    </div>
  );
}
