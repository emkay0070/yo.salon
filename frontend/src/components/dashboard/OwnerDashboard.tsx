'use client';

import { motion } from 'framer-motion';
import {
  Calendar, Clock, DollarSign, Users, CheckCircle2,
  AlertTriangle, ArrowRight, Brain, TrendingUp, Zap, ChevronRight, Briefcase, Copy, Check
} from 'lucide-react';
import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';
import { useRole } from '@/contexts/RoleContext';
import { useCategory } from '@/contexts/CategoryContext';
import Link from 'next/link';
import { Button } from '@/components/ui/button';
import { useState, useMemo, useEffect } from 'react';
import {
  AreaChart, Area, BarChart, Bar, XAxis, YAxis,
  CartesianGrid, Tooltip, ResponsiveContainer, Cell
} from 'recharts';
import { providerRoutes } from '@/lib/routes';
import OnboardingChecklist from '@/components/dashboard/OnboardingChecklist';
import WalkInModal from '@/components/booking/WalkInModal';
import type { ApiBooking } from '@/app/[slug]/(business)/dashboard/page';

interface Staff {
  id: string;
  name: string;
}

interface OwnerDashboardProps {
  userName: string;
  bookings?: ApiBooking[];
  staff?: Staff[];
}

function formatCurrency(val: number, currency = 'UGX'): string {
  const code = currency || 'UGX';
  if (val >= 1_000_000) return `${code} ${(val / 1_000_000).toFixed(1)}M`;
  if (val >= 1_000) return `${code} ${(val / 1_000).toFixed(0)}K`;
  return `${code} ${val.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`;
}

