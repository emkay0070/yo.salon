'use client';

import { useEffect, useState, use } from 'react';
import { useRouter } from 'next/navigation';
import { motion } from 'framer-motion';
import { MapPin, Phone, Mail, Clock, ArrowRight, Scissors, Star, User, Calendar, Shield, Loader2, Info, Award, Building2, Globe } from 'lucide-react';
import { apiClient } from '@/lib/api-client';
import { HeroCard } from '@/components/destination/HeroCard';
import { Gallery } from '@/components/destination/Gallery';
import { Reviews } from '@/components/destination/Reviews';
import { ServiceGrid } from '@/components/destination/ServiceGrid';
import { SpecialistGrid } from '@/components/destination/SpecialistGrid';
import { LocationCard } from '@/components/destination/LocationCard';
import { ShareFavoriteActions } from '@/components/destination/ShareFavoriteActions';

interface ProviderData {
  id: string;
  type: string;
  display_name: string;
  slug: string;
  description: string;
  logo: string | null;
  cover_image: string | null;
  phone: string;
  email: string;
  website: string;
  location: any;
  social_links: any;
  timezone: string;
  rating: number;
  review_count: number;
  operating_hours: any[];
  first_available_slot: any;
  specialists: any[];
  services: any[];
  reviews: any[];
  story?: string;
  mission?: string;
  locations?: any[];
  awards?: string[];
  gallery?: string[];
  years_in_business?: number;
}

interface Service {
  id: string;
  name: string;
  category: string;
  price: number;
  duration: number;
}

