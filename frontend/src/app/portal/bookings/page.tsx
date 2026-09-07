'use client';

import { motion } from 'framer-motion';
import { Calendar, Clock, Scissors, User, Plus } from 'lucide-react';
import { usePortalAuth } from '@/contexts/PortalAuthContext';
import { usePortalBrand } from '@/contexts/PortalBrandContext';
import { portalApiClient } from '@/lib/portal-api-client';
import { useQuery } from '@tanstack/react-query';
import Link from 'next/link';
import { useState } from 'react';
import { CardSkeleton } from '@/components/ui/LoadingSkeleton';
import { usePolling } from '@/hooks/usePolling';

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

export default function BookingsPage() {
  const { customer, salon } = usePortalAuth();
  const { brand } = usePortalBrand();

  const [activeTab, setActiveTab] = useState<'upcoming' | 'history'>('upcoming');

  const { data: bookingsData, isLoading, error, refetch } = useQuery({
    queryKey: ['portal-bookings'],
    queryFn: () => portalApiClient.get('/portal/bookings'),
    enabled: !!customer,
    retry: 1,
  });

  // Poll for booking updates every 30 seconds
  usePolling(
    async () => {
      await refetch();
    },
    {
      enabled: !!customer,
      interval: 30000, // 30 seconds
    }
  );

  const displayedBookings = activeTab === 'upcoming' 
    ? bookingsData?.upcoming || []
    : bookingsData?.history || [];

  if (isLoading) {
    return (
      <div className="space-y-6">
        {/* Header Skeleton */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          className="flex items-center justify-between"
        >
          <div className="space-y-2">
            <div className="h-10 w-48 bg-gray-200 rounded animate-pulse" />
            <div className="h-5 w-32 bg-gray-200 rounded animate-pulse" />
          </div>
          <div className="h-10 w-24 bg-gray-200 rounded-full animate-pulse" />
        </motion.div>

        {/* Tabs Skeleton */}
        <div className="flex gap-4 border-b border-border-light">
          <div className="h-10 w-24 bg-gray-200 rounded animate-pulse" />
          <div className="h-10 w-20 bg-gray-200 rounded animate-pulse" />
        </div>

        {/* Bookings List Skeleton */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.1 }}
          className="space-y-4"
        >
          <CardSkeleton />
          <CardSkeleton />
          <CardSkeleton />
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
          <h2 className="text-xl font-semibold text-text-primary mb-2">Unable to load bookings</h2>
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

  return (
    <div className="space-y-6">
      {/* Header */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        className="flex items-center justify-between"
      >
        <div className="space-y-2">
          <h1 className="text-3xl font-bold text-text-primary">Book</h1>
          <p className="text-text-secondary">Your appointments</p>
        </div>
        <Link
          href="/portal/bookings/new"
          className="flex items-center gap-2 px-4 py-2 text-sm font-medium transition-colors rounded-full"
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
          <Plus className="w-4 h-4" />
          New Booking
        </Link>
      </motion.div>

      {/* Tabs */}
      <div className="flex gap-4 border-b border-border-light">
        <button
          onClick={() => setActiveTab('upcoming')}
          className={`px-4 py-2 text-sm font-medium transition-colors ${
            activeTab === 'upcoming'
              ? 'text-text-primary border-b-2'
              : 'text-text-secondary hover:text-text-primary'
          }`}
          style={{
            borderColor: activeTab === 'upcoming' ? 'var(--brand-primary, #FFD700)' : 'transparent'
          }}
        >
          Upcoming
        </button>
        <button
          onClick={() => setActiveTab('history')}
          className={`px-4 py-2 text-sm font-medium transition-colors ${
            activeTab === 'history'
              ? 'text-text-primary border-b-2'
              : 'text-text-secondary hover:text-text-primary'
          }`}
          style={{
            borderColor: activeTab === 'history' ? 'var(--brand-primary, #FFD700)' : 'transparent'
          }}
        >
          History
        </button>
      </div>

      {/* Bookings List - Apple Wallet Style */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.1 }}
        className="space-y-4"
      >
        {displayedBookings.length > 0 ? (
          displayedBookings.map((booking: any, index: number) => (
            <WalletBookingCard key={booking.id} booking={booking} index={index} />
          ))
        ) : (
          <div className="bg-surface border border-border-light rounded-2xl p-12 text-center"
            style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
          >
            <Calendar className="w-16 h-16 text-text-secondary mx-auto mb-4" />
            <h3 className="text-xl font-semibold text-text-primary mb-2">
              {activeTab === 'upcoming' ? 'No upcoming appointments' : 'No booking history'}
            </h3>
            <p className="text-text-secondary mb-4">
              {activeTab === 'upcoming' ? 'Book your first appointment' : 'Your completed bookings will appear here'}
            </p>
            {activeTab === 'upcoming' && (
              <Link
                href="/portal/bookings/new"
                className="inline-flex items-center gap-2 px-6 py-2 text-white text-sm font-medium transition-colors rounded-full"
                style={{
                  backgroundColor: 'var(--brand-primary, #FFD700)',
                  borderRadius: 'var(--brand-border-radius, 16px)'
                }}
              >
                Book Now
              </Link>
            )}
          </div>
        )}
      </motion.div>
    </div>
  );
}

function WalletBookingCard({ booking, index }: { booking: any, index: number }) {
  const statusColors: Record<string, string> = {
    confirmed: 'bg-emerald-500/10 text-emerald-500',
    pending: 'bg-amber-500/10 text-amber-500',
    cancelled: 'bg-red-500/10 text-red-500',
    completed: 'bg-blue-500/10 text-blue-500',
  };

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ delay: index * 0.1 }}
      className="relative overflow-hidden cursor-pointer group"
      style={{
        borderRadius: 'var(--brand-border-radius, 16px)',
        background: `linear-gradient(135deg, var(--brand-primary, #FFD700)10, var(--brand-secondary, #FF8C5A)5)`,
        boxShadow: 'var(--brand-shadow-md, 0 4px 12px rgba(0,0,0,0.08))'
      }}
    >
      <div className="p-6">
        <div className="flex items-start justify-between mb-4">
          <div className="flex-1">
            <div className="flex items-center gap-2 mb-2">
              <span className={`px-3 py-1 rounded-full text-xs font-medium ${statusColors[booking.status as keyof typeof statusColors] || statusColors.pending}`}>
                {booking.status}
              </span>
            </div>
            <h3 className="text-xl font-bold text-text-primary mb-1">{booking.service?.name}</h3>
            <p className="text-text-secondary">{booking.staff?.name}</p>
          </div>
          <div className="text-right">
            <p className="text-2xl font-bold text-text-primary">{formatTime(booking.time)}</p>
            <p className="text-sm text-text-secondary">{formatDate(booking.date)}</p>
          </div>
        </div>

        <div className="flex items-center justify-between pt-4 border-t border-border-light">
          <div className="flex items-center gap-4 text-sm text-text-secondary">
            <div className="flex items-center gap-1">
              <Clock className="w-4 h-4" />
              <span>{booking.service?.duration} min</span>
            </div>
            <div className="flex items-center gap-1">
              <Scissors className="w-4 h-4" />
              <span>{booking.service?.category}</span>
            </div>
          </div>
          <div className="flex items-center gap-2">
            <span className="text-lg font-bold" style={{ color: 'var(--brand-primary, #FFD700)' }}>
              {booking.service?.price}
            </span>
          </div>
        </div>
      </div>

      {/* Apple Wallet-style corner accent */}
      <div className="absolute top-0 right-0 w-16 h-16 opacity-20">
        <div className="absolute top-0 right-0 w-8 h-8 rounded-bl-3xl" style={{ backgroundColor: 'var(--brand-primary, #FFD700)' }} />
      </div>
    </motion.div>
  );
}

