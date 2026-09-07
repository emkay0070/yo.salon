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
import { useState } from 'react';
import { apiClient } from '@/lib/api-client';

type AppointmentTab = 'upcoming' | 'completed' | 'cancelled' | 'no-show';

export default function AppointmentsPage() {
  const { specialist } = useSpecialistAuth();
  const [activeTab, setActiveTab] = useState<AppointmentTab>('upcoming');
  const queryClient = useQueryClient();

  const updateStatus = useMutation({
    mutationFn: async ({ bookingId, status }: { bookingId: string, status: string }) => {
      return await apiClient.put(`/v1/specialist-portal/bookings/${bookingId}/status`, { status });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['specialist-appointments'] });
    }
  });

  const { data, isLoading, isError } = useQuery({
    queryKey: ['specialist-appointments', activeTab],
    queryFn: async () => {
      // For upcoming we get all from today onwards with status filter
      // For historical tabs we need 'all' scope
      const scope = activeTab === 'upcoming' ? 'upcoming' : 'all';
      const json = await apiClient.get(`/v1/specialist-portal/bookings?scope=${scope}&status=${activeTab}`);
      return json.bookings as any[];
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
    if (s === 'completed') return 'text-green-400 bg-green-400/10';
    if (s === 'cancelled') return 'text-red-400 bg-red-400/10';
    if (s === 'no_show' || s === 'no-show') return 'text-orange-400 bg-orange-400/10';
    return 'text-text-secondary bg-surface';
  };

  const getStatusLabel = (status: string) => {
    const map: Record<string, string> = {
      pending: 'Pending',
      confirmed: 'Confirmed',
      completed: 'Completed',
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
                      <span className={`text-xs font-semibold px-2 py-0.5 rounded-full ${getStatusColor(booking.status)}`}>
                        {getStatusLabel(booking.status)}
                      </span>
                      {activeTab === 'upcoming' && booking.status !== 'confirmed' && (
                        <div className="flex gap-2">
                          <button 
                            onClick={(e) => {
                              e.stopPropagation();
                              updateStatus.mutate({ bookingId: booking.id, status: 'confirmed' });
                            }}
                            disabled={updateStatus.isPending}
                            className="px-3 py-1 rounded-lg bg-green-400/20 text-green-400 text-xs font-medium hover:bg-green-400/30 transition-colors disabled:opacity-50">
                            Confirm
                          </button>
                          <button 
                            onClick={(e) => {
                              e.stopPropagation();
                              updateStatus.mutate({ bookingId: booking.id, status: 'cancelled' });
                            }}
                            disabled={updateStatus.isPending}
                            className="px-3 py-1 rounded-lg bg-red-400/20 text-red-400 text-xs font-medium hover:bg-red-400/30 transition-colors disabled:opacity-50">
                            Cancel
                          </button>
                        </div>
                      )}
                      {activeTab === 'upcoming' && booking.status === 'confirmed' && (
                         <div className="flex gap-2">
                          <button 
                            onClick={(e) => {
                              e.stopPropagation();
                              updateStatus.mutate({ bookingId: booking.id, status: 'completed' });
                            }}
                            disabled={updateStatus.isPending}
                            className="px-3 py-1 rounded-lg bg-blue-400/20 text-blue-400 text-xs font-medium hover:bg-blue-400/30 transition-colors disabled:opacity-50">
                            Complete
                          </button>
                          <button 
                            onClick={(e) => {
                              e.stopPropagation();
                              updateStatus.mutate({ bookingId: booking.id, status: 'no_show' });
                            }}
                            disabled={updateStatus.isPending}
                            className="px-3 py-1 rounded-lg bg-orange-400/20 text-orange-400 text-xs font-medium hover:bg-orange-400/30 transition-colors disabled:opacity-50">
                            No Show
                          </button>
                        </div>
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
    </div>
  );
}
