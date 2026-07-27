'use client';

import { useState, useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import { motion, AnimatePresence } from 'framer-motion';
import { Activity, Clock, Check, AlertTriangle, User, UserCheck, Star } from 'lucide-react';
import { apiClient } from '@/lib/api-client';
import { useRole } from '@/contexts/RoleContext';
import DashboardLayout from '@/components/DashboardLayout';

export default function PulsePage() {
  const { salonId } = useRole();
  const [now, setNow] = useState(new Date());

  // Clock updates every second
  useEffect(() => {
    const interval = setInterval(() => {
      setNow(new Date());
    }, 1000);
    return () => clearInterval(interval);
  }, []);

  // Fetch operational data every 30 seconds
  const { data, isLoading, error } = useQuery({
    queryKey: ['pulse', salonId],
    queryFn: () => apiClient.getPulseData({ salon_id: salonId || undefined }),
    enabled: !!salonId,
    refetchInterval: 30000,
  });

  if (isLoading) {
    return (
      <DashboardLayout>
        <div className="flex h-full items-center justify-center">
          <div className="w-8 h-8 border-2 border-gold/30 border-t-gold rounded-full animate-spin" />
        </div>
      </DashboardLayout>
    );
  }

  if (error || !data) {
    return (
      <DashboardLayout>
        <div className="flex h-full items-center justify-center text-red-400">
          Failed to load Pulse data.
        </div>
      </DashboardLayout>
    );
  }

  const { capacity, stations, nextAppointment, appointmentsRemaining, timeline, notifications, totalBookingsToday } = data;

  const formattedTime = now.toLocaleTimeString('en-US', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: true,
  });

  return (
    <DashboardLayout>
      <div className="max-w-[1200px] mx-auto pb-20 px-4">
        
        {/* Header Section */}
        <div className="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-10">
          <div>
            <h1 className="text-4xl md:text-5xl font-bold tracking-tight text-[var(--color-text-primary)] flex items-center gap-4 mb-2">
              {formattedTime}
              <div className="w-3 h-3 rounded-full bg-emerald-500 animate-pulse border-2 border-emerald-500/20 shadow-[0_0_10px_rgba(16,185,129,0.5)]" />
            </h1>
            <div className="flex items-center gap-3">
              <span className="text-gold font-bold tracking-[0.2em] uppercase text-sm">Today</span>
              <span className="w-1.5 h-1.5 rounded-full bg-[var(--color-text-muted)]/20" />
              <span className="text-[var(--color-text-secondary)]">{totalBookingsToday} Appointments</span>
            </div>
          </div>
 
          {/* Current Atmosphere Widget */}
          <div className="bg-[var(--color-card)] border border-[var(--color-border-light)] rounded-2xl p-5 md:w-80 shadow-md">
            <p className="text-[var(--color-text-secondary)] text-[10px] tracking-widest font-semibold uppercase mb-3">Current Atmosphere</p>
            <div className="flex justify-between items-end mb-2">
              <p className="text-2xl font-bold text-[var(--color-text-primary)]">{capacity.label}</p>
              <p className="text-gold font-semibold">{capacity.percentage}%</p>
            </div>
            
            {/* Minimalist Capacity Bar */}
            <div className="flex gap-1 h-2 mb-3">
              {Array.from({ length: 10 }).map((_, i) => (
                <div 
                  key={i} 
                  className={`flex-1 rounded-sm ${i < Math.round(capacity.percentage / 10) ? 'bg-gold' : 'bg-[var(--color-text-muted)]/20'}`} 
                />
              ))}
            </div>
            
            <p className="text-xs text-[var(--color-text-secondary)]">{capacity.busy} of {capacity.total} Chairs Active</p>
          </div>
        </div>
 
        {/* Main Operational Grid */}
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-8">
          
          {/* Left Column: Queue & Notifications */}
          <div className="lg:col-span-4 flex flex-col gap-8">
            
            {/* Next Up Hero Widget */}
            <div className="bg-gradient-to-b from-[var(--color-card)] to-[var(--color-surface)] border border-[var(--color-border-medium)] rounded-3xl p-6 shadow-lg relative overflow-hidden group">
              <div className="absolute inset-0 bg-gold/5 opacity-0 group-hover:opacity-100 transition-opacity duration-700" />
              
              <div className="relative z-10">
                <p className="text-gold text-[10px] tracking-[0.25em] font-bold uppercase mb-6 flex items-center gap-2">
                  <Activity className="w-3 h-3" /> Next Up
                </p>
                
                {nextAppointment ? (
                  <>
                    <h2 className="text-3xl font-bold text-[var(--color-text-primary)] mb-1">{nextAppointment.time}</h2>
                    <p className="text-xl font-semibold text-[var(--color-text-primary)] mb-1">{nextAppointment.customer_name}</p>
                    <p className="text-[var(--color-text-secondary)] text-sm mb-6">{nextAppointment.service_name} • {nextAppointment.staff_name}</p>
                    
                    <div className="space-y-2">
                      <div className="flex justify-between text-xs font-medium">
                        <span className="text-[var(--color-text-secondary)]">Arrival</span>
                        <span className="text-gold">in {nextAppointment.minutes_until} mins</span>
                      </div>
                      <div className="w-full bg-[var(--color-border-light)] rounded-full h-1 overflow-hidden">
                        <motion.div 
                          className="h-full bg-gradient-to-r from-gold to-[#FF8C00]"
                          initial={{ width: 0 }}
                          animate={{ width: `${Math.max(10, 100 - (nextAppointment.minutes_until / 60) * 100)}%` }}
                          transition={{ duration: 1 }}
                        />
                      </div>
                    </div>
                  </>
                ) : (
                  <div className="py-10 text-center">
                    <p className="text-[var(--color-text-secondary)]">No upcoming appointments</p>
                  </div>
                )}
              </div>
            </div>
 
            {/* Queue Timeline */}
            <div className="bg-[var(--color-card)] border border-[var(--color-border-light)] rounded-3xl p-6 shadow-sm">
              <p className="text-[var(--color-text-secondary)] text-[10px] tracking-widest font-semibold uppercase mb-6">Queue Timeline</p>
              <div className="space-y-4">
                {timeline.map((t: any, i: number) => {
                  let colorClass = 'bg-[var(--color-text-muted)]/25';
                  if (t.status === 'completed') colorClass = 'bg-emerald-500/20 border-emerald-500/30 text-emerald-400';
                  if (t.status === 'in_progress') colorClass = 'bg-blue-500/20 border-blue-500/30 text-blue-400';
                  if (t.status === 'checking_in') colorClass = 'bg-gold/20 border-gold/30 text-gold';
                  if (t.status === 'waiting') colorClass = 'bg-[var(--color-surface)] border-[var(--color-border-light)] text-[var(--color-text-secondary)]';
                  
                  return (
                    <div key={i} className="flex items-center gap-4">
                      <span className="text-xs font-mono text-[var(--color-text-secondary)] w-10 shrink-0">{t.time}</span>
                      <div className={`h-2.5 w-6 rounded-sm shrink-0 border ${colorClass}`} />
                      <div className="flex flex-col truncate">
                        <span className={`text-sm truncate ${t.status === 'waiting' ? 'text-[var(--color-text-secondary)]' : 'text-[var(--color-text-primary)] font-medium'}`}>
                          {t.customer_name}
                        </span>
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>
 
          </div>
 
          {/* Right Column: Stations & Notifications */}
          <div className="lg:col-span-8 flex flex-col gap-8">
            
            {/* Stations Grid */}
            <div className="bg-[var(--color-card)] border border-[var(--color-border-light)] rounded-3xl p-6 md:p-8 shadow-sm">
              <div className="flex justify-between items-center mb-8">
                <p className="text-[var(--color-text-secondary)] text-[10px] tracking-widest font-semibold uppercase">Active Stations</p>
                <div className="flex gap-4">
                  <div className="flex items-center gap-2">
                    <div className="w-2 h-2 rounded-full bg-blue-500 shadow-[0_0_8px_rgba(59,130,246,0.5)]" />
                    <span className="text-[10px] uppercase tracking-wider text-[var(--color-text-secondary)]">Busy</span>
                  </div>
                  <div className="flex items-center gap-2">
                    <div className="w-2 h-2 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.5)]" />
                    <span className="text-[10px] uppercase tracking-wider text-[var(--color-text-secondary)]">Available</span>
                  </div>
                </div>
              </div>
 
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {stations.map((station: any, i: number) => {
                  const isBusy = station.status === 'Busy';
                  return (
                    <div 
                      key={i} 
                      className={`relative p-5 rounded-2xl border transition-all duration-300 overflow-hidden ${
                        isBusy 
                          ? 'bg-blue-500/5 border-blue-500/20' 
                          : 'bg-[var(--color-surface)] border-[var(--color-border-light)]'
                      }`}
                    >
                      {isBusy && (
                        <div className="absolute top-0 right-0 w-32 h-32 bg-blue-500/10 rounded-full blur-3xl -mr-10 -mt-10 pointer-events-none" />
                      )}
                      
                      <div className="flex justify-between items-start mb-4 relative z-10">
                        <div>
                          <p className="text-xs text-[var(--color-text-secondary)] font-semibold mb-1">{station.chair}</p>
                          <p className="text-lg font-bold text-[var(--color-text-primary)]">{station.staff_name}</p>
                        </div>
                        <div className={`w-2.5 h-2.5 rounded-full ${isBusy ? 'bg-blue-500 animate-pulse' : 'bg-emerald-500'}`} />
                      </div>
 
                      {isBusy ? (
                        <div className="space-y-3 relative z-10">
                          <div>
                            <p className="text-sm font-medium text-[var(--color-text-primary)]">{station.customer_name}</p>
                            <p className="text-xs text-[var(--color-text-secondary)] truncate">{station.service_name}</p>
                          </div>
                          
                          <div className="flex items-center gap-2 pt-2 border-t border-[var(--color-border-light)]">
                            <Clock className="w-3.5 h-3.5 text-blue-500" />
                            <span className="text-xs font-semibold text-blue-500">{station.minutes_remaining} min left</span>
                          </div>
                        </div>
                      ) : (
                        <div className="mt-8 relative z-10">
                          <p className="text-xs font-medium text-emerald-600 bg-emerald-500/10 dark:text-emerald-400/80 px-3 py-1.5 rounded-full inline-block border border-emerald-500/10">
                            Available Now
                          </p>
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>
            </div>
 
            {/* Inferred Notifications Activity Feed */}
            <div className="bg-[var(--color-card)] border border-[var(--color-border-light)] rounded-3xl p-6 md:p-8 shadow-sm">
              <div className="flex justify-between items-center mb-6">
                <p className="text-[var(--color-text-secondary)] text-[10px] tracking-widest font-semibold uppercase">Live Activity</p>
              </div>
              
              <div className="space-y-4">
                <AnimatePresence>
                  {notifications.map((note: any) => {
                    let Icon = Check;
                    let color = 'text-emerald-500';
                    let bg = 'bg-emerald-500/10';
                    let border = 'border-emerald-500/20';
                    
                    if (note.type === 'warning') {
                      Icon = AlertTriangle;
                      color = 'text-amber-500';
                      bg = 'bg-amber-500/10';
                      border = 'border-amber-500/20';
                    } else if (note.type === 'info') {
                      Icon = UserCheck;
                      color = 'text-blue-500';
                      bg = 'bg-blue-500/10';
                      border = 'border-blue-500/20';
                    }
 
                    return (
                      <motion.div 
                        key={note.id}
                        initial={{ opacity: 0, x: -20 }}
                        animate={{ opacity: 1, x: 0 }}
                        className={`flex items-center gap-4 p-4 rounded-2xl border ${bg} ${border}`}
                      >
                        <div className={`p-2 rounded-xl bg-[var(--color-surface)] shadow-sm shrink-0`}>
                          <Icon className={`w-4 h-4 ${color}`} />
                        </div>
                        <p className="text-sm text-[var(--color-text-primary)] font-medium flex-1">{note.message}</p>
                        <span className="text-xs text-[var(--color-text-secondary)] whitespace-nowrap">{note.time}</span>
                      </motion.div>
                    );
                  })}
                </AnimatePresence>
              </div>
            </div>
 
          </div>
        </div>
      </div>
    </DashboardLayout>
  );
}
