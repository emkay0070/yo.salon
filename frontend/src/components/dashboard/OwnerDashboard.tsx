'use client';

import { motion } from 'framer-motion';
import {
  AlertTriangle, ArrowRight, Brain, Briefcase, TrendingUp,
  DollarSign, Users, Calendar, Zap, ChevronRight
} from 'lucide-react';
import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';
import { useRole } from '@/contexts/RoleContext';
import Link from 'next/link';
import { Button } from '@/components/ui/button';
import { TrendChart } from '@/components/analytics/TrendChart';
import { CopyLinkModal } from '@/components/ui/CopyLinkModal';
import { LiveDashboardWidgets } from '@/components/Notifications/LiveDashboardWidgets';
import { useState } from 'react';
import {
  AreaChart, Area, BarChart, Bar, XAxis, YAxis,
  CartesianGrid, Tooltip, ResponsiveContainer, Cell
} from 'recharts';

interface OwnerDashboardProps {
  userName: string;
}

function formatUGX(val: number): string {
  if (val >= 1_000_000) return `UGX ${(val / 1_000_000).toFixed(1)}M`;
  if (val >= 1_000) return `UGX ${(val / 1_000).toFixed(0)}K`;
  return `UGX ${val.toLocaleString()}`;
}

const SIGNAL_COLOR: Record<string, string> = {
  critical: '#ef4444',
  high: '#f97316',
  medium: '#eab308',
  low: '#3b82f6',
};

