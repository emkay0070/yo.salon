'use client';

import { motion } from 'framer-motion';
import { DollarSign, Calendar, Users, User, TrendingUp, AlertTriangle, Clock, Star } from 'lucide-react';

import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';
import { useRole } from '@/contexts/RoleContext';

interface OwnerDashboardProps {
  userName: string;
}

export default function OwnerDashboard({ userName }: OwnerDashboardProps) {
  const { salonId } = useRole();
  const todayDate = new Date();
  const formattedDate = todayDate.toLocaleDateString('en-US', { 
    weekday: 'long', 
    day: 'numeric', 
    month: 'long' 
  });

  const greeting = todayDate.getHours() < 12 ? 'Good Morning' : todayDate.getHours() < 18 ? 'Good Afternoon' : 'Good Evening';

  // Real data queries
  const { data: summary } = useQuery({
    queryKey: ['transaction-summary', salonId],
    queryFn: () => apiClient.getTransactionSummary({ salon_id: salonId }),
    enabled: !!salonId,
  });

  const { data: todayBookings } = useQuery({
    queryKey: ['bookings', salonId, 'today'],
    queryFn: () => apiClient.getBookings({ salon_id: salonId, date: 'today' }),
    enabled: !!salonId,
  });

  const { data: customers } = useQuery({
    queryKey: ['customers', salonId],
    queryFn: () => apiClient.getCustomers({ salon_id: salonId }),
    enabled: !!salonId,
  });

  const { data: staff } = useQuery({
    queryKey: ['staff', salonId],
    queryFn: () => apiClient.getStaff({ salon_id: salonId }),
    enabled: !!salonId,
  });

  const stats = {
    revenue: summary?.total_volume || 0,
    appointments: todayBookings?.length || 0,
    customers: customers?.length || 0,
    staffWorking: staff?.filter((s: any) => s.active).length || 0,
    totalStaff: staff?.length || 0
  };

  // Compute hourly booking distribution from real data (hours 9–16)
  const hourlyData = Array.from({ length: 8 }, (_, i) => {
    const hour = 9 + i;
    return (todayBookings as any[])?.filter((b: any) => {
      if (!b.date) return false;
      return new Date(b.date).getHours() === hour;
    }).length || 0;
  });
  const maxBookings = Math.max(...hourlyData, 1);

  // Top staff by today's booking count
  const topStaff = ((staff as any[]) || [])
    .map((s: any) => ({
      name: s.name,
      appointments: (todayBookings as any[])?.filter((b: any) => b.staff_id === s.id).length || 0,
      revenue: (todayBookings as any[])
        ?.filter((b: any) => b.staff_id === s.id)
        .reduce((sum: number, b: any) => sum + (b.service?.price || 0), 0) || 0,
      rating: s.rating || 4.5,
    }))
    .sort((a: any, b: any) => b.appointments - a.appointments)
    .slice(0, 3);

  // Busy services by today's booking count
  const serviceCountMap: Record<string, number> = {};
  ((todayBookings as any[]) || []).forEach((b: any) => {
    const svcName = b.service?.name || 'Unknown';
    serviceCountMap[svcName] = (serviceCountMap[svcName] || 0) + 1;
  });
  const busyServices = Object.entries(serviceCountMap)
    .map(([name, bookings]) => ({ name, bookings }))
    .sort((a, b) => b.bookings - a.bookings)
    .slice(0, 3);

  // Cancelled bookings today for attention panel
  const cancelledToday = ((todayBookings as any[]) || []).filter((b: any) => b.status === 'cancelled').length;

  return (
    <div className="space-y-6">
      {/* ── Cinematic Hero Banner ─────────────────────────────────── */}
      <motion.div
        initial={{ opacity: 0, y: -10 }}
        animate={{ opacity: 1, y: 0 }}
        className="relative overflow-hidden rounded-2xl"
        style={{ minHeight: '220px' }}
      >
        {/* Background image */}
        <div
          className="absolute inset-0 bg-cover bg-center bg-no-repeat scale-105"
          style={{ backgroundImage: `url('/images/salon-luxury.png')` }}
        />
        {/* Gradient overlays */}
        <div className="absolute inset-0 bg-gradient-to-r from-[var(--color-background)]/90 via-[var(--color-background)]/60 to-transparent" />
        <div className="absolute inset-0 bg-gradient-to-t from-[var(--color-background)]/70 via-transparent to-transparent" />
        {/* Gold accent line at bottom */}
        <div className="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-[#FFD700]/60 via-[#FFD700]/20 to-transparent" />

        {/* Content */}
        <div className="relative z-10 p-8 flex flex-col justify-end" style={{ minHeight: '220px' }}>
          <p className="text-gold text-xs font-semibold tracking-[0.2em] uppercase mb-3 opacity-80">
            {greeting}
          </p>
          <h1 className="text-3xl sm:text-4xl font-bold text-text-primary mb-2 tracking-tight leading-tight">
            {userName} 👋
          </h1>
          <p className="text-text-primary/50 text-sm">{formattedDate}</p>
          <p className="text-text-primary/70 mt-3 font-medium">
            {stats.appointments > 0
              ? `${stats.appointments} appointment${stats.appointments !== 1 ? 's' : ''} scheduled today. Let's make it count.`
              : "A quiet day — great for planning or catching up."}
          </p>
        </div>
      </motion.div>


      {/* KPI Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        {[
          { 
            label: "Today's Revenue", 
            value: `UGX ${stats.revenue.toLocaleString()}`, 
            icon: DollarSign, 
            color: 'text-gold',
            bg: 'from-[rgba(255,215,0,0.2)] to-[rgba(255,215,0,0.05)]'
          },
          { 
            label: 'Appointments', 
            value: stats.appointments.toString(), 
            icon: Calendar, 
            color: 'text-gold',
            bg: 'from-[rgba(255,215,0,0.2)] to-[rgba(255,215,0,0.05)]'
          },
          { 
            label: 'Customers', 
            value: stats.customers.toString(), 
            icon: Users, 
            color: 'text-[#2F7A5C]',
            bg: 'from-[rgba(47,122,92,0.2)] to-[rgba(47,122,92,0.05)]'
          },
          { 
            label: 'Staff Working', 
            value: `${stats.staffWorking} / ${stats.totalStaff}`, 
            icon: User, 
            color: 'text-[#FF622B]',
            bg: 'from-[rgba(255,98,43,0.2)] to-[rgba(255,98,43,0.05)]'
          },
        ].map((stat, index) => (
          <motion.div
            key={stat.label}
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: index * 0.1 }}
            className="bg-card border border-border-light rounded-2xl p-4 backdrop-blur-2xl"
          >
            <div className="flex items-center gap-3">
              <div className={`p-2 rounded-xl bg-gradient-to-br ${stat.bg}`}>
                <stat.icon className={`w-5 h-5 ${stat.color}`} />
              </div>
              <div>
                <p className="text-text-secondary text-xs">{stat.label}</p>
                <p className="text-xl font-bold text-text-primary">{stat.value}</p>
              </div>
            </div>
          </motion.div>
        ))}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Revenue Trend */}
        <motion.div
          initial={{ opacity: 0, x: -10 }}
          animate={{ opacity: 1, x: 0 }}
          className="lg:col-span-2 bg-card border border-border-light rounded-2xl p-6 backdrop-blur-2xl"
        >
          <div className="flex items-center justify-between mb-6">
            <h2 className="text-lg font-semibold text-text-primary">This Week</h2>
            <TrendingUp className="w-5 h-5 text-gold" />
          </div>
          
          <div className="flex items-end gap-2 h-32">
            {hourlyData.map((count, index) => (
              <div key={index} className="flex-1 flex flex-col items-center gap-2">
                <div 
                  className="w-full bg-gradient-to-t from-[#FFD700] to-[#C9A227] rounded-t-lg transition-all hover:opacity-80"
                  style={{ height: `${(count / maxBookings) * 100}%` }}
                />
                <span className="text-text-secondary text-xs">{9 + index}:00</span>
              </div>
            ))}
          </div>
        </motion.div>

        {/* Today's Schedule Snapshot */}
        <motion.div
          initial={{ opacity: 0, x: 10 }}
          animate={{ opacity: 1, x: 0 }}
          className="bg-card border border-border-light rounded-2xl p-6 backdrop-blur-2xl"
        >
          <h2 className="text-lg font-semibold text-text-primary mb-6">Today's Schedule</h2>
          
          <div className="space-y-3">
            {hourlyData.map((count, index) => (
              <div key={index} className="flex items-center gap-3">
                <span className="text-text-secondary text-xs w-12">{9 + index}:00</span>
                <div className="flex-1 h-4 bg-white/10 rounded-full overflow-hidden">
                  <div 
                    className="h-full bg-gradient-to-r from-[#FFD700] to-[#C9A227] rounded-full"
                    style={{ width: `${(count / maxBookings) * 100}%` }}
                  />
                </div>
                <span className="text-text-primary text-xs w-6">{count}</span>
              </div>
            ))}
          </div>
          
          <div className="mt-4 pt-4 border-t border-border-light">
            <p className="text-gold font-medium text-center">
              {stats.appointments > 30 ? '🔥 Busy Day' : '😌 Moderate Day'}
            </p>
          </div>
        </motion.div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Top Staff Today */}
        <motion.div
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          className="bg-card border border-border-light rounded-2xl p-6 backdrop-blur-2xl"
        >
          <h2 className="text-lg font-semibold text-text-primary mb-4">Top Staff Today</h2>
          
          <div className="space-y-3">
            {topStaff.map((staff, index) => (
              <div key={staff.name} className="flex items-center gap-4 p-3 rounded-xl bg-surface border border-border-medium">
                <div className="w-10 h-10 rounded-full bg-gradient-to-br from-[#FFD700] to-[#C9A227] flex items-center justify-center">
                  <span className="text-obsidian font-bold">{staff.name[0]}</span>
                </div>
                <div className="flex-1">
                  <div className="flex items-center gap-2">
                    <p className="text-text-primary font-medium">{staff.name}</p>
                    {index === 0 && <span className="text-gold">🔥</span>}
                  </div>
                  <p className="text-text-secondary text-sm">{staff.appointments} appointments</p>
                </div>
                <div className="text-right">
                  <p className="text-text-primary font-semibold">UGX {staff.revenue.toLocaleString()}</p>
                  <div className="flex items-center gap-1">
                    <Star className="w-3 h-3 text-gold fill-[#FFD700]" />
                    <span className="text-text-secondary text-xs">{staff.rating}</span>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </motion.div>

        {/* Busy Services */}
        <motion.div
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          className="bg-card border border-border-light rounded-2xl p-6 backdrop-blur-2xl"
        >
          <h2 className="text-lg font-semibold text-text-primary mb-4">Busy Services</h2>
          
          <div className="space-y-4">
            {busyServices.map((service, index) => (
              <div key={service.name}>
                <div className="flex items-center justify-between mb-2">
                  <p className="text-text-primary font-medium">{service.name}</p>
                  <p className="text-text-secondary text-sm">{service.bookings} bookings</p>
                </div>
                <div className="w-full bg-white/10 rounded-full h-2">
                  <div 
                    className="h-2 bg-gradient-to-r from-[#FFD700] to-[#C9A227] rounded-full"
                    style={{ width: `${(service.bookings / 15) * 100}%` }}
                  />
                </div>
              </div>
            ))}
          </div>
        </motion.div>
      </div>

      {/* Things Needing Attention */}
      <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        className="bg-card border border-border-light rounded-2xl p-6 backdrop-blur-2xl"
      >
        <h2 className="text-lg font-semibold text-text-primary mb-4 flex items-center gap-2">
          <AlertTriangle className="w-5 h-5 text-[#FF622B]" />
          Things Needing Attention
        </h2>

        {cancelledToday === 0 && stats.appointments === 0 ? (
          <div className="relative rounded-xl overflow-hidden border border-border-light p-8 flex flex-col items-center text-center min-h-[200px] justify-center">
            <div className="absolute inset-0 z-0">
              <img src="/images/salon_interior.png" alt="Clear Day" className="w-full h-full object-cover opacity-20" />
              <div className="absolute inset-0 bg-gradient-to-t from-surface via-surface/80 to-transparent"></div>
            </div>
            <div className="relative z-10">
              <p className="text-text-primary font-medium text-lg mb-1">You're all clear ✅</p>
              <p className="text-text-secondary text-sm">No issues flagged for today. Enjoy the smooth operations.</p>
            </div>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            {cancelledToday > 0 && (
              <div className="p-4 rounded-xl bg-red-500/5 border border-red-500/20">
                <div className="flex items-center gap-3">
                  <div className="p-2 rounded-lg bg-red-500/20">
                    <AlertTriangle className="w-4 h-4 text-red-400" />
                  </div>
                  <p className="text-text-primary text-sm">{cancelledToday} cancellation{cancelledToday !== 1 ? 's' : ''} today</p>
                </div>
              </div>
            )}
            {stats.appointments > 0 && (
              <div className="p-4 rounded-xl bg-surface border border-border-medium">
                <div className="flex items-center gap-3">
                  <div className="p-2 rounded-lg bg-blue-500/20">
                    <Clock className="w-4 h-4 text-blue-400" />
                  </div>
                  <p className="text-text-primary text-sm">{stats.appointments} appointment{stats.appointments !== 1 ? 's' : ''} today</p>
                </div>
              </div>
            )}
          </div>
        )}
      </motion.div>
    </div>
  );
}
