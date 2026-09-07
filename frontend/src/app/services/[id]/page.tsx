'use client';

import { useEffect, useState, use } from 'react';
import { useRouter } from 'next/navigation';
import { motion } from 'framer-motion';
import { Clock, DollarSign, User, ArrowRight, Shield, Info, Loader2, Calendar, CheckCircle, XCircle, AlertCircle, Scissors, Award, Tag } from 'lucide-react';
import { apiClient } from '@/lib/api-client';
import { HeroCard } from '@/components/destination/HeroCard';
import { Gallery } from '@/components/destination/Gallery';
import { Reviews } from '@/components/destination/Reviews';
import { AvailabilityPanel } from '@/components/destination/AvailabilityPanel';
import { BookCTA } from '@/components/destination/BookCTA';
import { SpecialistGrid } from '@/components/destination/SpecialistGrid';
import { LocationCard } from '@/components/destination/LocationCard';
import { FAQAccordion } from '@/components/destination/FAQAccordion';
import { ShareFavoriteActions } from '@/components/destination/ShareFavoriteActions';

interface ServiceData {
  id: string;
  name: string;
  description: string;
  preparation: string | null;
  aftercare: string | null;
  cancellation_policy: string | null;
  price: number;
  duration: number;
  buffer_before: number;
  buffer_after: number;
  requires_specialist: boolean;
  max_concurrent: number;
  min_booking_notice: number;
  max_booking_days_ahead: number;
  cancellation_cutoff_hours: number;
  category: string;
  image_path: string | null;
  image_url: string | null;
  images: string[] | null;
  active: boolean;
  provider: any;
  available_specialists: any[];
  what_included?: string[];
  suitable_for?: string[];
  popularity?: number;
  rating?: number;
  review_count?: number;
  salons?: any[];
  reviews?: any[];
}

