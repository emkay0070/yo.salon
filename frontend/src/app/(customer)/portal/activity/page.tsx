'use client';

import { motion } from 'framer-motion';
import { Bell, Image, Gift, Star, MapPin, Clock, Calendar, CheckCircle, AlertCircle, TrendingUp, Award } from 'lucide-react';
import { usePortalAuth } from '@/contexts/PortalAuthContext';
import { usePortalBrand } from '@/contexts/PortalBrandContext';
import { portalApiClient } from '@/lib/portal-api-client';
import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';

export default function ActivityPage() {
  const { customer, salon } = usePortalAuth();
  const { brand } = usePortalBrand();
  const [filter, setFilter] = useState<'all' | 'promotions' | 'points' | 'reviews' | 'providers'>('all');

  const { data: response, isLoading } = useQuery({
    queryKey: ['portal-activity', filter],
    queryFn: () => portalApiClient.get(`/portal/activity?type=${filter}`),
    enabled: !!customer,
  });

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-background">
        <div className="text-text-primary">Loading...</div>
      </div>
    );
  }

  const activities = response?.data?.activities?.data || [];

  const filteredActivities = activities;

  return (
    <div className="space-y-6">
      {/* Header */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
      >
        <h1 className="text-3xl font-bold text-text-primary mb-2">Activity</h1>
        <p className="text-text-secondary">Stay updated with what's happening around you</p>
      </motion.div>

      {/* Filter Pills */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.1 }}
        className="flex gap-2 overflow-x-auto pb-2"
      >
        {[
          { id: 'all', label: 'All' },
          { id: 'promotions', label: 'Promotions' },
          { id: 'points', label: 'Points' },
          { id: 'reviews', label: 'Reviews' },
          { id: 'providers', label: 'Providers' },
        ].map((f) => (
          <button
            key={f.id}
            onClick={() => setFilter(f.id as any)}
            className={`flex-shrink-0 px-5 py-2 rounded-2xl text-sm font-medium transition-all ${
              filter === f.id
                ? 'text-white'
                : 'bg-surface border border-border-light text-text-secondary hover:border-gold/30'
            }`}
            style={{
              ...(filter === f.id
                ? { backgroundColor: 'var(--brand-primary, #FFD700)', borderRadius: 'var(--brand-border-radius, 16px)' }
                : { borderRadius: 'var(--brand-border-radius, 16px)' })
            }}
          >
            {f.label}
          </button>
        ))}
      </motion.div>

      {/* Activity Feed */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.2 }}
        className="space-y-3"
      >
        {filteredActivities.map((activity: any, index: number) => (
          <ActivityCard key={activity.id} activity={activity} delay={index * 0.05} />
        ))}
      </motion.div>

      {/* Load More */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.6 }}
        className="text-center"
      >
        <button className="px-6 py-3 text-sm font-medium rounded-xl" style={{ backgroundColor: 'var(--brand-primary, #FFD700)20', color: 'var(--brand-primary, #FFD700)', borderRadius: 'var(--brand-border-radius, 16px)' }}>
          Load More
        </button>
      </motion.div>
    </div>
  );
}

function ActivityCard({ activity, delay }: { activity: any, delay: number }) {
  const Icon = activity.icon;

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ delay }}
      className="bg-surface border border-border-light rounded-2xl p-5 hover:border-gold/30 transition-all cursor-pointer"
      style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
    >
      <div className="flex items-start gap-4">
        <div className="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0" style={{ backgroundColor: `${activity.color}20` }}>
          <Icon className="w-6 h-6" style={{ color: activity.color }} />
        </div>
        <div className="flex-1">
          <div className="flex items-start justify-between mb-1">
            <h3 className="font-semibold text-text-primary">{activity.title}</h3>
            <span className="text-xs text-text-secondary whitespace-nowrap ml-2">{activity.timestamp}</span>
          </div>
          <p className="text-sm text-text-secondary">{activity.description}</p>
        </div>
      </div>
    </motion.div>
  );
}
