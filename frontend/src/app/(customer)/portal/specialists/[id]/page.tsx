'use client';

import { motion } from 'framer-motion';
import { Star, Calendar, Clock, Scissors, Award, MapPin, Phone, Mail, ChevronLeft, MessageCircle } from 'lucide-react';
import { usePortalAuth } from '@/contexts/PortalAuthContext';
import { usePortalBrand } from '@/contexts/PortalBrandContext';
import { portalApiClient } from '@/lib/portal-api-client';
import { useQuery } from '@tanstack/react-query';
import Link from 'next/link';
import { useState } from 'react';
import { useRouter } from 'next/navigation';

export default function SpecialistProfilePage({ params }: { params: { id: string } }) {
  const { customer, salon } = usePortalAuth();
  const { brand } = usePortalBrand();
  const router = useRouter();
  const [selectedTab, setSelectedTab] = useState<'about' | 'reviews'>('about');

  const { data: specialist, isLoading } = useQuery({
    queryKey: ['specialist-profile', params.id],
    queryFn: () => portalApiClient.get(`/portal/specialists/${params.id}`),
    enabled: !!customer,
  });

  const { data: reviewsData } = useQuery({
    queryKey: ['specialist-reviews', params.id],
    queryFn: () => portalApiClient.get(`/portal/specialists/${params.id}/reviews`),
    enabled: !!customer && !!specialist,
  });

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-background">
        <div className="text-text-primary">Loading...</div>
      </div>
    );
  }

  if (!specialist) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-background">
        <div className="text-text-secondary">Specialist not found</div>
      </div>
    );
  }

  return (
    <div className="max-w-4xl mx-auto space-y-6">
      {/* Header */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        className="flex items-center gap-4"
      >
        <Link
          href="/portal/discover"
          className="p-2 rounded-full bg-surface border border-border-light hover:border-gold/30 transition-colors"
        >
          <ChevronLeft className="w-5 h-5 text-text-primary" />
        </Link>
        <div>
          <h1 className="text-3xl font-bold text-text-primary">Specialist Profile</h1>
          <p className="text-text-secondary">Meet your professional</p>
        </div>
      </motion.div>

      {/* Profile Header */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.1 }}
        className="bg-surface border border-border-light rounded-2xl p-6"
        style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
      >
        <div className="flex items-start gap-6">
          <div className="w-24 h-24 rounded-full flex items-center justify-center text-white text-3xl font-bold flex-shrink-0"
            style={{
              background: 'linear-gradient(to bottom right, var(--brand-primary, #FFD700), var(--brand-secondary, #C9A227))',
              borderRadius: 'var(--brand-border-radius, 16px)'
            }}
          >
            {specialist.name?.charAt(0)}
          </div>
          <div className="flex-1">
            <h2 className="text-2xl font-bold text-text-primary mb-2">{specialist.name}</h2>
            <div className="flex items-center gap-4 mb-3">
              <div className="flex items-center gap-1">
                <Star className="w-4 h-4" style={{ color: 'var(--brand-primary, #FFD700)' }} />
                <span className="font-semibold text-text-primary">{specialist.rating || '0.0'}</span>
                <span className="text-text-secondary">({specialist.review_count || 0} reviews)</span>
              </div>
            </div>
            {specialist.bio && (
              <p className="text-text-secondary mb-4">{specialist.bio}</p>
            )}
            <Link
              href="/portal/bookings/new"
              className="inline-flex items-center gap-2 px-6 py-3 text-white font-medium rounded-full transition-all"
              style={{
                backgroundColor: 'var(--brand-primary, #FFD700)',
                borderRadius: 'var(--brand-border-radius, 16px)'
              }}
            >
              Book with {specialist.name?.split(' ')[0]}
              <Calendar className="w-4 h-4" />
            </Link>
          </div>
        </div>
      </motion.div>

      {/* Tabs */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.2 }}
        className="flex gap-4 border-b border-border-light"
      >
        <button
          onClick={() => setSelectedTab('about')}
          className={`px-4 py-2 text-sm font-medium transition-colors ${
            selectedTab === 'about'
              ? 'text-text-primary border-b-2'
              : 'text-text-secondary hover:text-text-primary'
          }`}
          style={{
            borderColor: selectedTab === 'about' ? 'var(--brand-primary, #FFD700)' : 'transparent'
          }}
        >
          About
        </button>
        <button
          onClick={() => setSelectedTab('reviews')}
          className={`px-4 py-2 text-sm font-medium transition-colors ${
            selectedTab === 'reviews'
              ? 'text-text-primary border-b-2'
              : 'text-text-secondary hover:text-text-primary'
          }`}
          style={{
            borderColor: selectedTab === 'reviews' ? 'var(--brand-primary, #FFD700)' : 'transparent'
          }}
        >
          Reviews ({specialist.review_count || 0})
        </button>
      </motion.div>

      {/* Tab Content */}
      {selectedTab === 'about' && (
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.3 }}
          className="space-y-4"
        >
          {/* Specialties */}
          {specialist.specialties && specialist.specialties.length > 0 && (
            <div className="bg-surface border border-border-light rounded-2xl p-6"
              style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
            >
              <h3 className="text-lg font-semibold text-text-primary mb-4 flex items-center gap-2">
                <Scissors className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
                Specialties
              </h3>
              <div className="flex flex-wrap gap-2">
                {specialist.specialties.map((specialty: string, index: number) => (
                  <span
                    key={index}
                    className="px-3 py-2 rounded-lg bg-surface border border-border-light text-sm text-text-secondary"
                    style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
                  >
                    {specialty}
                  </span>
                ))}
              </div>
            </div>
          )}

          {/* Languages */}
          {specialist.languages && specialist.languages.length > 0 && (
            <div className="bg-surface border border-border-light rounded-2xl p-6"
              style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
            >
              <h3 className="text-lg font-semibold text-text-primary mb-4 flex items-center gap-2">
                <MessageCircle className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
                Languages
              </h3>
              <div className="flex flex-wrap gap-2">
                {specialist.languages.map((language: string, index: number) => (
                  <span
                    key={index}
                    className="px-3 py-2 rounded-lg bg-surface border border-border-light text-sm text-text-secondary"
                    style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
                  >
                    {language}
                  </span>
                ))}
              </div>
            </div>
          )}

          {/* Qualifications */}
          {specialist.qualifications && specialist.qualifications.length > 0 && (
            <div className="bg-surface border border-border-light rounded-2xl p-6"
              style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
            >
              <h3 className="text-lg font-semibold text-text-primary mb-4 flex items-center gap-2">
                <Award className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
                Qualifications
              </h3>
              <ul className="space-y-2">
                {specialist.qualifications.map((qualification: string, index: number) => (
                  <li key={index} className="flex items-start gap-2 text-text-secondary">
                    <div className="w-1.5 h-1.5 rounded-full mt-2 flex-shrink-0"
                      style={{ backgroundColor: 'var(--brand-primary, #FFD700)' }}
                    />
                    {qualification}
                  </li>
                ))}
              </ul>
            </div>
          )}

          {/* Portfolio */}
          {specialist.portfolio && specialist.portfolio.length > 0 && (
            <div className="bg-surface border border-border-light rounded-2xl p-6"
              style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
            >
              <h3 className="text-lg font-semibold text-text-primary mb-4">Portfolio</h3>
              <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
                {specialist.portfolio.map((image: string, index: number) => (
                  <div
                    key={index}
                    className="aspect-square rounded-xl bg-surface border border-border-light overflow-hidden"
                    style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
                  >
                    <img
                      src={image}
                      alt={`Portfolio ${index + 1}`}
                      className="w-full h-full object-cover"
                    />
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Availability */}
          {specialist.availability && Object.keys(specialist.availability).length > 0 && (
            <div className="bg-surface border border-border-light rounded-2xl p-6"
              style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
            >
              <h3 className="text-lg font-semibold text-text-primary mb-4 flex items-center gap-2">
                <Clock className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
                Availability
              </h3>
              <div className="space-y-2">
                {Object.entries(specialist.availability).map(([day, times]: [string, any]) => (
                  <div key={day} className="flex items-center justify-between py-2 border-b border-border-light last:border-0">
                    <span className="text-text-primary capitalize">{day}</span>
                    <span className="text-text-secondary text-sm">
                      {times?.start && times?.end ? `${times.start} - ${times.end}` : 'Unavailable'}
                    </span>
                  </div>
                ))}
              </div>
            </div>
          )}
        </motion.div>
      )}

      {selectedTab === 'reviews' && (
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.3 }}
          className="space-y-4"
        >
          {reviewsData?.reviews?.data && reviewsData.reviews.data.length > 0 ? (
            reviewsData.reviews.data.map((review: any) => (
              <div
                key={review.id}
                className="bg-surface border border-border-light rounded-2xl p-6"
                style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
              >
                <div className="flex items-start justify-between mb-4">
                  <div className="flex items-center gap-3">
                    <div className="w-10 h-10 rounded-full flex items-center justify-center text-white font-semibold"
                      style={{
                        background: 'linear-gradient(to bottom right, var(--brand-primary, #FFD700), var(--brand-secondary, #C9A227))',
                        borderRadius: 'var(--brand-border-radius, 16px)'
                      }}
                    >
                      {review.customer?.name?.charAt(0)}
                    </div>
                    <div>
                      <p className="font-semibold text-text-primary">{review.customer?.name}</p>
                      <p className="text-sm text-text-secondary">
                        {new Date(review.created_at).toLocaleDateString()}
                      </p>
                    </div>
                  </div>
                  <div className="flex items-center gap-1">
                    {[...Array(5)].map((_, i) => (
                      <Star
                        key={i}
                        className={`w-4 h-4 ${i < review.rating ? 'text-yellow-500' : 'text-gray-300'}`}
                        fill={i < review.rating ? 'currentColor' : 'none'}
                      />
                    ))}
                  </div>
                </div>
                {review.comment && (
                  <p className="text-text-secondary">{review.comment}</p>
                )}
              </div>
            ))
          ) : (
            <div className="bg-surface border border-border-light rounded-2xl p-12 text-center"
              style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
            >
              <Star className="w-16 h-16 text-text-secondary mx-auto mb-4" />
              <h3 className="text-xl font-semibold text-text-primary mb-2">No reviews yet</h3>
              <p className="text-text-secondary">Be the first to leave a review!</p>
            </div>
          )}
        </motion.div>
      )}
    </div>
  );
}
