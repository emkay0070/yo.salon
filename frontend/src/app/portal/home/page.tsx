'use client';

import { motion } from 'framer-motion';
import { Calendar, Sparkles, Wallet, User, Scissors, Clock, ChevronRight, Repeat, Gift, TrendingUp, Award, ArrowRight } from 'lucide-react';
import { usePortalAuth } from '@/contexts/PortalAuthContext';
import { usePortalBrand } from '@/contexts/PortalBrandContext';
import { portalApiClient } from '@/lib/portal-api-client';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { FeatureGuard } from '@/components/ui/FeatureGuard';
import { useRouter } from 'next/navigation';
import { QuickActionSkeleton, StatCardSkeleton, ServiceCardSkeleton, CardSkeleton } from '@/components/ui/LoadingSkeleton';

function formatDate(dateStr: string): string {
  if (!dateStr) return '';
  const date = new Date(dateStr);
  return date.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
}

function formatTime(timeStr: string): string {
  if (!timeStr) return '';
  const time = new Date(timeStr);
  return time.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
}

export default function PortalHomePage() {
  const { customer, salon, isLoading: authLoading } = usePortalAuth();
  const { brand } = usePortalBrand();
  const queryClient = useQueryClient();
  const router = useRouter();

  const { data: homeData, isLoading: homeLoading, error, refetch } = useQuery({
    queryKey: ['portal-home'],
    queryFn: () => portalApiClient.get('/portal/home'),
    enabled: !!customer,
    retry: 1,
  });

  const cancelMutation = useMutation({
    mutationFn: (bookingId: string) => portalApiClient.patch(`/portal/bookings/${bookingId}/cancel`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['portal-home'] });
    },
  });

  if (authLoading || homeLoading) {
    return (
      <div className="space-y-8">
        {/* Greeting Skeleton */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.5 }}
          className="space-y-2"
        >
          <div className="h-10 w-64 bg-gray-200 rounded animate-pulse" />
          <div className="h-5 w-48 bg-gray-200 rounded animate-pulse" />
        </motion.div>

        {/* Quick Actions Skeleton */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.5, delay: 0.1 }}
          className="grid grid-cols-2 sm:grid-cols-4 gap-4"
        >
          <QuickActionSkeleton />
          <QuickActionSkeleton />
          <QuickActionSkeleton />
          <QuickActionSkeleton />
        </motion.div>

        {/* Next Appointment Skeleton */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.5, delay: 0.2 }}
        >
          <CardSkeleton />
        </motion.div>

        {/* Stats Cards Skeleton */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.5, delay: 0.4 }}
          className="grid grid-cols-2 sm:grid-cols-3 gap-4"
        >
          <StatCardSkeleton />
          <StatCardSkeleton />
          <StatCardSkeleton />
        </motion.div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-background">
        <div className="text-center p-8">
          <div className="text-red-500 mb-4">
            <svg className="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
          </div>
          <h2 className="text-xl font-semibold text-text-primary mb-2">Unable to load home data</h2>
          <p className="text-text-secondary mb-6">Something went wrong. Please try again.</p>
          <button
            onClick={() => refetch()}
            className="px-6 py-2 text-white rounded-full font-medium transition-colors"
            style={{ 
              backgroundColor: 'var(--brand-primary, #FFD700)',
              borderRadius: 'var(--brand-border-radius, 16px)'
            }}
            onMouseEnter={(e) => {
              e.currentTarget.style.opacity = '0.9';
            }}
            onMouseLeave={(e) => {
              e.currentTarget.style.opacity = '1';
            }}
          >
            Retry
          </button>
        </div>
      </div>
    );
  }

  const getGreeting = () => {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good morning';
    if (hour < 18) return 'Good afternoon';
    return 'Good evening';
  };

  const upcomingBooking = homeData?.next_appointment;
  const walletSummary = homeData?.wallet_summary;
  const loyaltySummary = homeData?.loyalty_summary;
  const recommendedServices = homeData?.recommended_services || [];
  const favoriteStylist = homeData?.favorite_stylist;
  const recentVisits = homeData?.recent_visits || [];
  const lastBooking = homeData?.last_booking;
  const offers = homeData?.offers || [];

  return (
    <div className="space-y-8">
        {/* Greeting Section */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.5 }}
        >
          <h1 className="text-3xl sm:text-4xl font-bold text-text-primary mb-2">
            {getGreeting()}, {customer?.name || 'there'}
          </h1>
          <p className="text-text-secondary">
            {new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' })}
          </p>
        </motion.div>

        {/* Quick Actions */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.5, delay: 0.1 }}
          className="grid grid-cols-2 sm:grid-cols-4 gap-4"
        >
          <QuickActionCard
            icon={Calendar}
            label="Book Now"
            href="/portal/bookings/new"
            color="from-gold to-dark-gold"
          />
          <QuickActionCard
            icon={Sparkles}
            label="Discover"
            href="/portal/discover"
            color="from-purple-500 to-purple-600"
          />
          <QuickActionCard
            icon={Wallet}
            label="Wallet"
            href="/portal/wallet"
            color="from-emerald-500 to-emerald-600"
          />
          <QuickActionCard
            icon={User}
            label="Profile"
            href="/portal/profile"
            color="from-blue-500 to-blue-600"
          />
        </motion.div>

        {/* Today's Appointment Card */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.5, delay: 0.2 }}
          className="bg-surface border border-border-light p-6"
          style={{
            borderRadius: 'var(--brand-border-radius, 16px)',
            boxShadow: 'var(--brand-shadow-md, 0 4px 12px rgba(0,0,0,0.08))'
          }}
        >
          <div className="flex items-center gap-3 mb-4">
            <div
              className="w-10 h-10 rounded-xl flex items-center justify-center"
              style={{
                backgroundColor: 'var(--brand-primary, #FFD700)20',
                borderRadius: 'var(--brand-border-radius, 16px)'
              }}
            >
              <Clock className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
            </div>
            <h2 className="text-lg font-semibold text-text-primary">Today's Appointment</h2>
          </div>
          {upcomingBooking && upcomingBooking.date === new Date().toISOString().split('T')[0] ? (
            <div className="space-y-4">
              <div className="flex items-center justify-between p-4 bg-surface border border-border-light rounded-xl">
                <div>
                  <p className="font-semibold text-text-primary">{upcomingBooking.service?.name}</p>
                  <p className="text-sm text-text-secondary">{upcomingBooking.staff?.name}</p>
                </div>
                <div className="text-right">
                  <p className="font-semibold text-text-primary">{formatTime(upcomingBooking.time)}</p>
                  <p className="text-sm text-text-secondary">Today</p>
                </div>
              </div>
              <div className="flex gap-2">
                <button
                  onClick={() => router.push(`/portal/bookings/${upcomingBooking.id}/check-in`)}
                  className="flex-1 px-4 py-2 text-sm font-medium transition-colors rounded-lg"
                  style={{
                    backgroundColor: 'var(--brand-primary, #FFD700)',
                    borderRadius: 'var(--brand-border-radius, 16px)'
                  }}
                  onMouseEnter={(e) => {
                    e.currentTarget.style.opacity = '0.9';
                  }}
                  onMouseLeave={(e) => {
                    e.currentTarget.style.opacity = '1';
                  }}
                >
                  Check In
                </button>
                <button
                  onClick={() => router.push(`/portal/bookings/new?reschedule=${upcomingBooking.id}`)}
                  className="flex-1 px-4 py-2 text-sm font-medium transition-colors rounded-lg"
                  style={{
                    backgroundColor: 'var(--brand-primary, #FFD700)20',
                    color: 'var(--brand-primary, #FFD700)',
                    borderRadius: 'var(--brand-border-radius, 16px)'
                  }}
                  onMouseEnter={(e) => {
                    e.currentTarget.style.backgroundColor = 'var(--brand-primary, #FFD700)30';
                  }}
                  onMouseLeave={(e) => {
                    e.currentTarget.style.backgroundColor = 'var(--brand-primary, #FFD700)20';
                  }}
                >
                  Reschedule
                </button>
              </div>
            </div>
          ) : upcomingBooking ? (
            <div className="space-y-4">
              <p className="text-text-secondary text-sm">Your next appointment is:</p>
              <div className="flex items-center justify-between p-4 bg-surface border border-border-light rounded-xl">
                <div>
                  <p className="font-semibold text-text-primary">{upcomingBooking.service?.name}</p>
                  <p className="text-sm text-text-secondary">{upcomingBooking.staff?.name}</p>
                </div>
                <div className="text-right">
                  <p className="font-semibold text-text-primary">{formatDate(upcomingBooking.date)}</p>
                  <p className="text-sm text-text-secondary">{formatTime(upcomingBooking.time)}</p>
                </div>
              </div>
              <div className="flex gap-2">
                <button
                  onClick={() => router.push(`/portal/bookings/new?reschedule=${upcomingBooking.id}`)}
                  className="flex-1 px-4 py-2 text-sm font-medium transition-colors rounded-lg"
                  style={{
                    backgroundColor: 'var(--brand-primary, #FFD700)20',
                    color: 'var(--brand-primary, #FFD700)',
                    borderRadius: 'var(--brand-border-radius, 16px)'
                  }}
                  onMouseEnter={(e) => {
                    e.currentTarget.style.backgroundColor = 'var(--brand-primary, #FFD700)30';
                  }}
                  onMouseLeave={(e) => {
                    e.currentTarget.style.backgroundColor = 'var(--brand-primary, #FFD700)20';
                  }}
                >
                  Reschedule
                </button>
                <button
                  onClick={() => router.push('/portal/bookings/new')}
                  className="flex-1 px-4 py-2 text-sm font-medium transition-colors rounded-lg"
                  style={{
                    backgroundColor: 'var(--brand-primary, #FFD700)',
                    borderRadius: 'var(--brand-border-radius, 16px)'
                  }}
                  onMouseEnter={(e) => {
                    e.currentTarget.style.opacity = '0.9';
                  }}
                  onMouseLeave={(e) => {
                    e.currentTarget.style.opacity = '1';
                  }}
                >
                  Book Today
                </button>
              </div>
            </div>
          ) : (
            <div className="text-center py-8">
              <p className="text-text-secondary mb-2">No appointments today</p>
              <p className="text-text-secondary text-sm mb-4">Book something for today?</p>
              <button
                onClick={() => router.push('/portal/bookings/new')}
                className="px-6 py-2 text-white text-sm font-medium transition-colors rounded-full"
                style={{
                  backgroundColor: 'var(--brand-primary, #FFD700)',
                  borderRadius: 'var(--brand-border-radius, 16px)'
                }}
                onMouseEnter={(e) => {
                  e.currentTarget.style.opacity = '0.9';
                }}
                onMouseLeave={(e) => {
                  e.currentTarget.style.opacity = '1';
                }}
              >
                Book Now
              </button>
            </div>
          )}
        </motion.div>

        {/* Continue Last Service */}
        <FeatureGuard feature="rebook" fallback={null}>
          {lastBooking && (
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.5, delay: 0.25 }}
              className="p-6"
              style={{
                background: `linear-gradient(to right, var(--brand-primary, #FFD700)20, var(--brand-secondary, #C9A227)20)`,
                border: `1px solid var(--brand-primary, #FFD700)30`,
                borderRadius: 'var(--brand-border-radius, 16px)',
                boxShadow: 'var(--brand-shadow-md, 0 4px 12px rgba(0,0,0,0.08))'
              }}
            >
              <div className="flex items-center gap-3 mb-4">
                <div
                  className="w-10 h-10 rounded-xl flex items-center justify-center"
                  style={{
                    backgroundColor: 'var(--brand-primary, #FFD700)20',
                    borderRadius: 'var(--brand-border-radius, 16px)'
                  }}
                >
                  <Repeat className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
                </div>
                <div>
                  <h2 className="text-lg font-semibold text-text-primary">Continue where you left off</h2>
                  <p className="text-sm text-text-secondary">{lastBooking.provider?.display_name}</p>
                </div>
              </div>
              <div className="flex items-center justify-between">
                <div>
                  <p className="font-semibold text-text-primary">{lastBooking.service?.name}</p>
                  <p className="text-sm text-text-secondary">
                    {lastBooking.staff?.name} • UGX {lastBooking.service?.price?.toLocaleString('en-UG', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}
                  </p>
                </div>
                <button
                  onClick={() => router.push(`/portal/bookings/new?service=${lastBooking.service?.id}&staff=${lastBooking.staff?.id}`)}
                  className="px-4 py-2 text-white text-sm font-medium transition-colors rounded-lg flex items-center gap-2"
                  style={{
                    backgroundColor: 'var(--brand-primary, #FFD700)',
                    borderRadius: 'var(--brand-border-radius, 16px)'
                  }}
                  onMouseEnter={(e) => {
                    e.currentTarget.style.opacity = '0.9';
                  }}
                  onMouseLeave={(e) => {
                    e.currentTarget.style.opacity = '1';
                  }}
                >
                  Book Again <ArrowRight className="w-4 h-4" />
                </button>
              </div>
            </motion.div>
          )}
        </FeatureGuard>

        {/* Active Offers */}
        <FeatureGuard feature="offers" fallback={null}>
          {offers && offers.length > 0 && (
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.5, delay: 0.3 }}
              className="bg-surface border border-border-light p-6"
              style={{ 
                borderRadius: 'var(--brand-border-radius, 16px)',
                boxShadow: 'var(--brand-shadow-md, 0 4px 12px rgba(0,0,0,0.08))'
              }}
            >
              <div className="flex items-center gap-3 mb-4">
                <div className="w-10 h-10 rounded-xl bg-red-500/10 flex items-center justify-center">
                  <Gift className="w-5 h-5 text-red-500" />
                </div>
                <h2 className="text-lg font-semibold text-text-primary">Active Offers</h2>
              </div>
              <div className="flex gap-4 overflow-x-auto pb-2">
                {offers.slice(0, 3).map((offer: any) => (
                  <div 
                    key={offer.id} 
                    className="flex-shrink-0 w-64 border rounded-xl p-4"
                    style={{ 
                      background: 'linear-gradient(to bottom right, rgba(239, 68, 68, 0.1), rgba(249, 115, 22, 0.1))',
                      borderColor: 'rgba(239, 68, 68, 0.2)',
                      borderRadius: 'var(--brand-border-radius, 16px)'
                    }}
                  >
                    <p className="font-semibold text-text-primary mb-1">{offer.title}</p>
                    <p className="text-sm text-text-secondary mb-2">{offer.description}</p>
                    <p className="text-xs text-red-500 font-medium">
                      {offer.discount_type === 'percentage' ? `${offer.discount_value}% OFF` : `$${offer.discount_value} OFF`}
                    </p>
                  </div>
                ))}
              </div>
            </motion.div>
          )}
        </FeatureGuard>

        {/* Recommended for You */}
        {recommendedServices.length > 0 && (
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.5, delay: 0.35 }}
            className="bg-surface border border-border-light p-6"
            style={{
              borderRadius: 'var(--brand-border-radius, 16px)',
              boxShadow: 'var(--brand-shadow-md, 0 4px 12px rgba(0,0,0,0.08))'
            }}
          >
            <div className="flex items-center justify-between mb-4">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-xl bg-purple-500/10 flex items-center justify-center">
                  <Sparkles className="w-5 h-5 text-purple-500" />
                </div>
                <div>
                  <h2 className="text-lg font-semibold text-text-primary">Because you liked {lastBooking?.service?.name}</h2>
                  <p className="text-xs text-text-secondary">Recommended for you</p>
                </div>
              </div>
              <a
                href="/portal/discover"
                className="text-sm hover:underline flex items-center gap-1"
                style={{ color: 'var(--brand-primary, #FFD700)' }}
              >
                See all <ChevronRight className="w-4 h-4" />
              </a>
            </div>
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
              {recommendedServices.slice(0, 4).map((service: any) => (
                <QuickServiceCard key={service.id} service={service} />
              ))}
            </div>
          </motion.div>
        )}

        {/* Loyalty Progress */}
        <FeatureGuard feature="loyalty" fallback={null}>
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.5, delay: 0.4 }}
            className="bg-surface border border-border-light p-6"
            style={{ 
              borderRadius: 'var(--brand-border-radius, 16px)',
              boxShadow: 'var(--brand-shadow-md, 0 4px 12px rgba(0,0,0,0.08))'
            }}
          >
            <div className="flex items-center gap-3 mb-4">
              <div className="w-10 h-10 rounded-xl bg-purple-500/10 flex items-center justify-center">
                <Award className="w-5 h-5 text-purple-500" />
              </div>
              <h2 className="text-lg font-semibold text-text-primary">Loyalty Progress</h2>
            </div>
            <div className="space-y-3">
              <div className="flex items-center justify-between">
                <span className="text-sm font-medium text-text-primary capitalize">
                  {loyaltySummary?.tier || 'Bronze'} Member
                </span>
                <span className="text-sm text-text-secondary">
                  {loyaltySummary?.points || 0} points
                </span>
              </div>
              <div className="w-full bg-gray-200 rounded-full h-2">
                <div
                  className="h-2 rounded-full transition-all"
                  style={{ 
                    width: `${loyaltySummary?.tier_progress || 0}%`,
                    background: `linear-gradient(to right, var(--brand-primary, #FFD700), var(--brand-secondary, #C9A227))`
                  }}
                />
              </div>
              {loyaltySummary?.points_to_next && loyaltySummary.points_to_next > 0 && (
                <p className="text-xs text-text-secondary">
                  {loyaltySummary.points_to_next} points to {loyaltySummary.next_tier || 'next tier'}
                </p>
              )}
            </div>
          </motion.div>
        </FeatureGuard>

        {/* Recent Visits */}
        <FeatureGuard feature="loyalty" fallback={null}>
          {recentVisits && recentVisits.length > 0 && (
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.5, delay: 0.45 }}
              className="bg-surface border border-border-light p-6"
              style={{ 
                borderRadius: 'var(--brand-border-radius, 16px)',
                boxShadow: 'var(--brand-shadow-md, 0 4px 12px rgba(0,0,0,0.08))'
              }}
            >
              <div className="flex items-center gap-3 mb-4">
                <div className="w-10 h-10 rounded-xl bg-blue-500/10 flex items-center justify-center">
                  <TrendingUp className="w-5 h-5 text-blue-500" />
                </div>
                <h2 className="text-lg font-semibold text-text-primary">Recent Visits</h2>
              </div>
              <div className="space-y-3">
                {recentVisits.slice(0, 3).map((visit: any) => (
                  <div
                    key={visit.id}
                    className="flex items-center justify-between p-3 bg-surface border border-border-light cursor-pointer transition-all"
                    style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
                    onClick={() => router.push(`/portal/bookings/new?service=${visit.service?.id}&staff=${visit.staff?.id}`)}
                    onMouseEnter={(e) => {
                      e.currentTarget.style.borderColor = 'var(--brand-primary, #FFD700)';
                    }}
                    onMouseLeave={(e) => {
                      e.currentTarget.style.borderColor = '';
                    }}
                  >
                    <div>
                      <p className="font-medium text-text-primary text-sm">{visit.service?.name}</p>
                      <p className="text-xs text-text-secondary">{visit.staff?.name}</p>
                    </div>
                    <div className="text-right">
                      <p className="text-sm text-text-primary">{formatDate(visit.date)}</p>
                      <p className="text-xs text-text-secondary">{formatTime(visit.time)}</p>
                    </div>
                  </div>
                ))}
              </div>
            </motion.div>
          )}
        </FeatureGuard>

        {/* My Stylist */}
        <FeatureGuard feature="my_stylist" fallback={null}>
          {favoriteStylist && (
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.5, delay: 0.5 }}
              className="p-6"
              style={{ 
                background: `linear-gradient(to right, var(--brand-primary, #FFD700)10, var(--brand-secondary, #C9A227)10)`,
                border: `1px solid var(--brand-primary, #FFD700)20`,
                borderRadius: 'var(--brand-border-radius, 16px)',
                boxShadow: 'var(--brand-shadow-md, 0 4px 12px rgba(0,0,0,0.08))'
              }}
            >
              <div className="flex items-center gap-3 mb-4">
                <div 
                  className="w-10 h-10 rounded-xl flex items-center justify-center"
                  style={{ 
                    backgroundColor: 'var(--brand-primary, #FFD700)20',
                    borderRadius: 'var(--brand-border-radius, 16px)'
                  }}
                >
                  <User className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
                </div>
                <h2 className="text-lg font-semibold text-text-primary">Your Stylist</h2>
              </div>
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <div 
                    className="w-12 h-12 rounded-full flex items-center justify-center text-white font-semibold"
                    style={{ 
                      background: `linear-gradient(to bottom right, var(--brand-primary, #FFD700), var(--brand-secondary, #C9A227))`,
                      borderRadius: 'var(--brand-border-radius, 16px)'
                    }}
                  >
                    {favoriteStylist.name?.charAt(0)}
                  </div>
                  <div>
                    <p className="font-semibold text-text-primary">{favoriteStylist.name}</p>
                    <p className="text-sm text-text-secondary">Preferred Stylist</p>
                  </div>
                </div>
                <button 
                  onClick={() => router.push(`/portal/bookings/new?staff=${favoriteStylist.id}`)}
                  className="px-4 py-2 text-white text-sm font-medium transition-colors rounded-lg"
                  style={{ 
                    backgroundColor: 'var(--brand-primary, #FFD700)',
                    borderRadius: 'var(--brand-border-radius, 16px)'
                  }}
                  onMouseEnter={(e) => {
                    e.currentTarget.style.opacity = '0.9';
                  }}
                  onMouseLeave={(e) => {
                    e.currentTarget.style.opacity = '1';
                  }}
                >
                  Book with {favoriteStylist.name?.split(' ')[0]}
                </button>
              </div>
            </motion.div>
          )}
        </FeatureGuard>

        {/* Trending Nearby */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.5, delay: 0.5 }}
          className="bg-surface border border-border-light p-6"
          style={{
            borderRadius: 'var(--brand-border-radius, 16px)',
            boxShadow: 'var(--brand-shadow-md, 0 4px 12px rgba(0,0,0,0.08))'
          }}
        >
          <div className="flex items-center justify-between mb-4">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-xl bg-orange-500/10 flex items-center justify-center">
                <TrendingUp className="w-5 h-5 text-orange-500" />
              </div>
              <h2 className="text-lg font-semibold text-text-primary">Trending Nearby</h2>
            </div>
            <a
              href="/portal/discover"
              className="text-sm hover:underline flex items-center gap-1"
              style={{ color: 'var(--brand-primary, #FFD700)' }}
            >
              See all <ChevronRight className="w-4 h-4" />
            </a>
          </div>
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
            {recommendedServices.slice(0, 4).map((service: any) => (
              <QuickServiceCard key={service.id} service={service} />
            ))}
          </div>
        </motion.div>
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.5, delay: 0.4 }}
          className="grid grid-cols-2 sm:grid-cols-3 gap-4"
        >
          <StatCard
            icon={Scissors}
            label="Total Visits"
            value={customer?.visits || 0}
          />
          <StatCard
            icon={Wallet}
            label="Wallet Balance"
            value={`UGX ${(walletSummary?.balance || 0).toLocaleString('en-UG', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`}
          />
          <StatCard
            icon={Sparkles}
            label="Loyalty Points"
            value={loyaltySummary?.points || 0}
          />
        </motion.div>
      </div>
  );
}

