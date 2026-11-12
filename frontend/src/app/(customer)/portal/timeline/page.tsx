'use client';

import { motion } from 'framer-motion';
import { Calendar, Image, Star, Scissors, ChevronLeft, ChevronRight, Download, Share2, Filter } from 'lucide-react';
import { usePortalAuth } from '@/contexts/PortalAuthContext';
import { usePortalBrand } from '@/contexts/PortalBrandContext';
import { portalApiClient } from '@/lib/portal-api-client';
import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';

export default function TimelinePage() {
  const { customer, salon } = usePortalAuth();
  const { brand } = usePortalBrand();
  const [selectedMonth, setSelectedMonth] = useState(new Date());
  const [filter, setFilter] = useState<'all' | 'hair' | 'beard' | 'spa' | 'other'>('all');

  const { data: timelineResponse, isLoading } = useQuery({
    queryKey: ['portal-timeline', filter, selectedMonth.getFullYear(), selectedMonth.getMonth() + 1],
    queryFn: () => portalApiClient.get(`/portal/timeline?category=${filter}&year=${selectedMonth.getFullYear()}&month=${selectedMonth.getMonth() + 1}`),
    enabled: !!customer,
  });

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-background">
        <div className="text-text-primary">Loading...</div>
      </div>
    );
  }

  const entries = timelineResponse?.data?.entries?.data || [];
  const stats = timelineResponse?.data?.stats || { total_visits: 0, total_spent: 0, avg_rating: 0, total_photos: 0 };

  const filteredTimeline = entries;

  const months = [
    'July 2026', 'June 2026', 'May 2026', 'April 2026', 'March 2026', 'February 2026',
    'January 2026', 'December 2025', 'November 2025', 'October 2025', 'September 2025',
  ];

  return (
    <div className="space-y-6">
      {/* Header */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
      >
        <h1 className="text-3xl font-bold text-text-primary mb-2">Grooming Timeline</h1>
        <p className="text-text-secondary">Your personal grooming journey over time</p>
      </motion.div>

      {/* Month Navigation */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.1 }}
        className="flex items-center justify-between"
      >
        <button
          onClick={() => {
            const newDate = new Date(selectedMonth);
            newDate.setMonth(newDate.getMonth() - 1);
            setSelectedMonth(newDate);
          }}
          className="p-2 rounded-xl bg-surface border border-border-light hover:border-gold/30 transition-colors"
          style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
        >
          <ChevronLeft className="w-5 h-5 text-text-secondary" />
        </button>
        <div className="flex items-center gap-2">
          <Calendar className="w-5 h-5" style={{ color: 'var(--brand-primary, #FFD700)' }} />
          <span className="text-lg font-semibold text-text-primary">
            {selectedMonth.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })}
          </span>
        </div>
        <button
          onClick={() => {
            const newDate = new Date(selectedMonth);
            newDate.setMonth(newDate.getMonth() + 1);
            setSelectedMonth(newDate);
          }}
          className="p-2 rounded-xl bg-surface border border-border-light hover:border-gold/30 transition-colors"
          style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}
        >
          <ChevronRight className="w-5 h-5 text-text-secondary" />
        </button>
      </motion.div>

      {/* Filter Pills */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.15 }}
        className="flex gap-2 overflow-x-auto pb-2"
      >
        {[
          { id: 'all', label: 'All' },
          { id: 'hair', label: 'Hair' },
          { id: 'beard', label: 'Beard' },
          { id: 'spa', label: 'Spa' },
          { id: 'other', label: 'Other' },
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

      {/* Timeline */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.2 }}
        className="relative"
      >
        {/* Timeline Line */}
        <div className="absolute left-8 top-0 bottom-0 w-0.5 bg-border-light" />
        
        <div className="space-y-6">
          {filteredTimeline.map((entry: any, index: number) => (
            <TimelineEntry key={entry.id} entry={entry} delay={index * 0.1} />
          ))}
        </div>
      </motion.div>

      {/* Stats */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.5 }}
        className="grid grid-cols-2 sm:grid-cols-4 gap-4"
      >
        <TimelineStat label="Total Visits" value={stats.total_visits} icon={Scissors} />
        <TimelineStat label="Total Spent" value={`UGX ${(stats.total_spent || 0).toLocaleString('en-UG', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`} icon={Star} />
        <TimelineStat label="Avg Rating" value={stats.avg_rating?.toFixed(1) || '0'} icon={Star} />
        <TimelineStat label="Photos" value={stats.total_photos || 0} icon={Image} />
      </motion.div>
    </div>
  );
}

