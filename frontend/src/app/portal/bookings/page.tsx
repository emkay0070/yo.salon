'use client';

import { motion } from 'framer-motion';
import { Calendar, Clock, Scissors, User, Plus } from 'lucide-react';
import { usePortalAuth } from '@/contexts/PortalAuthContext';
import { usePortalBrand } from '@/contexts/PortalBrandContext';
import { portalApiClient } from '@/lib/portal-api-client';
import { useQuery } from '@tanstack/react-query';
import ClientLayout from '@/components/ClientLayout';
import Link from 'next/link';
import { useState } from 'react';

export default function BookingsPage() {
  const { customer, salon } = usePortalAuth();
  const { brand } = usePortalBrand();

  const [activeTab, setActiveTab] = useState<'upcoming' | 'history'>('upcoming');

  const { data: bookingsData, isLoading } = useQuery({
    queryKey: ['portal-bookings'],
    queryFn: () => portalApiClient.get('/v1/portal/bookings'),
    enabled: !!customer,
  });

  const displayedBookings = activeTab === 'upcoming' 
    ? bookingsData?.upcoming || []
    : bookingsData?.history || [];

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-background">
        <div className="text-text-primary">Loading...</div>
      </div>
    );
  }

  return (
    <ClientLayout>
      <div className="space-y-6">
        {/* Header */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          className="flex items-center justify-between"
        >
          <div>
            <h1 className="text-3xl font-bold text-text-primary mb-2">My Bookings</h1>
            <p className="text-text-secondary">Manage your appointments</p>
          </div>
          <Link
            href="/portal/bookings/new"
            className="flex items-center gap-2 px-4 py-2 text-obsidian rounded-full font-medium transition-colors"
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
            Book Now
          </Link>
        </motion.div>

        {/* Tabs */}
        <div className="flex gap-4 border-b border-border-light">
          <button
            onClick={() => setActiveTab('upcoming')}
            className="pb-4 text-sm font-medium transition-colors relative"
            style={{ 
              color: activeTab === 'upcoming' ? 'var(--brand-primary, #FFD700)' : 'var(--text-secondary, #888)'
            }}
            onMouseEnter={(e) => {
              if (activeTab !== 'upcoming') {
                e.currentTarget.style.color = 'var(--brand-primary, #FFD700)';
              }
            }}
            onMouseLeave={(e) => {
              if (activeTab !== 'upcoming') {
                e.currentTarget.style.color = 'var(--text-secondary, #888)';
              }
            }}
          >
            Upcoming
            {activeTab === 'upcoming' && (
              <motion.div layoutId="activeTab" className="absolute bottom-0 left-0 right-0 h-0.5" style={{ backgroundColor: 'var(--brand-primary, #FFD700)' }} />
            )}
          </button>
          <button
            onClick={() => setActiveTab('history')}
            className="pb-4 text-sm font-medium transition-colors relative"
            style={{ 
              color: activeTab === 'history' ? 'var(--brand-primary, #FFD700)' : 'var(--text-secondary, #888)'
            }}
            onMouseEnter={(e) => {
              if (activeTab !== 'history') {
                e.currentTarget.style.color = 'var(--brand-primary, #FFD700)';
              }
            }}
            onMouseLeave={(e) => {
              if (activeTab !== 'history') {
                e.currentTarget.style.color = 'var(--text-secondary, #888)';
              }
            }}
          >
            History
            {activeTab === 'history' && (
              <motion.div layoutId="activeTab" className="absolute bottom-0 left-0 right-0 h-0.5" style={{ backgroundColor: 'var(--brand-primary, #FFD700)' }} />
            )}
          </button>
        </div>

        {/* Bookings List */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.1 }}
          className="space-y-4"
        >
          {displayedBookings && displayedBookings.length > 0 ? (
            displayedBookings.map((booking: any) => (
              <BookingCard key={booking.id} booking={booking} />
            ))
          ) : (
            <div 
              className="bg-surface border border-border-light p-12 text-center"
              style={{ 
                borderRadius: 'var(--brand-border-radius, 16px)',
                boxShadow: 'var(--brand-shadow-md, 0 6px 18px rgba(0,0,0,0.5))'
              }}
            >
              <Calendar className="w-16 h-16 text-text-secondary mx-auto mb-4" />
              <h3 className="text-xl font-semibold text-text-primary mb-2">
                {activeTab === 'upcoming' ? 'No upcoming bookings' : 'No booking history'}
              </h3>
              <p className="text-text-secondary mb-6">
                {activeTab === 'upcoming' ? 'Book your next appointment to get started' : "You haven't had any appointments yet"}
              </p>
              {activeTab === 'upcoming' && (
                <Link
                  href="/portal/bookings/new"
                  className="inline-flex items-center gap-2 px-6 py-3 text-obsidian rounded-full font-medium transition-colors"
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
                  Book Now
                </Link>
              )}
            </div>
          )}
        </motion.div>
      </div>
    </ClientLayout>
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
        boxShadow: 'var(--brand-shadow-sm, 0 2px 8px rgba(0,0,0,0.4))'
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
          <span>{booking.date} at {booking.time}</span>
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