function ProviderProfilePageContent({ params }: { params: Promise<{ id: string }> }) {
  const router = useRouter();
  const unwrappedParams = use(params);
  const [provider, setProvider] = useState<ProviderData | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');
  const [isFavorited, setIsFavorited] = useState(false);

  useEffect(() => {
    async function loadProvider() {
      try {
        const data = await apiClient.get(`/providers/${unwrappedParams.id}`);
        
        // Load scoped reviews for this provider
        const reviewsData = await apiClient.get(`/reviews?subject_type=provider&subject_id=${unwrappedParams.id}`);
        
        // Merge reviews into provider data
        setProvider({ ...data, reviews: reviewsData.data });
      } catch (err: any) {
        console.error('Failed to load provider:', err);
        setError(err.response?.data?.message || 'Failed to load provider');
      } finally {
        setIsLoading(false);
      }
    }

    loadProvider();
  }, [unwrappedParams.id]);

  const handleBook = () => {
    if (!provider) return;
    // If provider has locations, let user choose location in booking engine
    // Otherwise, use provider slug to find associated salon
    router.push(`/book?provider_id=${provider.id}`);
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

  if (error || !provider) {
    return (
      <div className="min-h-screen bg-background flex flex-col items-center justify-center text-text-primary">
        <h1 className="text-3xl font-bold mb-4">Provider Not Found</h1>
        <p className="text-text-secondary mb-4">{error || 'We couldn\'t find this provider.'}</p>
        <button
          onClick={() => router.push('/portal/discover')}
          className="px-6 py-2 text-white font-medium rounded-xl transition-colors"
          style={{
            background: `linear-gradient(to right, var(--brand-primary, #FFD700), var(--brand-secondary, #C9A227))`,
            borderRadius: 'var(--brand-border-radius, 16px)'
          }}
        >
          Back to Discover
        </button>
      </div>
    );
  }

  const getTypeLabel = (type: string) => {
    const labels: Record<string, string> = {
      salon: 'Salon',
      independent_specialist: 'Independent Specialist',
      spa: 'Spa',
      beauty_school: 'Beauty School',
      mobile_team: 'Mobile Team',
    };
    return labels[type] || type;
  };

  const badges = [];
  if (provider.years_in_business) {
    badges.push({ icon: Building2, label: 'Years', value: `${provider.years_in_business}+` });
  }
  if (provider.rating > 0) {
    badges.push({ icon: Star, label: 'Rating', value: `${provider.rating.toFixed(1)}` });
  }
  if (provider.locations && provider.locations.length > 0) {
    badges.push({ icon: MapPin, label: 'Locations', value: provider.locations.length });
  }

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
          title={provider.display_name}
          subtitle={getTypeLabel(provider.type)}
          description={provider.description}
          image={provider.logo || undefined}
          badges={badges}
          rating={provider.rating}
          reviewCount={provider.review_count}
          verified={true}
          primaryAction={{ label: 'Book Appointment', onClick: handleBook }}
          secondaryActions={[
            { label: 'View Locations', onClick: () => {} }
          ]}
          variant="provider"
        />

        {/* Share/Favorite */}
        <div className="flex justify-end mb-8">
          <ShareFavoriteActions
            isFavorited={isFavorited}
            onFavorite={handleFavorite}
            shareUrl={typeof window !== 'undefined' ? window.location.href : ''}
          />
        </div>

        {/* Story */}
        {provider.story && (
          <motion.section
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.1 }}
            className="mb-12"
          >
            <h3 className="text-xl font-semibold text-text-primary mb-4">Our Story</h3>
            <p className="text-text-secondary leading-relaxed">{provider.story}</p>
          </motion.section>
        )}

        {/* Mission */}
        {provider.mission && (
          <motion.section
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.2 }}
            className="mb-12"
          >
            <h3 className="text-xl font-semibold text-text-primary mb-4">Our Mission</h3>
            <p className="text-text-secondary leading-relaxed">{provider.mission}</p>
          </motion.section>
        )}

        {/* Locations */}
        {provider.locations && provider.locations.length > 0 && (
          <motion.section
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.3 }}
            className="mb-12"
          >
            <h3 className="text-xl font-semibold text-text-primary mb-4">Our Locations</h3>
            <div className="grid md:grid-cols-2 gap-4">
              {provider.locations.map((location: any) => (
                <LocationCard
                  key={location.id}
                  name={location.name}
                  address={location.address}
                  phone={location.phone}
                  rating={location.rating}
                  isOpen={location.is_open}
                  openingHours={location.opening_hours}
                  onBook={() => router.push(`/salons/${location.slug}/book`)}
                  onNavigate={() => window.open(location.maps_url, '_blank')}
                  variant="compact"
                />
              ))}
            </div>
          </motion.section>
        )}

        {/* Services */}
        {provider.services && provider.services.length > 0 && (
          <motion.section
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.4 }}
            className="mb-12"
          >
            <ServiceGrid
              services={provider.services.map(s => ({
                id: s.id,
                name: s.name,
                category: s.category,
                price: s.price,
                duration: s.duration
              }))}
              onServiceClick={(service) => router.push(`/services/${service.id}`)}
              columns={2}
            />
          </motion.section>
        )}

        {/* Specialists */}
        {provider.specialists && provider.specialists.length > 0 && (
          <motion.section
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.5 }}
            className="mb-12"
          >
            <SpecialistGrid
              specialists={provider.specialists.map(s => ({
                id: s.id,
                name: s.name,
                role: s.role,
                rating: s.rating,
                reviewCount: s.review_count,
                nextAvailable: s.next_available,
                specializations: s.specializations,
                salon: provider.display_name
              }))}
              onSpecialistClick={(specialist) => router.push(`/specialists/${specialist.id}`)}
              columns={4}
            />
          </motion.section>
        )}

        {/* Gallery */}
        {provider.gallery && provider.gallery.length > 0 && (
          <motion.section
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.6 }}
            className="mb-12"
          >
            <Gallery
              images={provider.gallery.map(url => ({ url }))}
              variant="provider"
            />
          </motion.section>
        )}

        {/* Reviews */}
        {provider.reviews && provider.reviews.length > 0 && (
          <motion.section
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.7 }}
            className="mb-12"
          >
            <Reviews
              reviews={provider.reviews.map(r => ({
                id: r.id,
                author: r.customer_name || 'Customer',
                rating: r.rating,
                date: r.date,
                text: r.comment || 'Great service!',
                helpful: r.helpful_count
              }))}
              averageRating={provider.rating}
              totalReviews={provider.review_count}
            />
          </motion.section>
        )}

        {/* Awards */}
        {provider.awards && provider.awards.length > 0 && (
          <motion.section
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.8 }}
            className="mb-12"
          >
            <h3 className="text-xl font-semibold text-text-primary mb-4">Awards & Recognition</h3>
            <div className="grid md:grid-cols-2 gap-4">
              {provider.awards.map((award, index) => (
                <div key={index} className="flex items-center gap-3 bg-surface border border-border-light rounded-xl p-4">
                  <Award className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
                  <span className="text-text-secondary">{award}</span>
                </div>
              ))}
            </div>
          </motion.section>
        )}

        {/* Contact */}
        <motion.section
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.9 }}
          className="mb-12"
        >
          <h3 className="text-xl font-semibold text-text-primary mb-4">Contact</h3>
          <div className="grid md:grid-cols-2 gap-4">
            {provider.phone && (
              <div className="flex items-center gap-3 bg-surface border border-border-light rounded-xl p-4">
                <Phone className="w-5 h-5 text-text-secondary" />
                <a href={`tel:${provider.phone}`} className="text-text-secondary hover:text-text-primary transition-colors">
                  {provider.phone}
                </a>
              </div>
            )}
            {provider.email && (
              <div className="flex items-center gap-3 bg-surface border border-border-light rounded-xl p-4">
                <Mail className="w-5 h-5 text-text-secondary" />
                <a href={`mailto:${provider.email}`} className="text-text-secondary hover:text-text-primary transition-colors">
                  {provider.email}
                </a>
              </div>
            )}
            {provider.website && (
              <div className="flex items-center gap-3 bg-surface border border-border-light rounded-xl p-4">
                <Globe className="w-5 h-5 text-text-secondary" />
                <a
                  href={provider.website}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="text-text-secondary hover:text-text-primary transition-colors"
                >
                  Visit Website
                </a>
              </div>
            )}
          </div>
        </motion.section>
      </div>
    </div>
  );
}

export default function ProviderProfilePage({ params }: { params: Promise<{ id: string }> }) {
  return <ProviderProfilePageContent params={params} />;
}