function BookingCard({ booking }: { booking: any }) {
  const statusColors = {
    confirmed: 'bg-emerald-500/10 text-emerald-500',
    pending: 'bg-amber-500/10 text-amber-500',
    cancelled: 'bg-red-500/10 text-red-500',
    completed: 'bg-blue-500/10 text-blue-500',
  };

  return (
    <div 
      className="bg-surface border border-border-light p-6 transition-all"
      style={{ 
        borderRadius: 'var(--brand-border-radius, 16px)',
        boxShadow: 'var(--brand-shadow-sm, 0 2px 8px rgba(0,0,0,0.05))'
      }}
      onMouseEnter={(e) => {
        e.currentTarget.style.borderColor = 'var(--brand-primary, #FFD700)';
      }}
      onMouseLeave={(e) => {
        e.currentTarget.style.borderColor = '';
      }}
    >
      <div className="flex items-start justify-between mb-4">
        <div className="flex items-center gap-4">
          <div 
            className="w-12 h-12 rounded-xl flex items-center justify-center"
            style={{ 
              backgroundColor: 'var(--brand-primary, #FFD700)20',
              borderRadius: 'var(--brand-border-radius, 16px)'
            }}
          >
            <Calendar className="w-6 h-6" style={{ color: 'var(--brand-primary, #FFD700)' }} />
          </div>
          <div>
            <h3 className="font-semibold text-text-primary">{booking.service?.name}</h3>
            <p className="text-sm text-text-secondary">{booking.staff?.name}</p>
          </div>
        </div>
        <span className={`px-3 py-1 rounded-full text-xs font-medium ${statusColors[booking.status as keyof typeof statusColors] || statusColors.pending}`}>
          {booking.status}
        </span>
      </div>
      
      <div className="flex items-center gap-6 text-sm">
        <div className="flex items-center gap-2 text-text-secondary">
          <Clock className="w-4 h-4" />
          <span>{formatDate(booking.date)} at {formatTime(booking.time)}</span>
        </div>
        <div className="flex items-center gap-2 text-text-secondary">
          <Scissors className="w-4 h-4" />
          <span>{booking.service?.duration} min</span>
        </div>
        <div className="flex items-center gap-2 text-text-primary font-medium">
          <span>${booking.service?.price}</span>
        </div>
      </div>
    </div>
  );
}
