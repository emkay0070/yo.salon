'use client';

import { useSpecialistAuth } from '@/contexts/SpecialistAuthContext';
import { useQuery, useQueryClient } from '@tanstack/react-query';
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
import { specialistWorkspaceApi } from '@/services/specialist/api/workspace';
import { BreakWidget, SpecialistStatusPill, type SpecialistFloorState } from '@/components/specialist/BreakWidget';

export default function WorkspacePage() {
  const { specialist, currentWorkplace } = useSpecialistAuth();
  const queryClient = useQueryClient();
  const [greeting, setGreeting] = useState('');
  const [currentTime, setCurrentTime] = useState(new Date());
  const [floorState, setFloorState] = useState<SpecialistFloorState>({ status: 'AVAILABLE', expected_end_at: null });

  useEffect(() => {
    const hour = new Date().getHours();
    if (hour < 12) setGreeting('Good morning');
    else if (hour < 17) setGreeting('Good afternoon');
    else setGreeting('Good evening');

    const timer = setInterval(() => setCurrentTime(new Date()), 60000);
    return () => clearInterval(timer);
  }, []);

  // Poll for no-show bookings every 2 minutes
  useEffect(() => {
    if (!specialist) return;

    const pollInterval = setInterval(async () => {
      try {
        await specialistWorkspaceApi.checkAppointmentsNeedingAttention();
        // Refetch bookings to update UI with any no-show status changes
        queryClient.invalidateQueries({ queryKey: ['specialist-today-bookings'] });
      } catch (error) {
        console.error('Failed to check appointments needing attention:', error);
      }
    }, 120000); // 2 minutes

    return () => clearInterval(pollInterval);
  }, [specialist, queryClient]);

  const { data: todayBookings, isLoading: loadingBookings } = useQuery({
    queryKey: ['specialist-today-bookings'],
    queryFn: async () => {
      const data = await apiClient.get('/v1/specialist-portal/bookings?scope=today');
      
      return data.bookings
        .filter((b: any) => b.date)
        .map((b: any) => {
          // Get the first booking_service (single service bookings)
          const bookingService = b.booking_services?.[0];
          const sessionStatus = bookingService?.session_status || null;
          const sessionStartedAt = bookingService?.session_started_at || null;
          
          return {
            id: b.id,
            bookingServiceId: bookingService?.id || null,
            time: b.time,
            customer: b.customer?.name || 'Walk-in',
            service: b.service?.name || 'Service',
            bookingStatus: b.status,
            sessionStatus: sessionStatus,
            sessionStartedAt: sessionStartedAt,
            // Determine display status based on session state
            status: sessionStatus === 'in_progress' ? 'in-progress' :
                    sessionStatus === 'completed' ? 'completed' :
                    sessionStatus === 'cancelled' ? 'cancelled' :
                    b.status === 'cancelled' ? 'cancelled' :
                    b.status === 'pending_payment' ? 'upcoming' :
                    (b.status === 'confirmed' ? 'upcoming' : b.status)
          };
        });
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

  const getSessionDuration = (startedAt: string) => {
    if (!startedAt) return null;
    const start = new Date(startedAt);
    const now = new Date();
    const diffMs = now.getTime() - start.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    return `${diffMins} min`;
  };

  const handleStartSession = async (bookingServiceId: string) => {
    try {
      await apiClient.post(`/v1/specialist-portal/sessions/${bookingServiceId}/start`);
      queryClient.invalidateQueries({ queryKey: ['specialist-today-bookings'] });
    } catch (error) {
      console.error('Failed to start session:', error);
    }
  };

  const handleCompleteSession = async (bookingServiceId: string) => {
    try {
      await apiClient.post(`/v1/specialist-portal/sessions/${bookingServiceId}/complete`);
      queryClient.invalidateQueries({ queryKey: ['specialist-today-bookings'] });
    } catch (error) {
      console.error('Failed to complete session:', error);
    }
  };

  const handleCancelSession = async (bookingServiceId: string) => {
    try {
      await apiClient.post(`/v1/specialist-portal/sessions/${bookingServiceId}/cancel`);
      queryClient.invalidateQueries({ queryKey: ['specialist-today-bookings'] });
    } catch (error) {
      console.error('Failed to cancel session:', error);
    }
  };

  const isAppointmentTimeDue = (appointmentDate: string, appointmentTime: string) => {
    if (!appointmentDate || !appointmentTime) return false;
    
    const now = new Date();
    const appointmentDateTime = new Date(`${appointmentDate}T${appointmentTime}`);
    
    // Allow starting 5 minutes before the scheduled time
    const fiveMinutesBefore = new Date(appointmentDateTime.getTime() - 5 * 60000);
    
    return now >= fiveMinutesBefore;
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
              className={`flex items-center justify-between p-4 rounded-xl border ${
                appointment.status === 'cancelled' 
                  ? 'bg-surface/50 border-border-light opacity-60' 
                  : 'bg-surface border-border-light'
              }`}
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
                  {appointment.status === 'in-progress' && appointment.sessionStartedAt && (
                    <p className="text-xs text-blue-400 font-mono mt-1">
                      In session · {getSessionDuration(appointment.sessionStartedAt)}
                    </p>
                  )}
                  {appointment.status === 'cancelled' && (
                    <p className="text-xs text-red-400 mt-1">Cancelled</p>
                  )}
                </div>
              </div>
              <div className="flex items-center gap-2">
                {appointment.status === 'completed' && (
                  <CheckCircle2 className="w-5 h-5 text-green-400" />
                )}
                {appointment.status === 'in-progress' && (
                  <>
                    <Clock className="w-5 h-5 text-blue-400 animate-pulse" />
                    {appointment.bookingServiceId && (
                      <>
                        <button
                          onClick={() => handleCompleteSession(appointment.bookingServiceId)}
                          className="px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white text-xs font-medium rounded-lg transition-colors"
                        >
                          Complete
                        </button>
                        <button
                          onClick={() => handleCancelSession(appointment.bookingServiceId)}
                          className="px-3 py-1.5 bg-red-400/20 hover:bg-red-400/30 text-red-400 text-xs font-medium rounded-lg transition-colors"
                        >
                          Cancel
                        </button>
                      </>
                    )}
                  </>
                )}
                {appointment.status === 'upcoming' && appointment.bookingServiceId && (
                  <>
                    <button
                      onClick={() => handleStartSession(appointment.bookingServiceId)}
                      disabled={!isAppointmentTimeDue(appointment.date, appointment.time)}
                      className={`px-3 py-1.5 text-black text-xs font-medium rounded-lg transition-colors ${
                        isAppointmentTimeDue(appointment.date, appointment.time)
                          ? 'bg-gold hover:bg-amber-500'
                          : 'bg-gray-600 cursor-not-allowed opacity-50'
                      }`}
                    >
                      Start session
                    </button>
                    <button
                      onClick={() => handleCancelSession(appointment.bookingServiceId)}
                      className="px-3 py-1.5 bg-red-400/20 hover:bg-red-400/30 text-red-400 text-xs font-medium rounded-lg transition-colors"
                    >
                      Cancel
                    </button>
                  </>
                )}
                {appointment.status === 'cancelled' && (
                  <XCircle className="w-5 h-5 text-red-400" />
                )}
              </div>
            </motion.div>
          )))}
        </div>
      </motion.div>

      {/* My Availability + Ad-hoc Break */}
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <motion.div
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.9 }}
          className="bg-card border border-border-light rounded-2xl overflow-hidden"
        >
          <div className="flex items-center gap-2 p-4 pb-0">
            <Coffee className="w-5 h-5 text-amber-400" />
            <h3 className="font-semibold text-text-primary">My Availability</h3>
          </div>
          {specialist?.id ? (
            <BreakWidget
              specialistId={specialist.id}
              providerId={currentWorkplace?.provider_id ?? ''}
              isSpecialistPortal
              initialState={floorState}
              onStateChange={(s) => {
                setFloorState(s);
                queryClient.invalidateQueries({ queryKey: ['specialist-today-bookings'] });
              }}
            />
          ) : (
            <div className="p-4">
              <SpecialistStatusPill state={floorState} />
            </div>
          )}
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