export default function OwnerDashboard({ userName }: OwnerDashboardProps) {
  const { salonId, user } = useRole();
  const salonSlug = user?.salons?.[0]?.slug;
  
  const publicBookingUrl = salonSlug
    ? `${window.location.origin}/salons/${salonSlug}`
    : null;
  const [bookingModalOpen, setBookingModalOpen] = useState(false);

  const { data: intelligence, isLoading } = useQuery({
    queryKey: ['intelligence', salonId],
    queryFn: () => apiClient.getIntelligence(),
    enabled: !!salonId,
  });

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-96 gap-3 text-gold">
        <Brain className="w-8 h-8 animate-pulse" />
        <span className="text-sm text-text-secondary animate-pulse">Compiling intelligence…</span>
      </div>
    );
  }

  if (!intelligence) return null;

  const topSignals  = intelligence.signals?.slice(0, 4) || [];
  const topStaff    = intelligence.analytics?.staff?.[0];
  const rev         = intelligence.analytics?.revenue ?? {};
  const revTrend    = intelligence.analytics?.revenue_trend ?? [];
  const staffList   = (intelligence.analytics?.staff ?? []).slice(0, 4);
  const narrative   = (intelligence.briefing?.narrative || '')
    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

  /* ── Weekly bookings for bar chart ───────────────── */
  const todayIndex = new Date().getDay(); // 0 = Sunday, 1 = Monday, etc.
  const weeklyData = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'].map((day, i) => ({
    day: day.substring(0, 3), // Display as Mon, Tue, etc.
    fullDay: day,
    bookings: (intelligence.analytics?.bookings_by_day?.[day]) ?? 0,
    isToday: i === (todayIndex === 0 ? 6 : todayIndex - 1), // Adjust for Monday=0
  }));

  return (
    <div className="space-y-5">

      {/* ═══ CINEMATIC HERO BRIEFING ═══════════════════════════════════════ */}
      <motion.section
        initial={{ opacity: 0, y: -20, scale: 0.98 }}
        animate={{ opacity: 1, y: 0, scale: 1 }}
        transition={{ duration: 0.6, ease: [0.16, 1, 0.3, 1] }}
        className="relative overflow-hidden rounded-[32px] border border-white/[0.08] bg-gradient-to-br from-white/[0.03] via-white/[0.01] to-transparent backdrop-blur-2xl"
        style={{ 
          boxShadow: '0 8px 32px rgba(0,0,0,0.12), 0 0 0 1px rgba(255,255,255,0.05)',
        }}
      >
        {/* Animated gradient background */}
        <div className="absolute inset-0 overflow-hidden">
          <motion.div
            animate={{
              backgroundPosition: ['0% 0%', '100% 100%', '0% 0%'],
            }}
            transition={{
              duration: 20,
              repeat: Infinity,
              ease: 'linear',
            }}
            className="absolute inset-0 opacity-30"
            style={{
              background: 'radial-gradient(circle at 30% 20%, rgba(255,215,0,0.15) 0%, transparent 50%), radial-gradient(circle at 70% 80%, rgba(108,92,231,0.1) 0%, transparent 50%)',
              backgroundSize: '200% 200%',
            }}
          />
        </div>

        {/* Floating particles */}
        <div className="absolute inset-0 overflow-hidden pointer-events-none">
          {[...Array(6)].map((_, i) => (
            <motion.div
              key={i}
              animate={{
                y: [0, -20, 0],
                opacity: [0.3, 0.6, 0.3],
              }}
              transition={{
                duration: 4 + i * 0.5,
                repeat: Infinity,
                ease: 'easeInOut',
                delay: i * 0.3,
              }}
              className="absolute w-1 h-1 rounded-full bg-gold/40"
              style={{
                left: `${10 + i * 15}%`,
                top: `${20 + i * 12}%`,
              }}
            />
          ))}
        </div>

        {/* Top accent line with animation */}
        <motion.div
          initial={{ scaleX: 0 }}
          animate={{ scaleX: 1 }}
          transition={{ duration: 0.8, delay: 0.2, ease: [0.16, 1, 0.3, 1] }}
          className="absolute top-0 inset-x-0 h-px bg-gradient-to-r from-transparent via-gold to-transparent opacity-80 origin-left"
        />

        {/* Watermark with subtle animation */}
        <motion.div
          animate={{ 
            rotate: [0, 5, 0],
            scale: [1, 1.02, 1],
          }}
          transition={{
            duration: 8,
            repeat: Infinity,
            ease: 'easeInOut',
          }}
          className="absolute -bottom-12 -right-12 opacity-[0.03] pointer-events-none"
        >
          <Brain className="w-80 h-80 text-gold" />
        </motion.div>

        <div className="relative z-10 p-8 sm:p-10">
          <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-6">
            <div className="max-w-2xl">
              <motion.div
                initial={{ opacity: 0, x: -10 }}
                animate={{ opacity: 1, x: 0 }}
                transition={{ delay: 0.3, duration: 0.5 }}
              >
                <p className="text-xs font-semibold uppercase tracking-[0.2em] text-gold/80 mb-3 flex items-center gap-2">
                  <span className="w-1.5 h-1.5 rounded-full bg-gold animate-pulse" />
                  Intelligence Briefing
                </p>
                <h1 className="text-3xl sm:text-4xl font-bold text-white tracking-tight leading-[1.2] mb-4 bg-gradient-to-r from-white via-white/90 to-white/70 bg-clip-text text-transparent">
                  {intelligence.briefing?.greeting || `Good afternoon, ${userName}.`}
                </h1>
                <p
                  className="text-white/60 text-base leading-relaxed"
                  dangerouslySetInnerHTML={{ __html: narrative }}
                />
              </motion.div>
            </div>

            {/* Floating KPI Pills */}
            <motion.div
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.4, duration: 0.5 }}
              className="flex sm:flex-col gap-3 flex-wrap"
            >
              <motion.div
                whileHover={{ scale: 1.02, y: -2 }}
                transition={{ duration: 0.2 }}
                className="flex items-center gap-3 px-5 py-3 rounded-2xl bg-gradient-to-br from-gold/20 to-gold/5 border border-gold/20 backdrop-blur-sm"
              >
                <div className="p-2 rounded-xl bg-gold/20">
                  <DollarSign className="w-4 h-4 text-gold" />
                </div>
                <div>
                  <p className="text-[10px] text-white/50 uppercase tracking-wide">Net Revenue</p>
                  <p className="text-sm font-bold text-gold">{formatUGX(rev.net || 0)}</p>
                </div>
              </motion.div>
              <motion.div
                whileHover={{ scale: 1.02, y: -2 }}
                transition={{ duration: 0.2 }}
                className="flex items-center gap-3 px-5 py-3 rounded-2xl bg-white/[0.05] border border-white/[0.08] backdrop-blur-sm"
              >
                <div className="p-2 rounded-xl bg-emerald/20">
                  <TrendingUp className="w-4 h-4 text-emerald" />
                </div>
                <div>
                  <p className="text-[10px] text-white/50 uppercase tracking-wide">Gross</p>
                  <p className="text-sm font-bold text-white">{formatUGX(rev.gross || 0)}</p>
                </div>
              </motion.div>
            </motion.div>
          </div>

          {/* Footer bar */}
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            transition={{ delay: 0.5, duration: 0.5 }}
            className="mt-8 pt-5 border-t border-white/[0.06] flex flex-wrap items-center justify-between gap-3"
          >
            <span className="text-xs text-white/40 flex items-center gap-2">
              <span className="w-2 h-2 rounded-full bg-emerald animate-pulse shadow-[0_0_8px_rgba(52,211,153,0.5)]" />
              Live · Generated {new Date(intelligence.generated_at).toLocaleTimeString()}
            </span>
            <div className="flex gap-2">
              {publicBookingUrl && (
                <Button
                  variant="ghost"
                  size="sm"
                  className="gap-1.5 h-9 text-xs border-gold/30 text-gold hover:bg-gold/10 hover:border-gold/50 transition-all duration-200"
                  onClick={() => setBookingModalOpen(true)}
                >
                  <Briefcase className="w-3.5 h-3.5" />
                  Get Booking Link
                </Button>
              )}
              <Link href="/analytics/intelligence">
                <Button variant="ghost" size="sm" className="gap-1.5 h-9 text-xs hover:bg-white/[0.05] transition-all duration-200">
                  <Brain className="w-3.5 h-3.5" />
                  Full Intelligence
                  <ChevronRight className="w-3 h-3" />
                </Button>
              </Link>
            </div>
          </motion.div>
        </div>
      </motion.section>

      {/* ═══ Live Dashboard Widgets ═══════════════════════════════════════ */}
      <LiveDashboardWidgets />

      {/* ═══ ROW 1: Revenue Chart + Signals ══════════════════════════════ */}
      <div className="grid grid-cols-1 lg:grid-cols-5 gap-5">

        {/* Revenue Area Chart ── spans 3/5 */}
        <motion.section
          initial={{ opacity: 0, y: 12 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.08 }}
          className="lg:col-span-3 rounded-3xl border border-[var(--color-border-light)] bg-[var(--color-surface)] p-6"
          style={{ boxShadow: '0 1px 12px 0 rgba(0,0,0,0.05)' }}
        >
          <div className="flex items-center justify-between mb-5">
            <div>
              <h2 className="text-base font-semibold text-[var(--color-text-primary)]">Revenue Trend</h2>
              <p className="text-xs text-[var(--color-text-secondary)] mt-0.5">Gross vs Net over time</p>
            </div>
            <div className="flex items-center gap-3 text-xs text-[var(--color-text-secondary)]">
              <span className="flex items-center gap-1.5">
                <span className="w-2.5 h-2.5 rounded-full bg-[#6C5CE7]" /> Gross
              </span>
              <span className="flex items-center gap-1.5">
                <span className="w-2.5 h-2.5 rounded-full bg-gold" /> Net
              </span>
            </div>
          </div>

          {revTrend.length > 0 ? (
            <div style={{ height: 200 }}>
              <ResponsiveContainer width="100%" height="100%">
                <AreaChart data={revTrend} margin={{ top: 5, right: 4, left: -16, bottom: 0 }}>
                  <defs>
                    <linearGradient id="gradGross" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="5%"  stopColor="#6C5CE7" stopOpacity={0.3} />
                      <stop offset="95%" stopColor="#6C5CE7" stopOpacity={0} />
                    </linearGradient>
                    <linearGradient id="gradNet" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="5%"  stopColor="#FFD700" stopOpacity={0.3} />
                      <stop offset="95%" stopColor="#FFD700" stopOpacity={0} />
                    </linearGradient>
                  </defs>
                  <CartesianGrid strokeDasharray="3 3" stroke="var(--color-border-medium)" vertical={false} />
                  <XAxis dataKey="date" tick={{ fill: '#888', fontSize: 11 }} axisLine={false} tickLine={false} dy={8} />
                  <YAxis tick={{ fill: '#888', fontSize: 11 }} axisLine={false} tickLine={false}
                    tickFormatter={v => v >= 1000 ? `${(v/1000).toFixed(0)}k` : v} />
                  <Tooltip
                    contentStyle={{ backgroundColor: 'var(--color-card)', borderRadius: 12, border: '1px solid var(--color-border-medium)' }}
                    formatter={(v: any, name: any) => [`UGX ${Number(v).toLocaleString()}`, String(name) === 'revenue' ? 'Gross' : 'Net']}
                    labelStyle={{ color: 'var(--color-text-secondary)', fontSize: 11 }}
                  />
                  <Area type="monotone" dataKey="revenue" stroke="#6C5CE7" strokeWidth={2} fill="url(#gradGross)" name="revenue" />
                  <Area type="monotone" dataKey="net"     stroke="#FFD700" strokeWidth={2} fill="url(#gradNet)"   name="net" />
                </AreaChart>
              </ResponsiveContainer>
            </div>
          ) : (
            <div className="h-[200px] flex flex-col items-center justify-center gap-2 text-[var(--color-text-secondary)]">
              <TrendingUp className="w-8 h-8 opacity-30" />
              <p className="text-sm">Revenue data will appear after your first transactions.</p>
            </div>
          )}
        </motion.section>

        {/* Signals ── spans 2/5 */}
        <motion.section
          initial={{ opacity: 0, y: 12 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.14 }}
          className="lg:col-span-2 rounded-3xl border border-[var(--color-border-light)] bg-[var(--color-surface)] p-6 flex flex-col"
          style={{ boxShadow: '0 1px 12px 0 rgba(0,0,0,0.05)' }}
        >
          <div className="flex items-center justify-between mb-5">
            <h2 className="text-base font-semibold text-[var(--color-text-primary)]">Live Signals</h2>
            <div className="p-1.5 rounded-full bg-gold/10">
              <Zap className="w-4 h-4 text-gold" />
            </div>
          </div>

          <div className="flex-1 space-y-3">
            {topSignals.length > 0 ? topSignals.map((signal: any) => (
              <Link
                key={signal.id}
                href={signal.deep_link || '/analytics/intelligence'}
                className="group flex items-start gap-3 p-3 rounded-2xl border border-transparent hover:border-[var(--color-border-light)] hover:bg-[var(--color-card)] transition-all duration-200"
              >
                <span
                  className="mt-1.5 flex-shrink-0 w-2 h-2 rounded-full"
                  style={{
                    backgroundColor: SIGNAL_COLOR[signal.priority] || '#888',
                    boxShadow: `0 0 8px 1px ${SIGNAL_COLOR[signal.priority] || '#888'}66`
                  }}
                />
                <div className="min-w-0">
                  <p className="text-sm font-medium text-[var(--color-text-primary)] group-hover:text-gold transition-colors leading-snug">
                    {signal.title}
                  </p>
                  {signal.recommended_action && (
                    <p className="text-xs text-[var(--color-text-secondary)] mt-0.5 truncate">
                      {signal.recommended_action}
                    </p>
                  )}
                </div>
                <ChevronRight className="w-3.5 h-3.5 text-[var(--color-text-secondary)] flex-shrink-0 mt-1 opacity-0 group-hover:opacity-100 transition-opacity" />
              </Link>
            )) : (
              <div className="flex-1 flex flex-col items-center justify-center gap-2 py-8 text-center text-[var(--color-text-secondary)]">
                <AlertTriangle className="w-7 h-7 opacity-30" />
                <p className="text-sm">No active signals. All clear!</p>
              </div>
            )}
          </div>

          <Link href="/analytics/intelligence" className="mt-4 pt-4 border-t border-[var(--color-border-light)] flex items-center justify-center gap-1.5 text-xs text-gold hover:underline">
            View all in Intelligence Center <ArrowRight className="w-3.5 h-3.5" />
          </Link>
        </motion.section>
      </div>

      {/* ═══ ROW 2: Bookings Bar Chart + Staff Leaderboard ═══════════════ */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">

        {/* Weekly Bookings Bar Chart */}
        <motion.section
          initial={{ opacity: 0, y: 12 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.2 }}
          className="rounded-3xl border border-[var(--color-border-light)] bg-[var(--color-surface)] p-6"
          style={{ boxShadow: '0 1px 12px 0 rgba(0,0,0,0.05)' }}
        >
          <div className="flex items-center justify-between mb-5">
            <div>
              <h2 className="text-base font-semibold text-[var(--color-text-primary)]">Bookings by Day</h2>
              <p className="text-xs text-[var(--color-text-secondary)] mt-0.5">Weekly activity distribution</p>
            </div>
            <Calendar className="w-5 h-5 text-[var(--color-text-secondary)] opacity-50" />
          </div>

          <div style={{ height: 180 }}>
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={weeklyData} margin={{ top: 4, right: 4, left: -20, bottom: 0 }}>
                <CartesianGrid strokeDasharray="3 3" stroke="var(--color-border-medium)" vertical={false} />
                <XAxis dataKey="day" tick={{ fill: '#888', fontSize: 11 }} axisLine={false} tickLine={false} dy={8} />
                <YAxis tick={{ fill: '#888', fontSize: 11 }} axisLine={false} tickLine={false} allowDecimals={false} />
                <Tooltip
                  contentStyle={{ backgroundColor: 'var(--color-card)', borderRadius: 12, border: '1px solid var(--color-border-medium)' }}
                  formatter={(v: any) => [v, 'Bookings']}
                  labelStyle={{ color: 'var(--color-text-secondary)', fontSize: 11 }}
                />
                <Bar dataKey="bookings" radius={[6, 6, 0, 0]} barSize={28}>
                  {weeklyData.map((entry, i) => (
                    <Cell key={i} fill={entry.isToday ? '#FFD700' : 'rgba(255,215,0,0.35)'} />
                  ))}
                </Bar>
              </BarChart>
            </ResponsiveContainer>
          </div>
        </motion.section>

        {/* Staff Leaderboard */}
        <motion.section
          initial={{ opacity: 0, y: 12 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.26 }}
          className="rounded-3xl border border-[var(--color-border-light)] bg-[var(--color-surface)] p-6"
          style={{ boxShadow: '0 1px 12px 0 rgba(0,0,0,0.05)' }}
        >
          <div className="flex items-center justify-between mb-5">
            <div>
              <h2 className="text-base font-semibold text-[var(--color-text-primary)]">Staff Leaderboard</h2>
              <p className="text-xs text-[var(--color-text-secondary)] mt-0.5">By Intelligence Score</p>
            </div>
            <Users className="w-5 h-5 text-[var(--color-text-secondary)] opacity-50" />
          </div>

          <div className="space-y-3">
            {staffList.length > 0 ? staffList.map((staff: any, i: number) => (
              <div key={staff.id || i} className="flex items-center gap-3">
                {/* Rank */}
                <span className={`flex-shrink-0 w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold ${
                  i === 0 ? 'bg-gold text-obsidian' : 'bg-[var(--color-card)] text-[var(--color-text-secondary)] border border-[var(--color-border-light)]'
                }`}>
                  {i + 1}
                </span>
                {/* Avatar */}
                <div className={`flex-shrink-0 w-9 h-9 rounded-full flex items-center justify-center font-bold text-sm ${
                  i === 0 ? 'bg-gradient-to-br from-gold to-dark-gold text-obsidian shadow-md' : 'bg-[var(--color-card)] text-[var(--color-text-primary)] border border-[var(--color-border-light)]'
                }`}>
                  {staff.name?.charAt(0) ?? '?'}
                </div>
                {/* Name + score bar */}
                <div className="flex-1 min-w-0">
                  <div className="flex items-center justify-between mb-1">
                    <p className="text-sm font-medium text-[var(--color-text-primary)] truncate">{staff.name}</p>
                    <p className="text-xs font-bold text-gold ml-2 flex-shrink-0">{staff.intelligence_score ?? staff.performance_score ?? 0}</p>
                  </div>
                  <div className="h-1.5 bg-[var(--color-card)] rounded-full overflow-hidden">
                    <div
                      className="h-full rounded-full bg-gradient-to-r from-gold to-dark-gold transition-all duration-700"
                      style={{ width: `${Math.min(100, (staff.intelligence_score ?? staff.performance_score ?? 0) / 1.5)}%` }}
                    />
                  </div>
                </div>
              </div>
            )) : (
              <div className="py-8 flex flex-col items-center gap-2 text-center text-[var(--color-text-secondary)]">
                <Users className="w-7 h-7 opacity-30" />
                <p className="text-sm">Add staff to see performance rankings.</p>
              </div>
            )}
          </div>

          {staffList.length > 0 && (
            <Link href="/staff" className="mt-5 pt-4 border-t border-[var(--color-border-light)] flex items-center justify-center gap-1.5 text-xs text-gold hover:underline">
              Manage Staff <ChevronRight className="w-3.5 h-3.5" />
            </Link>
          )}
        </motion.section>
      </div>

      {/* CopyLink Modal */}
      {publicBookingUrl && (
        <CopyLinkModal
          isOpen={bookingModalOpen}
          onClose={() => setBookingModalOpen(false)}
          title="Your Public Booking Link"
          description="Share this link with clients via social media, WhatsApp, or your website so they can book appointments directly."
          link={publicBookingUrl}
        />
      )}
    </div>
  );
}
