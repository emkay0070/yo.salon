'use client';

import { motion } from 'framer-motion';
import { Clock, DollarSign, ArrowRight } from 'lucide-react';

interface Service {
  id: string;
  name: string;
  category?: string;
  price: number;
  duration: number;
  image?: string;
}

interface ServiceGridProps {
  services: Service[];
  onServiceClick?: (service: Service) => void;
  variant?: 'compact' | 'full';
  columns?: number;
}

export function ServiceGrid({ services, onServiceClick, variant = 'full', columns = 3 }: ServiceGridProps) {
  if (!services || services.length === 0) {
    return (
      <div className="text-center py-12 text-text-secondary">
        <p>No services available</p>
      </div>
    );
  }

  const gridCols = `grid-cols-1 md:grid-cols-${Math.min(columns, services.length)}`;

  return (
    <div className="space-y-4">
      <h3 className="text-xl font-semibold text-text-primary">Services</h3>
      
      <div className={`grid ${gridCols} gap-4`}>
        {services.map((service, index) => (
          <motion.div
            key={service.id}
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: index * 0.05 }}
            onClick={() => onServiceClick?.(service)}
            className="bg-surface border border-border-light rounded-xl p-4 cursor-pointer hover:border-gold/30 transition-all group"
            style={{
              borderRadius: 'var(--brand-border-radius, 16px)',
              boxShadow: 'var(--brand-shadow-sm, 0 2px 8px rgba(0,0,0,0.05))'
            }}
          >
            {variant === 'full' && service.image && (
              <div className="w-full h-32 rounded-lg mb-4 overflow-hidden bg-surface/50">
                <img
                  src={service.image}
                  alt={service.name}
                  className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                />
              </div>
            )}

            {service.category && (
              <span className="text-xs text-text-secondary mb-1 block">{service.category}</span>
            )}
            
            <h4 className="font-semibold text-text-primary mb-2">{service.name}</h4>
            
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-3">
                <div className="flex items-center gap-1 text-text-secondary">
                  <Clock className="w-4 h-4" />
                  <span className="text-sm">{service.duration} min</span>
                </div>
                <div className="flex items-center gap-1 font-semibold" style={{ color: 'var(--brand-primary, #FFD700)' }}>
                  <DollarSign className="w-4 h-4" />
                  <span className="text-sm">{service.price.toLocaleString('en-UG', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}</span>
                </div>
              </div>
              
              <ArrowRight className="w-5 h-5 text-text-secondary group-hover:text-gold transition-colors" />
            </div>
          </motion.div>
        ))}
      </div>
    </div>
  );
}
