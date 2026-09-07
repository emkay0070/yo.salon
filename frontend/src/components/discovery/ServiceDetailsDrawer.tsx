'use client';

import { motion } from 'framer-motion';
import { Clock, DollarSign, Star, Scissors, MapPin, ArrowRight, X } from 'lucide-react';
import { useRouter } from 'next/navigation';
import SideDrawer from '@/components/shared/SideDrawer';

interface ServiceDetailsDrawerProps {
  isOpen: boolean;
  onClose: () => void;
  service: {
    id: string;
    name: string;
    description: string;
    price: number;
    duration: number;
    category: string;
    image_url?: string | null;
    rating?: number;
    review_count?: number;
    provider?: {
      id: string;
      name: string;
      slug?: string;
      address?: string;
      city?: string;
    };
    available_specialists?: Array<{
      id: string;
      name: string;
      photo_url?: string | null;
    }>;
  };
}

export default function ServiceDetailsDrawer({ isOpen, onClose, service }: ServiceDetailsDrawerProps) {
  const router = useRouter();

  const handleBook = () => {
    const params = new URLSearchParams();
    params.set('service_id', service.id);
    if (service.provider) {
      params.set('provider_id', service.provider.id);
    }
    router.push(`/book?${params.toString()}`);
    onClose();
  };

  const handleViewService = () => {
    router.push(`/services/${service.id}`);
    onClose();
  };

  const handleViewSalon = () => {
    if (service.provider?.slug) {
      router.push(`/salons/${service.provider.slug}`);
      onClose();
    }
  };

  const handleViewSpecialist = (specialistId: string) => {
    router.push(`/specialists/${specialistId}`);
    onClose();
  };

  return (
    <SideDrawer isOpen={isOpen} onClose={onClose} title="Service Details" position="right" width="wide">
      <div className="space-y-6">
        {/* Service Image */}
        {service.image_url && (
          <motion.div
            initial={{ opacity: 0, scale: 0.95 }}
            animate={{ opacity: 1, scale: 1 }}
            transition={{ delay: 0.1 }}
            className="relative h-48 rounded-xl overflow-hidden"
            style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
          >
            <img
              src={service.image_url}
              alt={service.name}
              className="w-full h-full object-cover"
            />
          </motion.div>
        )}

        {/* Service Info */}
        <motion.div
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.15 }}
        >
          <div className="flex items-center gap-2 mb-2">
            <span className="text-xs font-medium px-2 py-0.5 rounded-full"
              style={{
                backgroundColor: 'var(--brand-primary, #FFD700)20',
                color: 'var(--brand-primary, #FFD700)'
              }}
            >
              {service.category}
            </span>
            {service.rating && (
              <div className="flex items-center gap-1">
                <Star className="w-3 h-3" style={{ color: 'var(--brand-primary, #FFD700)' }} fill="currentColor" />
                <span className="text-xs text-text-secondary">{service.rating}</span>
                <span className="text-xs text-text-secondary">({service.review_count || 0})</span>
              </div>
            )}
          </div>
          <h3 className="text-xl font-bold text-text-primary mb-2">{service.name}</h3>
          <p className="text-sm text-text-secondary line-clamp-3">{service.description}</p>
        </motion.div>

        {/* Price & Duration */}
        <motion.div
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.2 }}
          className="flex items-center gap-6 p-4 rounded-xl"
          style={{
            backgroundColor: 'var(--brand-primary, #FFD700)10',
            borderRadius: 'var(--brand-border-radius, 16px)'
          }}
        >
          <div className="flex items-center gap-2">
            <Clock className="w-4 h-4 text-text-secondary" />
            <span className="text-sm text-text-primary">{service.duration} min</span>
          </div>
          <div className="flex items-center gap-2">
            <DollarSign className="w-4 h-4" style={{ color: 'var(--brand-primary, #FFD700)' }} />
            <span className="text-sm font-semibold" style={{ color: 'var(--brand-primary, #FFD700)' }}>
              {service.price.toLocaleString('en-UG', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}
            </span>
          </div>
        </motion.div>

        {/* Available At */}
        {service.provider && (
          <motion.div
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.25 }}
          >
            <h4 className="text-sm font-semibold text-text-primary mb-3">Available At</h4>
            <div
              className="p-4 rounded-xl border cursor-pointer hover:border-gold/30 transition-all"
              style={{
                borderColor: 'var(--border-light, rgba(255,255,255,0.1))',
                borderRadius: 'var(--brand-border-radius, 16px)'
              }}
              onClick={handleViewSalon}
            >
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-full flex items-center justify-center text-white text-sm font-bold"
                  style={{
                    background: 'linear-gradient(to bottom right, var(--brand-primary, #FFD700), var(--brand-secondary, #C9A227))',
                    borderRadius: 'var(--brand-border-radius, 16px)'
                  }}
                >
                  {service.provider.name.charAt(0)}
                </div>
                <div className="flex-1">
                  <p className="text-sm font-semibold text-text-primary">{service.provider.name}</p>
                  <div className="flex items-center gap-1 text-xs text-text-secondary">
                    <MapPin className="w-3 h-3" />
                    <span>{service.provider.city || service.provider.address}</span>
                  </div>
                </div>
                <ArrowRight className="w-4 h-4 text-text-secondary" />
              </div>
            </div>
          </motion.div>
        )}

        {/* Specialists */}
        {service.available_specialists && service.available_specialists.length > 0 && (
          <motion.div
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.3 }}
          >
            <h4 className="text-sm font-semibold text-text-primary mb-3">Specialists</h4>
            <div className="flex gap-2">
              {service.available_specialists.slice(0, 4).map((specialist) => (
                <button
                  key={specialist.id}
                  onClick={() => handleViewSpecialist(specialist.id)}
                  className="w-12 h-12 rounded-full border-2 border-transparent hover:border-gold/30 transition-all overflow-hidden flex-shrink-0"
                  style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
                >
                  {specialist.photo_url ? (
                    <img
                      src={specialist.photo_url}
                      alt={specialist.name}
                      className="w-full h-full object-cover"
                    />
                  ) : (
                    <div className="w-full h-full flex items-center justify-center text-white text-xs font-bold"
                      style={{
                        background: 'linear-gradient(to bottom right, var(--brand-primary, #FFD700), var(--brand-secondary, #C9A227))'
                      }}
                    >
                      {specialist.name.charAt(0)}
                    </div>
                  )}
                </button>
              ))}
            </div>
          </motion.div>
        )}

        {/* Book CTA */}
        <motion.button
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.35 }}
          whileHover={{ scale: 1.02 }}
          whileTap={{ scale: 0.98 }}
          onClick={handleBook}
          className="w-full py-3 text-white font-semibold rounded-xl transition-all flex items-center justify-center gap-2"
          style={{
            backgroundColor: 'var(--brand-primary, #FFD700)',
            borderRadius: 'var(--brand-border-radius, 16px)'
          }}
        >
          <Scissors className="w-4 h-4" />
          Book this service
        </motion.button>

        {/* Explore Further */}
        <motion.div
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.4 }}
          className="pt-4 border-t"
          style={{ borderColor: 'var(--border-light, rgba(255,255,255,0.1))' }}
        >
          <p className="text-xs text-text-secondary mb-3 uppercase tracking-wider">Explore further</p>
          <div className="space-y-2">
            <button
              onClick={handleViewService}
              className="w-full flex items-center justify-between p-3 rounded-xl hover:bg-white/5 transition-all text-sm text-text-secondary hover:text-text-primary"
              style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
            >
              <span>View service</span>
              <ArrowRight className="w-4 h-4" />
            </button>
            {service.provider && (
              <button
                onClick={handleViewSalon}
                className="w-full flex items-center justify-between p-3 rounded-xl hover:bg-white/5 transition-all text-sm text-text-secondary hover:text-text-primary"
                style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
              >
                <span>View salon</span>
                <ArrowRight className="w-4 h-4" />
              </button>
            )}
          </div>
        </motion.div>
      </div>
    </SideDrawer>
  );
}