function TimelineEntry({ entry, delay }: { entry: any, delay: number }) {
  const formatDate = new Date(entry.service_date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });

  return (
    <motion.div
      initial={{ opacity: 0, x: -20 }}
      animate={{ opacity: 1, x: 0 }}
      transition={{ delay }}
      className="relative flex gap-4"
    >
      {/* Timeline Dot */}
      <div className="w-16 flex flex-col items-center">
        <div className="w-4 h-4 rounded-full border-4 border-surface" style={{ backgroundColor: 'var(--brand-primary, #FFD700)' }} />
      </div>

      {/* Content */}
      <div className="flex-1 bg-surface border border-border-light rounded-2xl p-5 hover:border-gold/30 transition-all" style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}>
        <div className="flex items-start justify-between mb-4">
          <div>
            <h3 className="font-semibold text-text-primary text-lg">{entry.service_name}</h3>
            <p className="text-sm text-text-secondary">{formatDate}</p>
          </div>
          {entry.rating && (
            <div className="flex items-center gap-1">
              <Star className="w-4 h-4" style={{ color: 'var(--brand-primary, #FFD700)' }} fill="currentColor" />
              <span className="font-semibold text-text-primary">{entry.rating}</span>
            </div>
          )}
        </div>

        <div className="space-y-2 mb-4">
          {entry.specialist_name && (
            <div className="flex items-center gap-2 text-sm text-text-secondary">
              <span>Specialist:</span>
              <span className="font-medium text-text-primary">{entry.specialist_name}</span>
            </div>
          )}
          {entry.provider_name && (
            <div className="flex items-center gap-2 text-sm text-text-secondary">
              <span>Provider:</span>
              <span className="font-medium text-text-primary">{entry.provider_name}</span>
            </div>
          )}
          {entry.price && (
            <div className="flex items-center gap-2 text-sm text-text-secondary">
              <span>Price:</span>
              <span className="font-medium" style={{ color: 'var(--brand-primary, #FFD700)' }}>UGX {entry.price.toLocaleString('en-UG', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}</span>
            </div>
          )}
        </div>

        {/* Before/After Section */}
        <div className="grid grid-cols-2 gap-4 mb-4">
          <div className="aspect-square bg-surface border border-border-light rounded-xl flex items-center justify-center overflow-hidden" style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}>
            {entry.before_photo ? (
              <img src={entry.before_photo} alt="Before" className="w-full h-full object-cover" />
            ) : (
              <div className="text-center">
                <Image className="w-8 h-8 text-text-secondary mx-auto mb-2" />
                <p className="text-xs text-text-secondary">Before</p>
              </div>
            )}
          </div>
          <div className="aspect-square bg-surface border border-border-light rounded-xl flex items-center justify-center overflow-hidden" style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}>
            {entry.after_photo ? (
              <img src={entry.after_photo} alt="After" className="w-full h-full object-cover" />
            ) : (
              <div className="text-center">
                <Image className="w-8 h-8 text-text-secondary mx-auto mb-2" />
                <p className="text-xs text-text-secondary">After</p>
              </div>
            )}
          </div>
        </div>

        {/* Actions */}
        <div className="flex gap-2">
          <button className="flex-1 flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-surface border border-border-light hover:border-gold/30 transition-colors" style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}>
            <Download className="w-4 h-4" />
            Download
          </button>
          <button className="flex-1 flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-surface border border-border-light hover:border-gold/30 transition-colors" style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}>
            <Share2 className="w-4 h-4" />
            Share
          </button>
        </div>
      </div>
    </motion.div>
  );
}

function TimelineStat({ label, value, icon: Icon }: { label: string, value: string | number, icon: any }) {
  return (
    <div className="bg-surface border border-border-light rounded-2xl p-4" style={{ borderRadius: 'var(--brand-border-radius, 16px)' }}>
      <div className="flex items-center gap-2 mb-2">
        <Icon className="w-4 h-4" style={{ color: 'var(--brand-primary, #FFD700)' }} />
        <span className="text-xs text-text-secondary">{label}</span>
      </div>
      <p className="text-xl font-bold text-text-primary">{value}</p>
    </div>
  );
}