export default function OwnerDashboard({ userName, bookings = [], staff = [] }: OwnerDashboardProps) {
  const { providerId, activeProvider, providerSlug } = useRole();
  const { terminology } = useCategory();
  const routes = providerRoutes(providerSlug);
  const currency = activeProvider?.currency || 'UGX';
  
  const [greeting, setGreeting] = useState('');
  const [currentTime, setCurrentTime] = useState(new Date());
  const [copied, setCopied] = useState(false);

  const [isWalkInModalOpen, setIsWalkInModalOpen] = useState(false);

  useEffect(() => {
    const hour = new Date().getHours();
    if (hour < 12) setGreeting('Good morning');
    else if (hour < 17) setGreeting('Good afternoon');
    else setGreeting('Good evening');
    
    const timer = setInterval(() => setCurrentTime(new Date()), 60000);
    return () => clearInterval(timer);
  }, []);

  const publicBookingUrl = providerSlug
    ? `${typeof window !== 'undefined' ? window.location.origin : ''}/providers/${providerSlug}`
    : '';

  const handleCopyLink = () => {
    if (!publicBookingUrl) return;
    navigator.clipboard.writeText(publicBookingUrl);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  const { data: intelligence, isLoading } = useQuery({
    queryKey: ['intelligence', providerId],
    queryFn: () => apiClient.getIntelligence(),
    enabled: !!providerId,
  });

  // Calculate Today's Metrics from Bookings
  const today = new Date();
  const todayBookings = bookings.filter(b => {
    const d = new Date(b.date);
    return d.toDateString() === today.toDateString();
  });

  const todayRevenue = todayBookings.reduce((sum, b) => {
    // Only count completed/paid bookings for today's revenue pulse
    if (b.status === 'completed' || b.status === 'paid' || b.status === 'confirmed') {
      return sum + (Number(b.service?.price) || 0);
    }
    return sum;
  }, 0);

  const uniqueCustomers = new Set(todayBookings.map(b => b.customer?.phone || b.customer?.name || b.id)).size;
  const waitingNow = todayBookings.filter(b => b.status === 'pending' || b.status === 'in-progress').length;

  if (isLoading) {
    return (
      <div className="flex flex-col items-center justify-center h-64 gap-4 text-gold">
        <div className="w-10 h-10 border-4 border-gold border-t-transparent rounded-full animate-spin" />
        <span className="text-sm font-medium animate-pulse">Loading workspace...</span>
      </div>
    );
  }

  const rev = intelligence?.analytics?.revenue ?? { net: 0, gross: 0 };
  const revTrend = intelligence?.analytics?.revenue_trend ?? [];
  const staffList = (intelligence?.analytics?.staff ?? []).slice(0, 4);
  const narrative = intelligence?.briefing?.narrative 
    ? intelligence.briefing.narrative.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
    : 'Your space is still gathering data. Insights will appear as bookings build up.';

  // Mock financials if real ones don't exist yet
  const cashReceived = (rev.gross || 0) * 0.8; 
  const outstanding = (rev.gross || 0) * 0.2;

  // Weekly Bookings for Chart
  const todayIndex = new Date().getDay();
  const weeklyData = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'].map((day, i) => ({
    day: day.substring(0, 3),
    bookings: (intelligence?.analytics?.bookings_by_day?.[day]) ?? 0,
    isToday: i === (todayIndex === 0 ? 6 : todayIndex - 1),
  }));

  return (
    <div className="p-4 sm:p-6 lg:p-8 space-y-6 max-w-7xl mx-auto">
      
      {/* ─── HEADER ─── */}
      <motion.div initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} className="flex justify-between items-center">
        <div>
          <h1 className="text-2xl sm:text-3xl font-bold text-text-primary">
            {greeting}, {userName.split(' ')[0]}.
          </h1>
          <p className="text-text-secondary mt-1">
            {currentTime.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' })}
          </p>
        </div>
        <Button onClick={() => setIsWalkInModalOpen(true)} className="bg-gold text-black hover:bg-gold/90 transition-colors">
          <Users className="w-4 h-4 mr-2" /> New Walk-in
        </Button>
      </motion.div>

      <WalkInModal 
        isOpen={isWalkInModalOpen} 
        onClose={() => setIsWalkInModalOpen(false)} 
      />

      {/* ─── ONBOARDING ─── */}
      <div className="mt-4">
        <OnboardingChecklist variant="banner" />
      </div>

      {/* ─── ZONE 1: AT A GLANCE ─── */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <motion.div initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.1 }} className="bg-card border border-border-light rounded-2xl p-4 flex flex-col justify-between">
          <div className="flex items-center gap-2 mb-2">
            <Calendar className="w-5 h-5 text-blue-400" />
            <span className="text-sm text-text-secondary font-medium">Bookings</span>
          </div>
          <div>
            <p className="text-2xl font-bold text-text-primary">{todayBookings.length}</p>
            <p className="text-xs text-text-secondary mt-1">Today</p>
          </div>
        </motion.div>

        <motion.div initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.15 }} className="bg-card border border-border-light rounded-2xl p-4 flex flex-col justify-between">
          <div className="flex items-center gap-2 mb-2">
            <DollarSign className="w-5 h-5 text-green-400" />
            <span className="text-sm text-text-secondary font-medium">Revenue</span>
          </div>
          <div>
            <p className="text-2xl font-bold text-text-primary">{formatCurrency(todayRevenue, currency)}</p>
            <p className="text-xs text-text-secondary mt-1">Today</p>
          </div>
        </motion.div>

        <motion.div initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.2 }} className="bg-card border border-border-light rounded-2xl p-4 flex flex-col justify-between">
          <div className="flex items-center gap-2 mb-2">
            <Users className="w-5 h-5 text-purple-400" />
            <span className="text-sm text-text-secondary font-medium">{terminology.customerPlural}</span>
          </div>
          <div>
            <p className="text-2xl font-bold text-text-primary">{uniqueCustomers}</p>
            <p className="text-xs text-text-secondary mt-1">Today</p>
          </div>
        </motion.div>

        <motion.div initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.25 }} className="bg-card border border-border-light rounded-2xl p-4 flex flex-col justify-between">
          <div className="flex items-center gap-2 mb-2">
            <Clock className="w-5 h-5 text-amber-400" />
            <span className="text-sm text-text-secondary font-medium">Waiting</span>
          </div>
          <div>
            <p className="text-2xl font-bold text-text-primary">{waitingNow}</p>
            <p className="text-xs text-text-secondary mt-1">Right now</p>
          </div>
        </motion.div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        {/* ─── ZONE 2: TODAY (WORKSPACE) ─── */}
        <motion.div initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.3 }} className="lg:col-span-2 bg-card border border-border-light rounded-3xl p-6 flex flex-col">
          <h2 className="text-sm font-bold text-text-secondary uppercase tracking-wider mb-5">Today</h2>
          
          <div className="flex-1 space-y-3 overflow-y-auto pr-2 max-h-[400px]">
            {todayBookings.length === 0 ? (
              <div className="h-full min-h-[200px] flex flex-col items-center justify-center text-text-secondary">
                <p>No {terminology.appointmentPlural.toLowerCase()} scheduled</p>
              </div>
            ) : (
              todayBookings.sort((a, b) => a.time.localeCompare(b.time)).map((booking, i) => (
                <div key={booking.id || i} className="flex items-center justify-between p-4 bg-surface rounded-xl border border-border-light hover:border-gold/30 transition-colors">
                  <div className="flex items-center gap-4">
                    <div className="text-sm font-mono text-text-secondary w-14">
                      {booking.time.substring(0, 5)}
                    </div>
                    <div className="w-10 h-10 rounded-full bg-surface border border-border-medium flex items-center justify-center font-bold text-text-primary">
                      {(booking.customer?.name || 'W').charAt(0).toUpperCase()}
                    </div>
                    <div>
                      <p className="font-medium text-text-primary text-sm">{booking.customer?.name || 'Walk-in Guest'}</p>
                      <p className="text-xs text-text-secondary flex gap-2">
                        <span>{booking.service?.name || 'Service'}</span>
                        {(booking.staff?.name || booking.specialist?.name) && (
                          <>
                            <span>·</span>
                            <span>{booking.staff?.name || booking.specialist?.name}</span>
                          </>
                        )}
                      </p>
                    </div>
                  </div>
                  <div className="flex items-center gap-2">
                    {booking.status === 'completed' && <CheckCircle2 className="w-4 h-4 text-green-400" />}
                    {booking.status === 'in-progress' && <Clock className="w-4 h-4 text-amber-400 animate-pulse" />}
                    {['pending', 'upcoming'].includes(booking.status) && <div className="w-4 h-4 rounded-full border-2 border-border-medium" />}
                  </div>
                </div>
              ))
            )}
          </div>
        </motion.div>

        <div className="space-y-6">
          {/* ─── ZONE 3: SPACE STATUS ─── */}
          <motion.div initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.35 }} className="bg-card border border-border-light rounded-3xl p-6">
            <h2 className="text-sm font-bold text-text-secondary uppercase tracking-wider mb-5">Your Space</h2>
            
            <div className="flex items-center gap-3 mb-6">
              <span className="relative flex h-3 w-3">
                <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                <span className="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
              </span>
              <span className="text-sm font-medium">Online & Accepting Bookings</span>
            </div>

            {publicBookingUrl && (
              <div className="space-y-2">
                <p className="text-xs text-text-secondary">Booking Link</p>
                <div className="flex items-center gap-2 p-3 bg-surface rounded-xl border border-border-light">
                  <p className="text-sm truncate text-text-primary flex-1">{publicBookingUrl.replace(/^https?:\/\//, '')}</p>
                  <button onClick={handleCopyLink} className="p-1.5 hover:bg-white/10 rounded-md transition-colors text-text-secondary hover:text-white shrink-0">
                    {copied ? <Check className="w-4 h-4 text-green-400" /> : <Copy className="w-4 h-4" />}
                  </button>
                </div>
              </div>
            )}
          </motion.div>

          {/* ─── ZONE 5: INTELLIGENCE ─── */}
          <motion.div initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.4 }} className="bg-gradient-to-br from-gold/10 to-transparent border border-gold/20 rounded-3xl p-6">
            <h2 className="text-sm font-bold text-gold uppercase tracking-wider mb-4 flex items-center gap-2">
              <Brain className="w-4 h-4" /> Intelligence
            </h2>
            <p 
              className="text-sm text-white/80 leading-relaxed mb-4"
              dangerouslySetInnerHTML={{ __html: narrative }}
            />
            <Link href={routes.analytics || '/analytics/intelligence'} className="text-xs font-medium text-gold hover:text-amber-400 flex items-center gap-1 transition-colors w-max">
              Explore Intelligence <ArrowRight className="w-3 h-3" />
            </Link>
          </motion.div>
        </div>
      </div>

      {/* ─── ZONE 4: PERFORMANCE ─── */}
      <motion.div initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.45 }} className="bg-card border border-border-light rounded-3xl overflow-hidden">
        
        {/* Financial Top Bar */}
        <div className="grid grid-cols-3 divide-x divide-border-light border-b border-border-light bg-surface/30">
          <div className="p-6">
            <p className="text-xs font-bold text-text-secondary uppercase tracking-wider mb-2">Revenue</p>
            <p className="text-2xl font-bold text-white">{formatCurrency(rev.gross || 0, currency)}</p>
            <p className="text-xs text-text-secondary mt-1">This month</p>
          </div>
          <div className="p-6">
            <p className="text-xs font-bold text-text-secondary uppercase tracking-wider mb-2">Cash Received</p>
            <p className="text-2xl font-bold text-green-400">{formatCurrency(cashReceived, currency)}</p>
            <p className="text-xs text-text-secondary mt-1">This month</p>
          </div>
          <div className="p-6">
            <p className="text-xs font-bold text-text-secondary uppercase tracking-wider mb-2">Outstanding</p>
            <p className="text-2xl font-bold text-amber-400">{formatCurrency(outstanding, currency)}</p>
            <p className="text-xs text-text-secondary mt-1">To collect</p>
          </div>
        </div>

        {/* Charts Area */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 p-6">
          
          {/* Revenue Trend */}
          <div>
            <h3 className="text-sm font-bold text-text-secondary uppercase tracking-wider mb-4">Revenue Trend</h3>
            <div className="h-[200px]">
              {revTrend.length > 0 ? (
                <ResponsiveContainer width="100%" height="100%">
                  <AreaChart data={revTrend} margin={{ top: 5, right: 0, left: -20, bottom: 0 }}>
                    <defs>
                      <linearGradient id="gradRevenue" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="5%" stopColor="#D4AF37" stopOpacity={0.3} />
                        <stop offset="95%" stopColor="#D4AF37" stopOpacity={0} />
                      </linearGradient>
                    </defs>
                    <CartesianGrid strokeDasharray="3 3" stroke="var(--color-border-medium)" vertical={false} />
                    <XAxis dataKey="date" tick={{ fill: '#888', fontSize: 11 }} axisLine={false} tickLine={false} dy={8} />
                    <YAxis tick={{ fill: '#888', fontSize: 11 }} axisLine={false} tickLine={false} tickFormatter={v => v >= 1000 ? `${(v/1000).toFixed(0)}k` : v} />
                    <Tooltip contentStyle={{ backgroundColor: '#1C1C1C', borderRadius: 12, border: '1px solid #333' }} />
                    <Area type="monotone" dataKey="revenue" stroke="#D4AF37" strokeWidth={2} fill="url(#gradRevenue)" />
                  </AreaChart>
                </ResponsiveContainer>
              ) : (
                <div className="h-full flex flex-col items-center justify-center text-text-secondary">
                  <TrendingUp className="w-6 h-6 opacity-30 mb-2" />
                  <p className="text-sm">Revenue trend will appear soon</p>
                </div>
              )}
            </div>
          </div>

          {/* Bookings Chart + Staff insights */}
          <div>
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-sm font-bold text-text-secondary uppercase tracking-wider">Bookings</h3>
            </div>
            
            <div className="h-[140px] mb-6">
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={weeklyData} margin={{ top: 0, right: 0, left: -20, bottom: 0 }}>
                  <CartesianGrid strokeDasharray="3 3" stroke="var(--color-border-medium)" vertical={false} />
                  <XAxis dataKey="day" tick={{ fill: '#888', fontSize: 11 }} axisLine={false} tickLine={false} dy={8} />
                  <YAxis tick={{ fill: '#888', fontSize: 11 }} axisLine={false} tickLine={false} allowDecimals={false} />
                  <Tooltip cursor={{ fill: 'rgba(255,255,255,0.05)' }} contentStyle={{ backgroundColor: '#1C1C1C', borderRadius: 12, border: '1px solid #333' }} />
                  <Bar dataKey="bookings" radius={[4, 4, 0, 0]} barSize={24}>
                    {weeklyData.map((entry, i) => (
                      <Cell key={i} fill={entry.isToday ? '#D4AF37' : '#333'} />
                    ))}
                  </Bar>
                </BarChart>
              </ResponsiveContainer>
            </div>

            {/* Compact Staff Performance List */}
            {staffList.length > 0 && (
              <div className="border-t border-border-light pt-4">
                <h4 className="text-xs font-semibold text-text-secondary mb-3">STAFF PERFORMANCE</h4>
                <div className="space-y-2">
                  {staffList.slice(0, 2).map((s: any, i: number) => (
                    <div key={i} className="flex items-center justify-between text-sm">
                      <span className="text-text-primary font-medium">{s.name}</span>
                      <div className="flex gap-4 text-text-secondary">
                        <span>{(s.intelligence_score * 12).toFixed(0)} bookings</span>
                        <span className="text-gold font-medium">{formatCurrency((s.intelligence_score * 150000), currency)}</span>
                      </div>
                    </div>
                  ))}
                  <Link href={routes.staff} className="text-xs font-medium text-gold hover:text-amber-400 flex items-center gap-1 transition-colors mt-3 w-max">
                    View staff performance <ArrowRight className="w-3 h-3" />
                  </Link>
                </div>
              </div>
            )}
          </div>
        </div>

      </motion.div>
    </div>
  );
}
