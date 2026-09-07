'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { motion } from 'framer-motion';
import { MapPin, Phone, Mail, Clock, ArrowRight, Scissors, Star, User, Calendar, Award, Briefcase, Languages, Shield, Loader2, Info, CheckCircle, TrendingUp, Users } from 'lucide-react';
import { apiClient } from '@/lib/api-client';
import { portalApiClient } from '@/lib/portal-api-client';
import { HeroCard } from '@/components/destination/HeroCard';
import { Gallery } from '@/components/destination/Gallery';
import { Reviews } from '@/components/destination/Reviews';
import { AvailabilityPanel } from '@/components/destination/AvailabilityPanel';
import { BookCTA } from '@/components/destination/BookCTA';
import { ServiceGrid } from '@/components/destination/ServiceGrid';
import { LocationCard } from '@/components/destination/LocationCard';
import { SpecialistGrid } from '@/components/destination/SpecialistGrid';
import { ShareFavoriteActions } from '@/components/destination/ShareFavoriteActions';

interface SpecialistData {
  id: string;
  name: string;
  handle: string;
  role: string;
  specialties: string[] | null;
  languages: string[] | null;
  qualifications: string[] | null;
  portfolio: string[] | null;
  photo?: string | null;
  profile_image?: string | null;
  skills: string[] | null;
  years_experience?: number;
  certifications: string[] | null;
  rating?: number;
  review_count?: number;
  is_active: boolean;
  provider: any;
  services: any[];
  operating_hours: any[];
  reviews: any[];
  followers?: number;
  follower_count?: number;
  bio?: string | null;
  achievements?: string[];
  career_timeline?: Array<{ year: string; title: string; description: string }>;
  similar_specialists?: any[];
  next_available?: string;
}

