'use client';

import { motion } from 'framer-motion';
import { Calendar, Sparkles, Wallet, User, Scissors, Clock, ChevronRight, Repeat, Gift, TrendingUp, Award, ArrowRight } from 'lucide-react';
import { usePortalAuth } from '@/contexts/PortalAuthContext';
import { portalApiClient } from '@/lib/portal-api-client';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { FeatureGuard } from '@/components/ui/FeatureGuard';
import { useRouter } from 'next/navigation';

export default function PortalHomePage() {
  const { customer, salon, isLoading: authLoading } = usePortalAuth();
  const queryClient = useQueryClient();
  const router = useRouter();

  const { data: homeData, isLoading: homeLoading } = useQuery({
    queryKey: ['portal-home'],
    queryFn: () => portalApiClient.get('/v1/portal/home'),
    enabled: !!customer,
  });

  const cancelMutation = useMutation({
    mutationFn: (bookingId: string) => portalApiClient.patch(`/v1/portal/bookings/${bookingId}/cancel`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['portal-home'] });
    },
  });

  if (authLoading || homeLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-background">
        <div className="text-text-primary">Loading...</div>
      </div>
    );
  }

  const getGreeting = () => {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good morning';
    if (hour < 18) return 'Good afternoon';
    return 'Good evening';
  };

  const upcomingBooking = homeData?.upcoming_booking;
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
            {getGreeting()}, {customer?.name?.split(' ')[0] || 'Welcome'}
          </h1>
          <p className="text-text-secondary">
            {salon?.name || 'Your Salon'}
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

        {/* Next Appointment Card */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.5, delay: 0.2 }}
          className="bg-surface border border-border-light rounded-2xl p-6"
        >
          <div className="flex items-center gap-3 mb-4">
            <div className="w-10 h-10 rounded-xl bg-gold/10 flex items-center justify-center">
              <Clock className="w-5 h-5 text-gold" />
            </div>
            <h2 className="text-lg font-semibold text-text-primary">Next Appointment</h2>
          </div>
          {upcomingBooking ? (
            <div className="space-y-4">
              <div className="flex items-center justify-between p-4 bg-surface border border-border-light rounded-xl">
                <div>
                  <p className="font-semibold text-text-primary">{upcomingBooking.service?.name}</p>
                  <p className="text-sm text-text-secondary">{upcomingBooking.staff?.name}</p>
                </div>
                <div className="text-right">
                  <p className="font-semibold text-text-primary">{upcomingBooking.date}</p>
                  <p className="text-sm text-text-secondary">{upcomingBooking.time}</p>
                </div>
              </div>
              <div className="flex gap-2">
                <button 
                  onClick={() => router.push(`/portal/bookings/new?reschedule=${upcomingBooking.id}`)}
                  className="flex-1 px-4 py-2 bg-gold/10 text-gold rounded-lg text-sm font-medium hover:bg-gold/20 transition-colors">
                  Reschedule
                </button>
                <button 
                  onClick={() => {
                    if (window.confirm('Are you sure you want to cancel this booking?')) {
                      cancelMutation.mutate(upcomingBooking.id);
                    }
                  }}
                  disabled={cancelMutation.isPending}
                  className="flex-1 px-4 py-2 bg-red-500/10 text-red-500 rounded-lg text-sm font-medium hover:bg-red-500/20 transition-colors disabled:opacity-50">
                  {cancelMutation.isPending ? 'Canceling...' : 'Cancel'}
                </button>
              </div>
            </div>
          ) : (
            <div className="text-center py-8">
              <p className="text-text-secondary">No upcoming appointments</p>
              <button className="mt-4 px-6 py-2 bg-gold text-white rounded-full text-sm font-medium hover:bg-gold/90 transition-colors">
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
              className="bg-gradient-to-r from-gold/20 to-dark-gold/20 border border-gold/30 rounded-2xl p-6"
            >
              <div className="flex items-center gap-3 mb-4">
                <div className="w-10 h-10 rounded-xl bg-gold/20 flex items-center justify-center">
                  <Repeat className="w-5 h-5 text-gold" />
                </div>
                <h2 className="text-lg font-semibold text-text-primary">Continue Last Service</h2>
              </div>
              <div className="flex items-center justify-between">
                <div>
                  <p className="font-semibold text-text-primary">{lastBooking.service?.name}</p>
                  <p className="text-sm text-text-secondary">
                    {lastBooking.staff?.name} • ${lastBooking.service?.price}
                  </p>
                </div>
                <button className="px-4 py-2 bg-gold text-white rounded-lg text-sm font-medium hover:bg-gold/90 transition-colors flex items-center gap-2">
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
              className="bg-surface border border-border-light rounded-2xl p-6"
            >
              <div className="flex items-center gap-3 mb-4">
                <div className="w-10 h-10 rounded-xl bg-red-500/10 flex items-center justify-center">
                  <Gift className="w-5 h-5 text-red-500" />
                </div>
                <h2 className="text-lg font-semibold text-text-primary">Active Offers</h2>
              </div>
              <div className="flex gap-4 overflow-x-auto pb-2">
                {offers.slice(0, 3).map((offer: any) => (
                  <div key={offer.id} className="flex-shrink-0 w-64 bg-gradient-to-br from-red-500/10 to-orange-500/10 border border-red-500/20 rounded-xl p-4">
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

        {/* Recommended Services */}
        {recommendedServices.length > 0 && (
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.5, delay: 0.35 }}
            className="bg-surface border border-border-light rounded-2xl p-6"
          >
            <div className="flex items-center justify-between mb-4">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-xl bg-purple-500/10 flex items-center justify-center">
                  <Sparkles className="w-5 h-5 text-purple-500" />
                </div>
                <h2 className="text-lg font-semibold text-text-primary">Recommended for You</h2>
              </div>
              <a href="/portal/discover" className="text-sm text-gold hover:underline flex items-center gap-1">
                See all <ChevronRight className="w-4 h-4" />
              </a>
            </div>
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
              {recommendedServices.slice(0, 4).map((service: any) => (
                <ServiceCard key={service.id} service={service} />
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
            className="bg-surface border border-border-light rounded-2xl p-6"
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
                  className="bg-gradient-to-r from-purple-500 to-purple-600 h-2 rounded-full transition-all"
                  style={{ width: `${loyaltySummary?.tier_progress || 0}%` }}
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
              className="bg-surface border border-border-light rounded-2xl p-6"
            >
              <div className="flex items-center gap-3 mb-4">
                <div className="w-10 h-10 rounded-xl bg-blue-500/10 flex items-center justify-center">
                  <TrendingUp className="w-5 h-5 text-blue-500" />
                </div>
                <h2 className="text-lg font-semibold text-text-primary">Recent Visits</h2>
              </div>
              <div className="space-y-3">
                {recentVisits.slice(0, 3).map((visit: any) => (
                  <div key={visit.id} className="flex items-center justify-between p-3 bg-surface border border-border-light rounded-lg">
                    <div>
                      <p className="font-medium text-text-primary text-sm">{visit.service?.name}</p>
                      <p className="text-xs text-text-secondary">{visit.staff?.name}</p>
                    </div>
                    <div className="text-right">
                      <p className="text-sm text-text-primary">{visit.date}</p>
                      <p className="text-xs text-text-secondary">{visit.time}</p>
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
              className="bg-gradient-to-r from-blue-500/10 to-purple-500/10 border border-blue-500/20 rounded-2xl p-6"
            >
              <div className="flex items-center gap-3 mb-4">
                <div className="w-10 h-10 rounded-xl bg-blue-500/20 flex items-center justify-center">
                  <User className="w-5 h-5 text-blue-500" />
                </div>
                <h2 className="text-lg font-semibold text-text-primary">Your Stylist</h2>
              </div>
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <div className="w-12 h-12 rounded-full bg-gradient-to-br from-blue-500 to-purple-500 flex items-center justify-center text-white font-semibold">
                    {favoriteStylist.name?.charAt(0)}
                  </div>
                  <div>
                    <p className="font-semibold text-text-primary">{favoriteStylist.name}</p>
                    <p className="text-sm text-text-secondary">Preferred Stylist</p>
                  </div>
                </div>
                <button className="px-4 py-2 bg-blue-500 text-white rounded-lg text-sm font-medium hover:bg-blue-600 transition-colors">
                  Book with {favoriteStylist.name?.split(' ')[0]}
                </button>
              </div>
            </motion.div>
          )}
        </FeatureGuard>

        {/* Stats Cards */}
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
            value={`$${walletSummary?.balance || 0}.00`}
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
      className="bg-surface border border-border-light rounded-2xl p-4 hover:border-gold/30 transition-all hover:shadow-lg group"
    >
      <div className={`w-12 h-12 rounded-xl bg-gradient-to-br ${color} flex items-center justify-center mb-3 group-hover:scale-110 transition-transform`}>
        <Icon className="w-6 h-6 text-white" />
      </div>
      <p className="text-sm font-medium text-text-primary">{label}</p>
    </a>
  );
}

function StatCard({ icon: Icon, label, value }: { icon: any, label: string, value: string | number }) {
  return (
    <div className="bg-surface border border-border-light rounded-2xl p-4">
      <div className="flex items-center gap-2 mb-2">
        <Icon className="w-4 h-4 text-gold" />
        <p className="text-xs text-text-secondary">{label}</p>
      </div>
      <p className="text-2xl font-bold text-text-primary">{value}</p>
    </div>
  );
}

function ServiceCard({ service }: { service: any }) {
  return (
    <div className="bg-surface border border-border-light rounded-xl p-4 hover:border-gold/30 transition-all cursor-pointer">
      <div className="w-full h-24 bg-surface border border-border-light rounded-lg mb-3 flex items-center justify-center">
        <Scissors className="w-8 h-8 text-text-secondary" />
      </div>
      <p className="font-medium text-text-primary text-sm mb-1">{service.name}</p>
      <p className="text-xs text-text-secondary">${service.price}</p>
    </div>
  );
}
