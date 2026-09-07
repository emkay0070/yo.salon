'use client';

import { motion } from 'framer-motion';
import { Star, MapPin, Clock, Scissors, Users, ArrowRight, Calendar, Building2 } from 'lucide-react';
import { useRouter } from 'next/navigation';
import SideDrawer from '@/components/shared/SideDrawer';

interface SalonDetailsDrawerProps {
  isOpen: boolean;
  onClose: () => void;
  salon: {
    id: string;
    name: string;
    slug: string;
    address?: string;
    city?: string;
    rating?: number;
    review_count?: number;
    image_url?: string | null;
    is_open?: boolean;
    opening_hours?: string;
    services?: Array<{
      id: string;
      name: string;
      price: number;
      duration: number;
    }>;
    specialists?: Array<{
      id: string;
      name: string;
      photo_url?: string | null;
      role?: string;
    }>;
  };
}

export default function SalonDetailsDrawer({ isOpen, onClose, salon }: SalonDetailsDrawerProps) {
  const router = useRouter();

  const handleBook = () => {
    const params = new URLSearchParams();
    params.set('provider_id', salon.id);
    router.push(`/book?${params.toString()}`);
    onClose();
  };

  const handleViewSalon = () => {
    router.push(`/salons/${salon.slug}`);
    onClose();
  };

  const handleViewSpecialist = (specialistId: string) => {
    router.push(`/specialists/${specialistId}`);
    onClose();
  };

  const handleViewService = (serviceId: string) => {
    router.push(`/services/${serviceId}`);
    onClose();
  };

  return (
    <SideDrawer isOpen={isOpen} onClose={onClose} title="Salon Details" position="right" width="wide">
      <div className="space-y-6">
        {/* Salon Cover Image */}
        {salon.image_url && (
          <motion.div
            initial={{ opacity: 0, scale: 0.95 }}
            animate={{ opacity: 1, scale: 1 }}
            transition={{ delay: 0.1 }}
            className="relative h-48 rounded-xl overflow-hidden"
            style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
          >
            <img
              src={salon.image_url}
              alt={salon.name}
              className="w-full h-full object-cover"
            />
          </motion.div>
        )}

        {/* Salon Info */}
        <motion.div
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.15 }}
        >
          <div className="flex items-center gap-2 mb-2">
            {salon.is_open !== undefined && (
              <div className="flex items-center gap-1">
                <div className={`w-2 h-2 rounded-full ${salon.is_open ? 'bg-green-500' : 'bg-red-500'}`} />
                <span className="text-xs text-text-secondary">{salon.is_open ? 'Open Now' : 'Closed'}</span>
              </div>
            )}
            {salon.rating && (
              <div className="flex items-center gap-1">
                <Star className="w-3 h-3" style={{ color: 'var(--brand-primary, #FFD700)' }} fill="currentColor" />
                <span className="text-xs text-text-secondary">{salon.rating}</span>
                <span className="text-xs text-text-secondary">({salon.review_count || 0})</span>
              </div>
            )}
          </div>
          <h3 className="text-xl font-bold text-text-primary mb-2">{salon.name}</h3>
          <div className="flex items-center gap-2 text-sm text-text-secondary">
            <MapPin className="w-4 h-4" />
            <span>{salon.city || salon.address}</span>
          </div>
          {salon.opening_hours && (
            <div className="flex items-center gap-2 text-sm text-text-secondary mt-1">
              <Clock className="w-4 h-4" />
              <span>{salon.opening_hours}</span>
            </div>
          )}
        </motion.div>

        {/* Services */}
        {salon.services && salon.services.length > 0 && (
          <motion.div
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.2 }}
          >
            <h4 className="text-sm font-semibold text-text-primary mb-3 flex items-center gap-2">
              <Scissors className="w-4 h-4 text-text-secondary" />
              Services
            </h4>
            <div className="space-y-2">
              {salon.services.slice(0, 3).map((service) => (
                <button
                  key={service.id}
                  onClick={() => handleViewService(service.id)}
                  className="w-full p-3 rounded-xl border hover:border-gold/30 transition-all text-left"
                  style={{
                    borderColor: 'var(--border-light, rgba(255,255,255,0.1))',
                    borderRadius: 'var(--brand-border-radius, 16px)'
                  }}
                >
                  <div className="flex items-center justify-between">
                    <div>
                      <p className="text-sm font-medium text-text-primary">{service.name}</p>
                      <div className="flex items-center gap-2 text-xs text-text-secondary mt-1">
                        <span>{service.duration} min</span>
                      </div>
                    </div>
                    <span className="text-sm font-semibold" style={{ color: 'var(--brand-primary, #FFD700)' }}>
                      {service.price.toLocaleString('en-UG', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}
                    </span>
                  </div>
                </button>
              ))}
            </div>
          </motion.div>
        )}

        {/* Specialists */}
        {salon.specialists && salon.specialists.length > 0 && (
          <motion.div
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.25 }}
          >
            <h4 className="text-sm font-semibold text-text-primary mb-3 flex items-center gap-2">
              <Users className="w-4 h-4 text-text-secondary" />
              Specialists
            </h4>
            <div className="space-y-2">
              {salon.specialists.slice(0, 3).map((specialist) => (
                <button
                  key={specialist.id}
                  onClick={() => handleViewSpecialist(specialist.id)}
                  className="w-full p-3 rounded-xl border hover:border-gold/30 transition-all text-left"
                  style={{
                    borderColor: 'var(--border-light, rgba(255,255,255,0.1))',
                    borderRadius: 'var(--brand-border-radius, 16px)'
                  }}
                >
                  <div className="flex items-center gap-3">
                    <div className="w-10 h-10 rounded-full overflow-hidden flex-shrink-0"
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
                    </div>
                    <div className="flex-1">
                      <p className="text-sm font-medium text-text-primary">{specialist.name}</p>
                      {specialist.role && (
                        <p className="text-xs text-text-secondary">{specialist.role}</p>
                      )}
                    </div>
                    <ArrowRight className="w-4 h-4 text-text-secondary" />
                  </div>
                </button>
              ))}
            </div>
          </motion.div>
        )}

        {/* Book CTA */}
        <motion.button
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.3 }}
          whileHover={{ scale: 1.02 }}
          whileTap={{ scale: 0.98 }}
          onClick={handleBook}
          className="w-full py-3 text-white font-semibold rounded-xl transition-all flex items-center justify-center gap-2"
          style={{
            backgroundColor: 'var(--brand-primary, #FFD700)',
            borderRadius: 'var(--brand-border-radius, 16px)'
          }}
        >
          <Calendar className="w-4 h-4" />
          Book at {salon.name}
        </motion.button>

        {/* Explore Further */}
        <motion.div
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.35 }}
          className="pt-4 border-t"
          style={{ borderColor: 'var(--border-light, rgba(255,255,255,0.1))' }}
        >
          <p className="text-xs text-text-secondary mb-3 uppercase tracking-wider">Explore further</p>
          <div className="space-y-2">
            <button
              onClick={handleViewSalon}
              className="w-full flex items-center justify-between p-3 rounded-xl hover:bg-white/5 transition-all text-sm text-text-secondary hover:text-text-primary"
              style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
            >
              <div className="flex items-center gap-2">
                <Building2 className="w-4 h-4" />
                <span>View salon profile</span>
              </div>
              <ArrowRight className="w-4 h-4" />
            </button>
          </div>
        </motion.div>
      </div>
    </SideDrawer>
  );
}