function SpecialistProfilePageContent({ params }: { params: { handle: string } }) {
  const router = useRouter();
  const [specialist, setSpecialist] = useState<SpecialistData | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState('');
  const [isFavorited, setIsFavorited] = useState(false);
  const [isFollowing, setIsFollowing] = useState(false);

  useEffect(() => {
    async function loadSpecialist() {
      try {
        // Backend accepts idOrHandle — pass the handle directly
        const data = await apiClient.get(`/specialists/${params.handle}`);

        // Use resolved id from response for follow/favorite/reviews
        const resolvedId = data?.id ?? params.handle;
        const reviewsData = await apiClient.get(`/reviews?subject_type=specialist&subject_id=${resolvedId}`);

        let followStatus = { is_following: false, is_favorite: false };
        try {
          followStatus = await portalApiClient.get(`/specialists/${resolvedId}/follow-status`);
        } catch (err) {
          // Not authenticated — use defaults
        }

        setSpecialist({
          ...data,
          reviews: reviewsData?.reviews || [],
          is_following: followStatus.is_following,
          is_favorite: followStatus.is_favorite,
        });
        setIsFollowing(followStatus.is_following);
        setIsFavorited(followStatus.is_favorite);
      } catch (err: any) {
        setError(err?.response?.data?.message || 'Failed to load specialist');
      } finally {
        setIsLoading(false);
      }
    }

    loadSpecialist();
  }, [params.handle]);

  const handleBook = () => {
    if (!specialist) return;
    router.push(`/book?specialist_id=${specialist.id}`);
  };

  const handleFavorite = async () => {
    if (!specialist) return;
    try {
      if (isFavorited) {
        await portalApiClient.post(`/specialists/${specialist.id}/unfavorite`);
        setIsFavorited(false);
      } else {
        await portalApiClient.post(`/specialists/${specialist.id}/favorite`);
        setIsFavorited(true);
      }
    } catch (err) {
      console.error('Failed to update favorite status:', err);
    }
  };

  const handleFollow = async () => {
    if (!specialist) return;
    try {
      if (isFollowing) {
        await portalApiClient.post(`/specialists/${specialist.id}/unfollow`);
        setIsFollowing(false);
      } else {
        await portalApiClient.post(`/specialists/${specialist.id}/follow`);
        setIsFollowing(true);
      }
    } catch (err) {
      console.error('Failed to update follow status:', err);
    }
  };

  if (isLoading) {
    return (
      <div className="min-h-screen bg-background flex items-center justify-center">
        <Loader2 className="w-10 h-10 animate-spin" style={{ color: 'var(--brand-primary, #FFD700)' }} />
      </div>
    );
  }

  if (error || !specialist) {
    return (
      <div className="min-h-screen bg-background flex flex-col items-center justify-center text-text-primary">
        <h1 className="text-3xl font-bold mb-4">Specialist Not Found</h1>
        <p className="text-text-secondary mb-4">{error || "We couldn't find this specialist."}</p>
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

  const badges = [];
  if (specialist.years_experience) {
    badges.push({ icon: Briefcase, label: 'Experience', value: `${specialist.years_experience} years` });
  }
  if (specialist.rating && specialist.rating > 0) {
    badges.push({ icon: Star, label: 'Rating', value: `${specialist.rating.toFixed(1)}` });
  }
  if (specialist.follower_count) {
    badges.push({ icon: Users, label: 'Followers', value: specialist.follower_count });
  }

  return (
    <div className="min-h-screen bg-background pb-24">
      <div className="container mx-auto px-6 max-w-5xl py-8">
        <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="mb-6">
          <button
            onClick={() => router.back()}
            className="text-text-secondary hover:text-text-primary text-sm flex items-center gap-2 transition-colors"
          >
            <ArrowRight className="w-4 h-4 rotate-180" />
            Back
          </button>
        </motion.div>

        <HeroCard
          title={specialist.name}
          subtitle={specialist.role}
          description={specialist.bio || undefined}
          image={specialist.profile_image || specialist.photo || undefined}
          badges={badges}
          rating={specialist.rating}
          reviewCount={specialist.review_count}
          verified={specialist.is_active}
          primaryAction={{ label: 'Book Appointment', onClick: handleBook }}
          secondaryActions={[{ label: isFollowing ? 'Following' : 'Follow', onClick: handleFollow }]}
          variant="specialist"
        />

        <div className="flex justify-end mb-8">
          <ShareFavoriteActions
            isFavorited={isFavorited}
            onFavorite={handleFavorite}
            shareUrl={typeof window !== 'undefined' ? window.location.href : ''}
          />
        </div>

        {specialist.bio && (
          <motion.section initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.1 }} className="mb-12">
            <h3 className="text-xl font-semibold text-text-primary mb-4">About</h3>
            <p className="text-text-secondary leading-relaxed">{specialist.bio}</p>
          </motion.section>
        )}

        {(specialist.skills || specialist.specialties || specialist.certifications) && (
          <motion.section initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.2 }} className="mb-12">
            <h3 className="text-xl font-semibold text-text-primary mb-4">Expertise</h3>
            <div className="grid md:grid-cols-3 gap-4">
              {Array.isArray(specialist.skills) && specialist.skills.length > 0 && (
                <div className="bg-surface border border-border-light rounded-xl p-4">
                  <div className="flex items-center gap-2 mb-3">
                    <Scissors className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
                    <h4 className="font-semibold text-text-primary">Skills</h4>
                  </div>
                  <div className="flex flex-wrap gap-2">
                    {specialist.skills.map((skill, i) => (
                      <span key={i} className="px-3 py-1 rounded-full text-xs border" style={{ backgroundColor: 'var(--brand-primary, #FFD700)10', borderColor: 'var(--brand-primary, #FFD700)20', color: 'var(--brand-primary, #FFD700)' }}>{skill}</span>
                    ))}
                  </div>
                </div>
              )}
              {Array.isArray(specialist.specialties) && specialist.specialties.length > 0 && (
                <div className="bg-surface border border-border-light rounded-xl p-4">
                  <div className="flex items-center gap-2 mb-3">
                    <Award className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
                    <h4 className="font-semibold text-text-primary">Specialties</h4>
                  </div>
                  <div className="flex flex-wrap gap-2">
                    {specialist.specialties.map((specialty, i) => (
                      <span key={i} className="px-3 py-1 rounded-full text-xs border" style={{ backgroundColor: 'var(--brand-primary, #FFD700)10', borderColor: 'var(--brand-primary, #FFD700)20', color: 'var(--brand-primary, #FFD700)' }}>{specialty}</span>
                    ))}
                  </div>
                </div>
              )}
              {Array.isArray(specialist.certifications) && specialist.certifications.length > 0 && (
                <div className="bg-surface border border-border-light rounded-xl p-4">
                  <div className="flex items-center gap-2 mb-3">
                    <Shield className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
                    <h4 className="font-semibold text-text-primary">Certifications</h4>
                  </div>
                  <ul className="space-y-2">
                    {specialist.certifications.map((cert, i) => (
                      <li key={i} className="text-text-secondary text-sm flex items-start gap-2">
                        <CheckCircle className="w-4 h-4 text-text-muted mt-0.5 shrink-0" />{cert}
                      </li>
                    ))}
                  </ul>
                </div>
              )}
            </div>
            {Array.isArray(specialist.languages) && specialist.languages.length > 0 && (
              <div className="mt-4 bg-surface border border-border-light rounded-xl p-4">
                <div className="flex items-center gap-2 mb-3">
                  <Languages className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
                  <h4 className="font-semibold text-text-primary">Languages</h4>
                </div>
                <div className="flex flex-wrap gap-2">
                  {specialist.languages.map((language, i) => (
                    <span key={i} className="px-3 py-1 rounded-full text-xs bg-surface/50 border border-border-light text-text-secondary">{language}</span>
                  ))}
                </div>
              </div>
            )}
          </motion.section>
        )}

        {specialist.portfolio && specialist.portfolio.length > 0 && (
          <motion.section initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.3 }} className="mb-12">
            <Gallery images={specialist.portfolio.map(url => ({ url, type: 'after' }))} variant="specialist" />
          </motion.section>
        )}

        {specialist.services && specialist.services.length > 0 && (
          <motion.section initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.4 }} className="mb-12">
            <ServiceGrid
              services={specialist.services.map(s => ({ id: s.id, name: s.name, category: s.category, price: s.price_override || s.price, duration: s.duration }))}
              onServiceClick={(service) => router.push(`/services/${service.id}`)}
              columns={2}
            />
          </motion.section>
        )}

        {specialist.next_available && (
          <motion.section initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.5 }} className="mb-12">
            <AvailabilityPanel timeSlots={[{ time: specialist.next_available, available: true }]} showCalendar={false} />
          </motion.section>
        )}

        {specialist.provider && (
          <motion.section initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.6 }} className="mb-12">
            <h3 className="text-xl font-semibold text-text-primary mb-4">Current Workplace</h3>
            <LocationCard
              name={specialist.provider.display_name}
              address={specialist.provider.address}
              phone={specialist.provider.phone}
              rating={specialist.provider.rating}
              isOpen={specialist.provider.is_open}
              openingHours={specialist.provider.opening_hours}
              onBook={handleBook}
              onNavigate={() => window.open(specialist.provider.maps_url, '_blank')}
              variant="full"
            />
          </motion.section>
        )}

        {specialist.career_timeline && specialist.career_timeline.length > 0 && (
          <motion.section initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.7 }} className="mb-12">
            <h3 className="text-xl font-semibold text-text-primary mb-4">Career Journey</h3>
            <div className="relative">
              <div className="absolute left-4 top-0 bottom-0 w-0.5 bg-border-light" />
              <div className="space-y-6">
                {specialist.career_timeline.map((milestone, index) => (
                  <div key={index} className="relative pl-10">
                    <div className="absolute left-2 top-2 w-5 h-5 rounded-full border-2 bg-background" style={{ borderColor: 'var(--brand-primary, #FFD700)' }} />
                    <div className="bg-surface border border-border-light rounded-xl p-4">
                      <p className="font-semibold text-text-primary">{milestone.year}</p>
                      <p className="text-text-primary font-medium">{milestone.title}</p>
                      <p className="text-text-secondary text-sm mt-1">{milestone.description}</p>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </motion.section>
        )}

        {specialist.achievements && specialist.achievements.length > 0 && (
          <motion.section initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.8 }} className="mb-12">
            <h3 className="text-xl font-semibold text-text-primary mb-4">Achievements</h3>
            <div className="grid md:grid-cols-2 gap-4">
              {specialist.achievements.map((achievement, index) => (
                <div key={index} className="flex items-center gap-3 bg-surface border border-border-light rounded-xl p-4">
                  <Award className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
                  <span className="text-text-secondary">{achievement}</span>
                </div>
              ))}
            </div>
          </motion.section>
        )}

        {specialist.reviews && specialist.reviews.length > 0 && (
          <motion.section initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.9 }} className="mb-12">
            <Reviews
              reviews={specialist.reviews.map(r => ({ id: r.id, author: r.customer_name || 'Customer', rating: r.rating, date: r.date, text: r.comment || 'Great service!', helpful: r.helpful_count }))}
              averageRating={specialist.rating}
              totalReviews={specialist.review_count}
            />
          </motion.section>
        )}

        {specialist.similar_specialists && specialist.similar_specialists.length > 0 && (
          <motion.section initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 1.0 }} className="mb-12">
            <SpecialistGrid
              specialists={specialist.similar_specialists
                .filter(s => s.id !== specialist.id)
                .map(s => ({ id: s.id, name: s.name, role: s.role, rating: s.rating, reviewCount: s.review_count, nextAvailable: s.next_available, specializations: s.specializations, salon: s.salon?.name, handle: s.handle }))}
              onSpecialistClick={(s) => router.push(`/specialists/${s.handle ?? s.id}`)}
              columns={3}
            />
          </motion.section>
        )}
      </div>

      <BookCTA specialistName={specialist.name} onBook={handleBook} variant="floating" />
    </div>
  );
}

export default function SpecialistProfilePage({ params }: { params: { handle: string } }) {
  return <SpecialistProfilePageContent params={params} />;
}
