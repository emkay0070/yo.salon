'use client';

import { useSpecialistAuth } from '@/contexts/SpecialistAuthContext';
import { useQuery } from '@tanstack/react-query';
import { motion } from 'framer-motion';
import { 
  Calendar, 
  Clock, 
  DollarSign, 
  Star, 
  Users, 
  Coffee,
  Sun,
  Moon,
  CheckCircle2,
  XCircle
} from 'lucide-react';
import { useState, useEffect } from 'react';
import { apiClient } from '@/lib/api-client';

export default function WorkspacePage() {
  const { specialist } = useSpecialistAuth();
  const [greeting, setGreeting] = useState('');
  const [currentTime, setCurrentTime] = useState(new Date());

  useEffect(() => {
    const hour = new Date().getHours();
    if (hour < 12) setGreeting('Good morning');
    else if (hour < 17) setGreeting('Good afternoon');
    else setGreeting('Good evening');

    const timer = setInterval(() => setCurrentTime(new Date()), 60000);
    return () => clearInterval(timer);
  }, []);

  const { data: todayBookings, isLoading: loadingBookings } = useQuery({
    queryKey: ['specialist-today-bookings'],
    queryFn: async () => {
      const data = await apiClient.get('/v1/specialist-portal/bookings?scope=today');
      
      return data.bookings
        .filter((b: any) => b.date)
        .map((b: any) => ({
          id: b.id,
          time: b.time,
          customer: b.customer?.name || 'Walk-in',
          service: b.service?.name || 'Service',
          status: b.status === 'pending_payment' ? 'upcoming' : (b.status === 'confirmed' ? 'upcoming' : b.status)
        }));
    },
    enabled: !!specialist,
  });

  const { data: todayStats, isLoading: loadingStats } = useQuery({
    queryKey: ['specialist-today-stats'],
    queryFn: async () => {
      return await apiClient.get('/v1/specialist-portal/dashboard/stats');
    },
    enabled: !!specialist,
  });

  // Extract today's schedule details from stats
  const todaySchedule = todayStats?.todaySchedule;
  const isWorking = todaySchedule?.is_working ?? false;
  
  const formatTime = (timeString: string) => {
    if (!timeString) return '';
    // timeString is likely HH:mm:ss, let's parse to Date object and format
    const [h, m] = timeString.split(':');
    const d = new Date();
    d.setHours(parseInt(h, 10));
    d.setMinutes(parseInt(m, 10));
    return d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
  };

  const nextBreak = {
    time: todaySchedule?.break_start ? formatTime(todaySchedule.break_start) : 'No breaks',
    duration: (todaySchedule?.break_start && todaySchedule?.break_end) ? 
      `Until ${formatTime(todaySchedule.break_end)}` : '',
  };

  const availability = {
    status: isWorking ? 'Open' : 'Closed',
    closesAt: todaySchedule?.close_time ? formatTime(todaySchedule.close_time) : '',
  };

  return (
    <div className="p-4 sm:p-6 lg:p-8 space-y-6">
      {/* Greeting */}
      <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
      >
        <h1 className="text-2xl sm:text-3xl font-bold text-text-primary">
          {greeting}, {specialist?.name?.split(' ')[0] || 'Specialist'}.
        </h1>
        <p className="text-text-secondary mt-1">
          {currentTime.toLocaleDateString('en-US', { 
            weekday: 'long', 
            month: 'long', 
            day: 'numeric' 
          })}
        </p>
      </motion.div>

      {/* Quick Stats */}
      {loadingStats ? (
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
          {[1, 2, 3, 4].map((i) => (
            <div key={i} className="bg-card border border-border-light rounded-2xl p-4 animate-pulse">
              <div className="h-5 w-20 bg-surface rounded mb-2"></div>
              <div className="h-8 w-16 bg-surface rounded"></div>
            </div>
          ))}
        </div>
      ) : (
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <motion.div
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.1 }}
          className="bg-card border border-border-light rounded-2xl p-4"
        >
          <div className="flex items-center gap-2 mb-2">
            <DollarSign className="w-5 h-5 text-green-400" />
            <span className="text-sm text-text-secondary">Today's Earnings</span>
          </div>
          <p className="text-2xl font-bold text-text-primary">
            UGX {(todayStats?.earnings ?? 0).toLocaleString('en-UG', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}
          </p>
        </motion.div>

        <motion.div
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.2 }}
          className="bg-card border border-border-light rounded-2xl p-4"
        >
          <div className="flex items-center gap-2 mb-2">
            <Star className="w-5 h-5 text-yellow-400" />
            <span className="text-sm text-text-secondary">Today's Rating</span>
          </div>
          <p className="text-2xl font-bold text-text-primary">
            {todayStats?.rating ? Number(todayStats.rating).toFixed(1) : specialist?.rating ? Number(specialist.rating).toFixed(1) : '0.0'}
          </p>
        </motion.div>

        <motion.div
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.3 }}
          className="bg-card border border-border-light rounded-2xl p-4"
        >
          <div className="flex items-center gap-2 mb-2">
            <Users className="w-5 h-5 text-blue-400" />
            <span className="text-sm text-text-secondary">Customers Waiting</span>
          </div>
          <p className="text-2xl font-bold text-text-primary">
            {todayStats?.waitingCustomers || 0}
          </p>
        </motion.div>

        <motion.div
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.4 }}
          className="bg-card border border-border-light rounded-2xl p-4"
        >
          <div className="flex items-center gap-2 mb-2">
            <CheckCircle2 className="w-5 h-5 text-green-400" />
            <span className="text-sm text-text-secondary">Completed</span>
          </div>
          <p className="text-2xl font-bold text-text-primary">
            {todayStats?.completedToday || 0}
          </p>
        </motion.div>
      </div>
      )}

      {/* Today's Schedule */}
      <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.5 }}
        className="bg-card border border-border-light rounded-2xl p-6"
      >
        <h2 className="text-lg font-semibold text-text-primary mb-4 flex items-center gap-2">
          <Calendar className="w-5 h-5 text-gold" />
          Today
        </h2>

        <div className="space-y-3">
          {loadingBookings ? (
            <div className="text-center text-text-secondary py-4 animate-pulse">Loading schedule...</div>
          ) : todayBookings?.length === 0 ? (
            <div className="text-center text-text-secondary py-4">No appointments scheduled for today</div>
          ) : (
            todayBookings?.map((appointment: any, index: number) => (
            <motion.div
              key={index}
              initial={{ opacity: 0, x: -10 }}
              animate={{ opacity: 1, x: 0 }}
              transition={{ delay: 0.6 + index * 0.1 }}
              className="flex items-center justify-between p-4 bg-surface rounded-xl border border-border-light"
            >
              <div className="flex items-center gap-4">
                <div className="text-sm font-mono text-text-secondary w-16">
                  {appointment.time}
                </div>
                <div className="w-12 h-12 rounded-full bg-gradient-to-br from-gold to-amber-600 flex items-center justify-center text-white font-bold">
                  {appointment.customer.charAt(0)}
                </div>
                <div>
                  <p className="font-medium text-text-primary">{appointment.customer}</p>
                  <p className="text-sm text-text-secondary">{appointment.service}</p>
                </div>
              </div>
              <div className="flex items-center gap-2">
                {appointment.status === 'completed' && (
                  <CheckCircle2 className="w-5 h-5 text-green-400" />
                )}
                {appointment.status === 'in-progress' && (
                  <Clock className="w-5 h-5 text-blue-400 animate-pulse" />
                )}
                {appointment.status === 'upcoming' && (
                  <div className="w-5 h-5 rounded-full border-2 border-border-light" />
                )}
              </div>
            </motion.div>
          )))}
        </div>
      </motion.div>

      {/* Next Break & Availability */}
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <motion.div
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.9 }}
          className="bg-card border border-border-light rounded-2xl p-6"
        >
          <div className="flex items-center gap-2 mb-3">
            <Coffee className="w-5 h-5 text-amber-400" />
            <h3 className="font-semibold text-text-primary">Next Break</h3>
          </div>
          <p className="text-2xl font-bold text-text-primary">{nextBreak.time}</p>
          <p className="text-sm text-text-secondary">{nextBreak.duration}</p>
        </motion.div>

        <motion.div
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 1.0 }}
          className="bg-card border border-border-light rounded-2xl p-6"
        >
          <div className="flex items-center gap-2 mb-3">
            {availability.status === 'Open' ? (
              <Sun className="w-5 h-5 text-green-400" />
            ) : (
              <Moon className="w-5 h-5 text-text-secondary" />
            )}
            <h3 className="font-semibold text-text-primary">Availability</h3>
          </div>
          <p className="text-2xl font-bold text-text-primary">{availability.status}</p>
          <p className="text-sm text-text-secondary">
            {availability.status === 'Open' ? `Open until ${availability.closesAt}` : 'Closed'}
          </p>
        </motion.div>
      </div>
    </div>
  );
}
