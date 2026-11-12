'use client';

import { useSpecialistAuth } from '@/contexts/SpecialistAuthContext';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { motion } from 'framer-motion';
import {
  CalendarClock,
  CheckCircle2,
  XCircle,
  Clock,
  Scissors,
  Filter,
  AlertCircle,
  Loader2
} from 'lucide-react';
import { useState, useEffect } from 'react';
import { apiClient } from '@/lib/api-client';
import { specialistWorkspaceApi } from '@/services/specialist/api/workspace';

type AppointmentTab = 'upcoming' | 'completed' | 'cancelled' | 'no-show';

export default function AppointmentsPage() {
  const { specialist } = useSpecialistAuth();
  const [activeTab, setActiveTab] = useState<AppointmentTab>('upcoming');
  const queryClient = useQueryClient();

  // Poll for no-show bookings every 2 minutes
  useEffect(() => {
    if (!specialist) return;

    const pollInterval = setInterval(async () => {
      try {
        await specialistWorkspaceApi.checkAppointmentsNeedingAttention();
        // Refetch appointments to update UI with any no-show status changes
        queryClient.invalidateQueries({ queryKey: ['specialist-appointments'] });
      } catch (error) {
        console.error('Failed to check appointments needing attention:', error);
      }
    }, 120000); // 2 minutes

    return () => clearInterval(pollInterval);
  }, [specialist, queryClient]);
  const [sessionModal, setSessionModal] = useState<{
    isOpen: boolean;
    bookingServiceId: string | null;
    customerName: string;
    serviceName: string;
    startedAt: string | null;
  }>({
    isOpen: false,
    bookingServiceId: null,
    customerName: '',
    serviceName: '',
    startedAt: null,
  });
  const [sessionDuration, setSessionDuration] = useState(0);

  const updateStatus = useMutation({
    mutationFn: async ({ bookingId, status }: { bookingId: string, status: string }) => {
      return await apiClient.put(`/v1/specialist-portal/bookings/${bookingId}/status`, { status });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['specialist-appointments'] });
    }
  });

  const startSession = useMutation({
    mutationFn: async (bookingServiceId: string) => {
      return await apiClient.post(`/v1/specialist-portal/sessions/${bookingServiceId}/start`);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['specialist-appointments'] });
    }
  });

  const completeSession = useMutation({
    mutationFn: async (bookingServiceId: string) => {
      return await apiClient.post(`/v1/specialist-portal/sessions/${bookingServiceId}/complete`);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['specialist-appointments'] });
      setSessionModal({ isOpen: false, bookingServiceId: null, customerName: '', serviceName: '', startedAt: null });
    }
  });

  const cancelSession = useMutation({
    mutationFn: async (bookingServiceId: string) => {
      return await apiClient.post(`/v1/specialist-portal/sessions/${bookingServiceId}/cancel`);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['specialist-appointments'] });
    }
  });

  // Update session duration timer when modal is open
  useEffect(() => {
    if (sessionModal.isOpen && sessionModal.startedAt) {
      const interval = setInterval(() => {
        const start = new Date(sessionModal.startedAt!);
        const now = new Date();
        const diffMs = now.getTime() - start.getTime();
        const diffMins = Math.floor(diffMs / 60000);
        setSessionDuration(diffMins);
      }, 1000);

      return () => clearInterval(interval);
    }
  }, [sessionModal.isOpen, sessionModal.startedAt]);

  const handleStartSessionClick = (booking: any) => {
    const bookingService = booking.booking_services?.[0];
    if (!bookingService?.id) return;

    setSessionModal({
      isOpen: true,
      bookingServiceId: bookingService.id,
      customerName: booking.customer?.name || 'Walk-in',
      serviceName: booking.service?.name || booking.services?.[0]?.name || 'Service',
      startedAt: null,
    });

    // Start the session
    startSession.mutate(bookingService.id);
  };

  const handleCompleteSessionClick = () => {
    if (sessionModal.bookingServiceId) {
      completeSession.mutate(sessionModal.bookingServiceId);
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

  const { data, isLoading, isError } = useQuery({
    queryKey: ['specialist-appointments', activeTab],
    queryFn: async () => {
      // For upcoming we get all from today onwards with status filter
      // For historical tabs we need 'all' scope
      const scope = activeTab === 'upcoming' ? 'upcoming' : 'all';
      const json = await apiClient.get(`/v1/specialist-portal/bookings?scope=${scope}&status=${activeTab}`);
      
      // Map bookings to include session data and filter appropriately
      return (json.bookings as any[])
        .filter((b: any) => {
          const bookingService = b.booking_services?.[0];
          const sessionStatus = bookingService?.session_status || null;

          // Filter out completed or cancelled sessions from upcoming tab
          if (activeTab === 'upcoming' && (sessionStatus === 'completed' || sessionStatus === 'cancelled')) {
            return false;
          }

          // For completed tab, include bookings with completed session status even if booking status isn't completed
          if (activeTab === 'completed') {
            return b.status === 'completed' || sessionStatus === 'completed';
          }

          // For cancelled tab, include bookings with cancelled session status
          if (activeTab === 'cancelled') {
            return b.status === 'cancelled' || sessionStatus === 'cancelled';
          }

          return true;
        })
        .map((b: any) => {
        const bookingService = b.booking_services?.[0];
        const sessionStatus = bookingService?.session_status || null;
        const sessionStartedAt = bookingService?.session_started_at || null;

        return {
          ...b,
          bookingServiceId: bookingService?.id || null,
          sessionStatus: sessionStatus,
          sessionStartedAt: sessionStartedAt,
          // Determine display status based on session state
          displayStatus: sessionStatus === 'in_progress' ? 'in-progress' :
                         sessionStatus === 'completed' ? 'completed' :
                         sessionStatus === 'cancelled' ? 'cancelled' :
                         b.status
        };
      });
    },
    enabled: !!specialist,
  });

  const tabs: { id: AppointmentTab; label: string; icon: any }[] = [
    { id: 'upcoming',  label: 'Upcoming',   icon: Clock },
    { id: 'completed', label: 'Completed',  icon: CheckCircle2 },
    { id: 'cancelled', label: 'Cancelled',  icon: XCircle },
    { id: 'no-show',   label: 'No Shows',   icon: AlertCircle },
  ];

  const getStatusColor = (status: string) => {
    const s = status?.toLowerCase();
    if (s === 'confirmed' || s === 'pending') return 'text-blue-400 bg-blue-400/10';
    if (s === 'completed' || s === 'in-progress') return 'text-green-400 bg-green-400/10';
    if (s === 'cancelled') return 'text-red-400 bg-red-400/10';
    if (s === 'no_show' || s === 'no-show') return 'text-orange-400 bg-orange-400/10';
    return 'text-text-secondary bg-surface';
  };

  const getStatusLabel = (status: string) => {
    const map: Record<string, string> = {
      pending: 'Pending',
      confirmed: 'Confirmed',
      completed: 'Completed',
      'in-progress': 'In Session',
      cancelled: 'Cancelled',
      no_show: 'No Show',
      'no-show': 'No Show',
    };
    return map[status?.toLowerCase()] ?? status;
  };

  const formatDate = (dateStr: string) => {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-US', {
      weekday: 'short',
      month: 'short',
      day: 'numeric',
    });
  };

  const formatTime = (timeStr: string) => {
    if (!timeStr) return '';
    // time column is stored as HH:MM:SS — parse manually
    const parts = timeStr.split(':');
    const h = parseInt(parts[0]);
    const m = parts[1];
    const ampm = h >= 12 ? 'PM' : 'AM';
    const h12 = h % 12 || 12;
    return `${h12}:${m} ${ampm}`;
  };

  const appointments = data ?? [];

  return (
    <div className="p-4 sm:p-6 lg:p-8 space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-text-primary">Appointments</h1>
        <p className="text-text-secondary">Manage your daily work</p>
      </div>

      {/* Tabs */}
      <div className="flex gap-2 overflow-x-auto pb-2">
        {tabs.map((tab) => {
          const Icon = tab.icon;
          return (
            <button
              key={tab.id}
              onClick={() => setActiveTab(tab.id)}
              className={`flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors whitespace-nowrap ${
                activeTab === tab.id
                  ? 'bg-gold text-black'
                  : 'bg-surface text-text-secondary hover:text-text-primary'
              }`}
            >
              <Icon className="w-4 h-4" />
              {tab.label}
            </button>
          );
        })}
      </div>

      {/* Appointments List */}
      <div className="bg-card border border-border-light rounded-2xl p-6">
        <div className="flex items-center justify-between mb-6">
          <h3 className="font-semibold text-text-primary">
            {tabs.find(t => t.id === activeTab)?.label} Appointments
            {!isLoading && (
              <span className="ml-2 text-sm font-normal text-text-secondary">
                ({appointments.length})
              </span>
            )}
          </h3>
          <button className="flex items-center gap-2 px-4 py-2 rounded-lg bg-surface border border-border-light text-sm text-text-secondary hover:text-text-primary transition-colors">
            <Filter className="w-4 h-4" />
            Filter
          </button>
        </div>

        {/* Loading */}
        {isLoading && (
          <div className="flex items-center justify-center py-16">
            <Loader2 className="w-8 h-8 text-gold animate-spin" />
          </div>
        )}

        {/* Error */}
        {isError && (
          <div className="text-center py-12">
            <AlertCircle className="w-12 h-12 text-red-400 mx-auto mb-3" />
            <p className="text-text-secondary">Failed to load appointments. Please try again.</p>
          </div>
        )}

        {/* List */}
        {!isLoading && !isError && (
          <div className="space-y-3">
            {appointments.map((booking, index) => {
              const customerName = booking.customer?.name ?? 'Walk-in';
              const serviceName  = booking.service?.name ?? booking.services?.[0]?.name ?? 'Service';

              return (
                <motion.div
                  key={booking.id}
                  initial={{ opacity: 0, y: 10 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ delay: index * 0.04 }}
                  className="p-4 bg-surface rounded-xl border border-border-light hover:border-gold/30 transition-colors cursor-pointer"
                >
                  <div className="flex items-start justify-between gap-4">
                    <div className="flex items-start gap-4 flex-1">
                      {/* Avatar */}
                      <div className="w-12 h-12 rounded-full bg-gradient-to-br from-gold to-amber-600 flex items-center justify-center text-white font-bold flex-shrink-0 text-lg">
                        {customerName.charAt(0).toUpperCase()}
                      </div>

                      {/* Details */}
                      <div className="flex-1 min-w-0">
                        <p className="font-medium text-text-primary mb-1">{customerName}</p>
                        <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-text-secondary">
                          <span className="flex items-center gap-1">
                            <Scissors className="w-3.5 h-3.5" />
                            {serviceName}
                          </span>
                          <span className="flex items-center gap-1">
                            <Clock className="w-3.5 h-3.5" />
                            {formatTime(booking.time)}
                          </span>
                        </div>
                        <p className="text-xs text-text-secondary mt-1">
                          {formatDate(booking.date)}
                        </p>
                      </div>
                    </div>

                    {/* Status + actions */}
                    <div className="flex flex-col items-end gap-2 flex-shrink-0">
                      <span className={`text-xs font-semibold px-2 py-0.5 rounded-full ${getStatusColor(booking.displayStatus || booking.status)}`}>
                        {getStatusLabel(booking.displayStatus || booking.status)}
                      </span>
                      {activeTab === 'upcoming' && booking.displayStatus === 'in-progress' && booking.bookingServiceId && (
                        <div className="flex gap-2">
                          <button 
                            onClick={(e) => {
                              e.stopPropagation();
                              setSessionModal({
                                isOpen: true,
                                bookingServiceId: booking.bookingServiceId,
                                customerName: booking.customer?.name || 'Walk-in',
                                serviceName: booking.service?.name || booking.services?.[0]?.name || 'Service',
                                startedAt: booking.sessionStartedAt,
                              });
                            }}
                            className="px-3 py-1 rounded-lg bg-blue-500 hover:bg-blue-600 text-white text-xs font-medium transition-colors">
                            View Session
                          </button>
                          <button 
                            onClick={(e) => {
                              e.stopPropagation();
                              cancelSession.mutate(booking.bookingServiceId);
                            }}
                            disabled={cancelSession.isPending}
                            className="px-3 py-1 rounded-lg bg-red-400/20 hover:bg-red-400/30 text-red-400 text-xs font-medium transition-colors disabled:opacity-50">
                            Cancel
                          </button>
                        </div>
                      )}
                      {activeTab === 'upcoming' && (booking.displayStatus === 'upcoming' || booking.status === 'confirmed' || booking.status === 'pending') && booking.bookingServiceId && (
                        <div className="flex gap-2">
                          <button 
                            onClick={(e) => {
                              e.stopPropagation();
                              handleStartSessionClick(booking);
                            }}
                            disabled={startSession.isPending || !isAppointmentTimeDue(booking.date, booking.time)}
                            className={`px-3 py-1 rounded-lg text-black text-xs font-medium transition-colors ${
                              isAppointmentTimeDue(booking.date, booking.time)
                                ? 'bg-gold hover:bg-amber-500'
                                : 'bg-gray-600 cursor-not-allowed opacity-50'
                            } disabled:opacity-50`}>
                            Start Session
                          </button>
                        </div>
                      )}
                      {activeTab === 'upcoming' && (booking.status === 'confirmed' || booking.status === 'pending') && !booking.bookingServiceId && (
                        <button 
                          onClick={(e) => {
                            e.stopPropagation();
                            updateStatus.mutate({ bookingId: booking.id, status: 'cancelled' });
                          }}
                          disabled={updateStatus.isPending}
                          className="px-3 py-1 rounded-lg bg-red-400/20 text-red-400 text-xs font-medium hover:bg-red-400/30 transition-colors disabled:opacity-50">
                          Cancel
                        </button>
                      )}
                    </div>
                  </div>
                </motion.div>
              );
            })}

            {appointments.length === 0 && (
              <div className="text-center py-12">
                <CalendarClock className="w-12 h-12 text-text-secondary mx-auto mb-4" />
                <p className="text-text-secondary">No {activeTab} appointments</p>
              </div>
            )}
          </div>
        )}
      </div>

      {/* Session Modal */}
      {sessionModal.isOpen && (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          className="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4"
          onClick={() => setSessionModal({ isOpen: false, bookingServiceId: null, customerName: '', serviceName: '', startedAt: null })}
        >
          <motion.div
            initial={{ scale: 0.95, opacity: 0 }}
            animate={{ scale: 1, opacity: 1 }}
            exit={{ scale: 0.95, opacity: 0 }}
            className="bg-card border border-border-light rounded-2xl p-6 max-w-md w-full"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="text-center mb-6">
              <div className="w-16 h-16 mx-auto rounded-full bg-gradient-to-br from-gold to-amber-600 flex items-center justify-center text-white font-bold text-2xl mb-4">
                {sessionModal.customerName.charAt(0)}
              </div>
              <h2 className="text-xl font-bold text-text-primary mb-1">{sessionModal.customerName}</h2>
              <p className="text-text-secondary">{sessionModal.serviceName}</p>
            </div>

            <div className="bg-surface rounded-xl p-6 text-center mb-6">
              <p className="text-sm text-text-secondary mb-2">Session Duration</p>
              <p className="text-4xl font-bold text-gold font-mono">{sessionDuration}</p>
              <p className="text-xs text-text-secondary mt-1">minutes</p>
            </div>

            <div className="flex gap-3">
              <button
                onClick={() => setSessionModal({ isOpen: false, bookingServiceId: null, customerName: '', serviceName: '', startedAt: null })}
                className="flex-1 px-4 py-3 bg-surface border border-border-light rounded-xl text-text-primary font-medium hover:bg-surface/80 transition-colors"
              >
                Close
              </button>
              <button
                onClick={handleCompleteSessionClick}
                disabled={completeSession.isPending}
                className="flex-1 px-4 py-3 bg-blue-500 hover:bg-blue-600 text-white font-medium rounded-xl transition-colors disabled:opacity-50"
              >
                {completeSession.isPending ? 'Completing...' : 'Complete Session'}
              </button>
            </div>
          </motion.div>
        </motion.div>
      )}
    </div>
  );
}
