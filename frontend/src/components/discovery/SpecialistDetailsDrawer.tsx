'use client';

import { motion } from 'framer-motion';
import { Star, MapPin, Scissors, Award, ArrowRight, Calendar, Briefcase } from 'lucide-react';
import { useRouter } from 'next/navigation';
import SideDrawer from '@/components/shared/SideDrawer';

interface SpecialistDetailsDrawerProps {
  isOpen: boolean;
  onClose: () => void;
  specialist: {
    id: string;
    name: string;
    role?: string;
    bio?: string;
    specialties?: string[];
    rating?: number;
    review_count?: number;
    photo_url?: string | null;
    provider?: {
      id: string;
      name: string;
      slug?: string;
      address?: string;
      city?: string;
    };
    services?: Array<{
      id: string;
      name: string;
      price: number;
      duration: number;
    }>;
  };
}

export default function SpecialistDetailsDrawer({ isOpen, onClose, specialist }: SpecialistDetailsDrawerProps) {
  const router = useRouter();

  const handleBook = () => {
    const params = new URLSearchParams();
    params.set('specialist_id', specialist.id);
    if (specialist.provider) {
      params.set('provider_id', specialist.provider.id);
    }
    router.push(`/book?${params.toString()}`);
    onClose();
  };

  const handleViewSpecialist = () => {
    router.push(`/specialists/${specialist.id}`);
    onClose();
  };

  const handleViewSalon = () => {
    if (specialist.provider?.slug) {
      router.push(`/salons/${specialist.provider.slug}`);
      onClose();
    }
  };

  const handleViewService = (serviceId: string) => {
    router.push(`/services/${serviceId}`);
    onClose();
  };

  return (
    <SideDrawer isOpen={isOpen} onClose={onClose} title="Specialist Details" position="right" width="wide">
      <div className="space-y-6">
        {/* Specialist Photo */}
        <motion.div
          initial={{ opacity: 0, scale: 0.95 }}
          animate={{ opacity: 1, scale: 1 }}
          transition={{ delay: 0.1 }}
          className="relative h-48 rounded-xl overflow-hidden flex items-center justify-center"
          style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
        >
          {specialist.photo_url ? (
            <img
              src={specialist.photo_url}
              alt={specialist.name}
              className="w-full h-full object-cover"
            />
          ) : (
            <div className="w-full h-full flex items-center justify-center text-white text-4xl font-bold"
              style={{
                background: 'linear-gradient(to bottom right, var(--brand-primary, #FFD700), var(--brand-secondary, #C9A227))'
              }}
            >
              {specialist.name.charAt(0)}
            </div>
          )}
        </motion.div>

        {/* Specialist Info */}
        <motion.div
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.15 }}
        >
          <div className="flex items-center gap-2 mb-2">
            {specialist.role && (
              <span className="text-xs font-medium px-2 py-0.5 rounded-full"
                style={{
                  backgroundColor: 'var(--brand-primary, #FFD700)20',
                  color: 'var(--brand-primary, #FFD700)'
                }}
              >
                {specialist.role}
              </span>
            )}
            {specialist.rating && (
              <div className="flex items-center gap-1">
                <Star className="w-3 h-3" style={{ color: 'var(--brand-primary, #FFD700)' }} fill="currentColor" />
                <span className="text-xs text-text-secondary">{specialist.rating}</span>
                <span className="text-xs text-text-secondary">({specialist.review_count || 0})</span>
              </div>
            )}
          </div>
          <h3 className="text-xl font-bold text-text-primary mb-2">{specialist.name}</h3>
          {specialist.bio && (
            <p className="text-sm text-text-secondary line-clamp-3">{specialist.bio}</p>
          )}
        </motion.div>

        {/* Expertise */}
        {specialist.specialties && specialist.specialties.length > 0 && (
          <motion.div
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.2 }}
          >
            <h4 className="text-sm font-semibold text-text-primary mb-3 flex items-center gap-2">
              <Award className="w-4 h-4" style={{ color: 'var(--brand-primary, #FFD700)' }} />
              Expertise
            </h4>
            <div className="flex flex-wrap gap-2">
              {specialist.specialties.slice(0, 4).map((specialty, index) => (
                <span
                  key={index}
                  className="text-xs px-3 py-1 rounded-full"
                  style={{
                    backgroundColor: 'var(--brand-primary, #FFD700)10',
                    color: 'var(--brand-primary, #FFD700)',
                    border: '1px solid var(--brand-primary, #FFD700)20'
                  }}
                >
                  {specialty}
                </span>
              ))}
            </div>
          </motion.div>
        )}

        {/* Works At */}
        {specialist.provider && (
          <motion.div
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.25 }}
          >
            <h4 className="text-sm font-semibold text-text-primary mb-3 flex items-center gap-2">
              <Briefcase className="w-4 h-4 text-text-secondary" />
              Works At
            </h4>
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
                  {specialist.provider.name.charAt(0)}
                </div>
                <div className="flex-1">
                  <p className="text-sm font-semibold text-text-primary">{specialist.provider.name}</p>
                  <div className="flex items-center gap-1 text-xs text-text-secondary">
                    <MapPin className="w-3 h-3" />
                    <span>{specialist.provider.city || specialist.provider.address}</span>
                  </div>
                </div>
                <ArrowRight className="w-4 h-4 text-text-secondary" />
              </div>
            </div>
          </motion.div>
        )}

        {/* Services */}
        {specialist.services && specialist.services.length > 0 && (
          <motion.div
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.3 }}
          >
            <h4 className="text-sm font-semibold text-text-primary mb-3 flex items-center gap-2">
              <Scissors className="w-4 h-4 text-text-secondary" />
              Services
            </h4>
            <div className="space-y-2">
              {specialist.services.slice(0, 3).map((service) => (
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
          <Calendar className="w-4 h-4" />
          Book with {specialist.name}
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
              onClick={handleViewSpecialist}
              className="w-full flex items-center justify-between p-3 rounded-xl hover:bg-white/5 transition-all text-sm text-text-secondary hover:text-text-primary"
              style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
            >
              <span>View specialist profile</span>
              <ArrowRight className="w-4 h-4" />
            </button>
            {specialist.provider && (
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