function ServiceDetailPageContent({ serviceId }: { serviceId: string }) {
  const router = useRouter();
  const [service, setService] = useState<ServiceData | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');
  const [isFavorited, setIsFavorited] = useState(false);

  useEffect(() => {
    async function loadService() {
      try {
        const data = await apiClient.get(`/services/${serviceId}`);

        // Load scoped reviews for this service
        const reviewsData = await apiClient.get(`/reviews?subject_type=service&subject_id=${serviceId}`);

        // Merge reviews into service data
        setService({ ...data, reviews: reviewsData.data });
      } catch (err: any) {
        console.error('Failed to load service:', err);
        setError(err.response?.data?.message || 'Failed to load service');
      } finally {
        setIsLoading(false);
      }
    }

    loadService();
  }, [serviceId]);

  const handleBook = () => {
    if (!service) return;
    router.push(`/book?service_id=${service.id}`);
  };

  const handleFavorite = () => {
    setIsFavorited(!isFavorited);
    // TODO: Implement favorite API call
  };

  if (isLoading) {
    return (
      <div className="min-h-screen bg-background flex items-center justify-center">
        <Loader2 className="w-10 h-10 animate-spin" style={{ color: 'var(--brand-primary, #FFD700)' }} />
      </div>
    );
  }

  if (error || !service) {
    return (
      <div className="min-h-screen bg-background flex flex-col items-center justify-center text-text-primary">
        <h1 className="text-3xl font-bold mb-4">Service Not Found</h1>
        <p className="text-text-secondary mb-4">{error || 'We couldn\'t find this service.'}</p>
        <button
          onClick={() => router.back()}
          className="px-6 py-2 text-white font-medium rounded-xl transition-colors"
          style={{
            background: `linear-gradient(to right, var(--brand-primary, #FFD700), var(--brand-secondary, #C9A227))`,
            borderRadius: 'var(--brand-border-radius, 16px)'
          }}
        >
          Go Back
        </button>
      </div>
    );
  }

  const badges = [
    { icon: Clock, label: 'Duration', value: `${service.duration} min` },
    { icon: DollarSign, label: 'Price', value: `UGX ${service.price?.toLocaleString('en-UG', { minimumFractionDigits: 0, maximumFractionDigits: 0 }) || '0'}` },
  ];

  if (service.popularity) {
    badges.push({ icon: Award, label: 'Popularity', value: `${service.popularity} bookings` });
  }

  const faqs = [
    { question: 'How long does this service take?', answer: `${service.duration || 0} minutes including consultation and styling.` },
    { question: 'What preparation is needed?', answer: service.preparation || 'No special preparation required.' },
    { question: 'What aftercare is recommended?', answer: service.aftercare || 'Standard aftercare instructions will be provided.' },
    { question: 'What is the cancellation policy?', answer: service.cancellation_policy || `Cancel at least ${service.cancellation_cutoff_hours || 24} hours before your appointment.` },
  ];

  return (
    <div className="min-h-screen bg-background pb-24">
      <div className="container mx-auto px-6 max-w-5xl py-8">
        {/* Back Button */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          className="mb-6"
        >
          <button
            onClick={() => router.back()}
            className="text-text-secondary hover:text-text-primary text-sm flex items-center gap-2 transition-colors"
          >
            <ArrowRight className="w-4 h-4 rotate-180" />
            Back
          </button>
        </motion.div>

        {/* Hero Card */}
        <HeroCard
          title={service.name}
          subtitle={service.category}
          description={service.description}
          image={service.image_url || undefined}
          badges={badges}
          rating={service.rating}
          reviewCount={service.review_count}
          primaryAction={{ label: 'Book Now', onClick: handleBook }}
          secondaryActions={[
            { label: 'View Availability', onClick: () => {} }
          ]}
          variant="service"
        />

        {/* Share/Favorite */}
        <div className="flex justify-end mb-8">
          <ShareFavoriteActions
            isFavorited={isFavorited}
            onFavorite={handleFavorite}
            shareUrl={typeof window !== 'undefined' ? window.location.href : ''}
          />
        </div>

        {/* Description */}
        <motion.section
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.1 }}
          className="mb-12"
        >
          <h3 className="text-xl font-semibold text-text-primary mb-4">About This Service</h3>
          <p className="text-text-secondary leading-relaxed">{service.description}</p>
        </motion.section>

        {/* What's Included */}
        {service.what_included && service.what_included.length > 0 && (
          <motion.section
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.2 }}
            className="mb-12"
          >
            <h3 className="text-xl font-semibold text-text-primary mb-4">What's Included</h3>
            <div className="grid md:grid-cols-2 gap-4">
              {service.what_included.map((item, index) => (
                <div key={index} className="flex items-center gap-3 text-text-secondary">
                  <CheckCircle className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
                  <span>{item}</span>
                </div>
              ))}
            </div>
          </motion.section>
        )}

        {/* Suitable For */}
        {service.suitable_for && service.suitable_for.length > 0 && (
          <motion.section
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.3 }}
            className="mb-12"
          >
            <h3 className="text-xl font-semibold text-text-primary mb-4">Suitable For</h3>
            <div className="flex flex-wrap gap-2">
              {service.suitable_for.map((item, index) => (
                <span
                  key={index}
                  className="px-4 py-2 rounded-full border text-sm"
                  style={{
                    backgroundColor: 'var(--brand-primary, #FFD700)10',
                    borderColor: 'var(--brand-primary, #FFD700)20',
                    color: 'var(--brand-primary, #FFD700)',
                    borderRadius: 'var(--brand-border-radius, 16px)'
                  }}
                >
                  {item}
                </span>
              ))}
            </div>
          </motion.section>
        )}

        {/* Specialists Offering This Service */}
        {service.available_specialists && service.available_specialists.length > 0 && (
          <motion.section
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.4 }}
            className="mb-12"
          >
            <SpecialistGrid
              specialists={service.available_specialists.map(s => ({
                id: s.id,
                name: s.name,
                role: s.role,
                rating: s.rating,
                reviewCount: s.review_count,
                nextAvailable: s.next_available,
                specializations: s.specializations,
                salon: s.salon?.name
              }))}
              onSpecialistClick={(specialist) => router.push(`/specialists/${specialist.id}`)}
              columns={4}
            />
          </motion.section>
        )}

        {/* Salons Offering This Service */}
        {service.salons && service.salons.length > 0 && (
          <motion.section
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.5 }}
            className="mb-12"
          >
            <h3 className="text-xl font-semibold text-text-primary mb-4">Salons Offering This Service</h3>
            <div className="grid md:grid-cols-2 gap-4">
              {service.salons.map((salon: any) => (
                <LocationCard
                  key={salon.id}
                  name={salon.name}
                  address={salon.address}
                  phone={salon.phone}
                  rating={salon.rating}
                  isOpen={salon.is_open}
                  openingHours={salon.opening_hours}
                  onBook={() => router.push(`/salons/${salon.slug}/book?service=${service.id}`)}
                  onNavigate={() => window.open(salon.maps_url, '_blank')}
                  variant="compact"
                />
              ))}
            </div>
          </motion.section>
        )}

        {/* Gallery */}
        {service.images && service.images.length > 0 && (
          <motion.section
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.6 }}
            className="mb-12"
          >
            <Gallery
              images={service.images.map(url => ({ url, type: 'after' }))}
              variant="service"
            />
          </motion.section>
        )}

        {/* Reviews */}
        {service.reviews && service.reviews.length > 0 && (
          <motion.section
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.7 }}
            className="mb-12"
          >
            <Reviews
              reviews={service.reviews}
              averageRating={service.rating}
              totalReviews={service.review_count}
            />
          </motion.section>
        )}

        {/* FAQs */}
        <motion.section
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.8 }}
          className="mb-12"
        >
          <FAQAccordion faqs={faqs} />
        </motion.section>
      </div>

      {/* Floating Book CTA */}
      <BookCTA
        serviceName={service.name}
        price={service.price}
        duration={service.duration}
        onBook={handleBook}
        variant="floating"
      />
    </div>
  );
}

export default function ServiceDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  return <ServiceDetailPageContent serviceId={id} />;
}