function QuickActionCard({ icon: Icon, label, href, color }: { icon: any, label: string, href: string, color: string }) {
  return (
    <a
      href={href}
      className="bg-surface border border-border-light p-4 hover:shadow-lg group transition-all"
      style={{ 
        borderRadius: 'var(--brand-border-radius, 16px)',
        boxShadow: 'var(--brand-shadow-sm, 0 2px 8px rgba(0,0,0,0.05))'
      }}
    >
      <div 
        className={`w-12 h-12 rounded-xl bg-gradient-to-br ${color} flex items-center justify-center mb-3 group-hover:scale-110 transition-transform`}
        style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
      >
        <Icon className="w-6 h-6 text-white" />
      </div>
      <p className="text-sm font-medium text-text-primary">{label}</p>
    </a>
  );
}

function StatCard({ icon: Icon, label, value }: { icon: any, label: string, value: string | number }) {
  return (
    <div 
      className="bg-surface border border-border-light p-4"
      style={{ 
        borderRadius: 'var(--brand-border-radius, 16px)',
        boxShadow: 'var(--brand-shadow-sm, 0 2px 8px rgba(0,0,0,0.05))'
      }}
    >
      <div className="flex items-center gap-2 mb-2">
        <Icon className="w-4 h-4" style={{ color: 'var(--brand-primary, #FFD700)' }} />
        <p className="text-xs text-text-secondary">{label}</p>
      </div>
      <p className="text-2xl font-bold text-text-primary">{value}</p>
    </div>
  );
}

function QuickServiceCard({ service }: { service: any }) {
  const router = useRouter();
  return (
    <div
      className="bg-surface border border-border-light p-4 transition-all cursor-pointer"
      style={{
        borderRadius: 'var(--brand-border-radius, 16px)',
        boxShadow: 'var(--brand-shadow-sm, 0 2px 8px rgba(0,0,0,0.05))'
      }}
      onClick={() => router.push(`/services/${service.id}`)}
      onMouseEnter={(e) => {
        e.currentTarget.style.borderColor = 'var(--brand-primary, #FFD700)';
      }}
      onMouseLeave={(e) => {
        e.currentTarget.style.borderColor = '';
      }}
    >
      <div
        className="w-full h-24 bg-surface border border-border-light mb-3 flex items-center justify-center"
        style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
      >
        <Scissors className="w-8 h-8 text-text-secondary" />
      </div>
      <p className="font-medium text-text-primary text-sm mb-1">{service.name}</p>
      <p className="text-xs text-text-secondary">UGX {service.price?.toLocaleString('en-UG', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}</p>
    </div>
  );
}
