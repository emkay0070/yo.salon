'use client';

import { motion } from 'framer-motion';
import { Calendar, Search, Plus, UserCheck, Clock, DollarSign, RotateCcw, X, Users } from 'lucide-react';
import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';
import { useRole } from '@/contexts/RoleContext';

interface ReceptionistDashboardProps {
  userName: string;
}

export default function ReceptionistDashboard({ userName }: ReceptionistDashboardProps) {
  const { salonId } = useRole();
  const today = new Date();
  const formattedDate = today.toLocaleDateString('en-US', {
    weekday: 'long',
    day: 'numeric',
    month: 'long'
  });

  // Fetch real data from API
  const { data: bookings = [], isLoading: bookingsLoading } = useQuery({
    queryKey: ['bookings', salonId, 'today'],
    queryFn: () => apiClient.getBookings({ salon_id: salonId, date: 'today' }),
    enabled: !!salonId,
  });

  const { data: customers = [] } = useQuery({
    queryKey: ['customers', salonId],
    queryFn: () => apiClient.getCustomers({ salon_id: salonId }),
    enabled: !!salonId,
  });

  // Calculate stats from real data
  const todayStats = {
    totalBookings: bookings.length,
    checkedIn: bookings.filter((b: any) => b.status === 'checked_in').length,
    pending: bookings.filter((b: any) => b.status === 'pending').length,
    completed: bookings.filter((b: any) => b.status === 'completed').length,
  };

  const quickActions = [
    { icon: Calendar, label: "Today's Calendar", color: 'from-[#FFD700] to-[#C9A227]' },
    { icon: Search, label: 'Search Customer', color: 'from-[#6366F1] to-[#4F46E5]' },
    { icon: Plus, label: 'New Booking', color: 'from-[#2F7A5C] to-[#1E523D]' },
    { icon: UserCheck, label: 'Walk-in', color: 'from-[#FF622B] to-[#CC4E22]' },
    { icon: Clock, label: 'Check-in', color: 'from-[#8B5CF6] to-[#7C3AED]' },
    { icon: DollarSign, label: 'Payments', color: 'from-[#EC4899] to-[#DB2777]' },
    { icon: RotateCcw, label: 'Reschedule', color: 'from-[#F59E0B] to-[#D97706]' },
    { icon: X, label: 'Cancel', color: 'from-[#EF4444] to-[#DC2626]' },
  ];

  // Transform bookings to upcoming format
  const upcomingBookings = bookings
    .filter((b: any) => ['pending', 'checked_in'].includes(b.status))
    .slice(0, 4)
    .map((b: any) => ({
      time: b.date ? new Date(b.date).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '00:00',
      name: b.customer?.name || 'Unknown',
      service: b.service?.name || 'Service',
      phone: b.customer?.phone || '',
      status: b.status,
    }));

  // Transform bookings to recent check-ins
  const recentCheckIns = bookings
    .filter((b: any) => b.status === 'checked_in')
    .slice(0, 3)
    .map((b: any) => ({
      name: b.customer?.name || 'Unknown',
      time: b.date ? new Date(b.date).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '00:00',
      service: b.service?.name || 'Service',
    }));

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'checked_in': return 'bg-green-400';
      case 'pending': return 'bg-yellow-400';
      case 'completed': return 'bg-blue-400';
      default: return 'bg-gray-400';
    }
  };

  const getStatusText = (status: string) => {
    switch (status) {
      case 'checked_in': return 'Checked In';
      case 'pending': return 'Pending';
      case 'completed': return 'Completed';
      default: return 'Unknown';
    }
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <motion.div
        initial={{ opacity: 0, y: -10 }}
        animate={{ opacity: 1, y: 0 }}
        className="flex items-center justify-between"
      >
        <div>
          <h1 className="text-2xl font-bold text-text-primary">Receptionist Dashboard</h1>
          <p className="text-text-secondary mt-1">{formattedDate}</p>
        </div>
      </motion.div>

      {/* Today's Stats */}
      <div className="grid grid-cols-4 gap-4">
        {[
          { label: 'Total Bookings', value: todayStats.totalBookings, icon: Calendar, color: 'text-gold', bg: 'from-[rgba(255,215,0,0.2)] to-[rgba(255,215,0,0.05)]' },
          { label: 'Checked In', value: todayStats.checkedIn, icon: UserCheck, color: 'text-green-400', bg: 'from-[rgba(74,222,128,0.2)] to-[rgba(74,222,128,0.05)]' },
          { label: 'Pending', value: todayStats.pending, icon: Clock, color: 'text-yellow-400', bg: 'from-[rgba(250,204,21,0.2)] to-[rgba(250,204,21,0.05)]' },
          { label: 'Completed', value: todayStats.completed, icon: Users, color: 'text-blue-400', bg: 'from-[rgba(96,165,250,0.2)] to-[rgba(96,165,250,0.05)]' },
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

      {/* Quick Actions */}
      <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        className="bg-card border border-border-light rounded-2xl p-6 backdrop-blur-2xl"
      >
        <h2 className="text-lg font-semibold text-text-primary mb-4">Quick Actions</h2>
        
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          {quickActions.map((action) => (
            <button
              key={action.label}
              className="flex flex-col items-center gap-3 p-4 rounded-xl bg-gradient-to-r from-white/5 to-white/10 border border-border-light hover:border-[rgba(255,215,0,0.4)] transition-all group"
            >
              <div className={`p-3 rounded-xl bg-gradient-to-br ${action.color}`}>
                <action.icon className="w-5 h-5 text-text-primary" />
              </div>
              <span className="text-text-primary font-medium text-sm text-center">{action.label}</span>
            </button>
          ))}
        </div>
      </motion.div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Upcoming Bookings */}
        <motion.div
          initial={{ opacity: 0, x: -10 }}
          animate={{ opacity: 1, x: 0 }}
          className="bg-card border border-border-light rounded-2xl p-6 backdrop-blur-2xl"
        >
          <h2 className="text-lg font-semibold text-text-primary mb-4">Upcoming Bookings</h2>
          
          <div className="space-y-3">
            {upcomingBookings.map((booking: any) => (
              <div key={booking.time} className="flex items-center gap-4 p-4 rounded-xl bg-surface border border-border-medium">
                <div className="flex flex-col items-center min-w-[60px]">
                  <span className="text-text-primary font-semibold">{booking.time}</span>
                  <div className={`w-2 h-2 rounded-full mt-1 ${getStatusColor(booking.status)}`} />
                </div>
                <div className="flex-1">
                  <p className="text-text-primary font-medium">{booking.name}</p>
                  <p className="text-text-secondary text-sm">{booking.service}</p>
                  <p className="text-text-secondary text-xs mt-1">{booking.phone}</p>
                </div>
                <div className="text-right">
                  <span className={`text-xs px-2 py-1 rounded-full ${
                    booking.status === 'checked_in' ? 'bg-green-500/20 text-green-400' :
                    'bg-yellow-500/20 text-yellow-400'
                  }`}>
                    {getStatusText(booking.status)}
                  </span>
                </div>
              </div>
            ))}
          </div>
        </motion.div>

        {/* Recent Check-ins */}
        <motion.div
          initial={{ opacity: 0, x: 10 }}
          animate={{ opacity: 1, x: 0 }}
          className="bg-card border border-border-light rounded-2xl p-6 backdrop-blur-2xl"
        >
          <h2 className="text-lg font-semibold text-text-primary mb-4 flex items-center gap-2">
            <UserCheck className="w-5 h-5 text-gold" />
            Recent Check-ins
          </h2>
          
          <div className="space-y-3">
            {recentCheckIns.map((checkIn: any, index: number) => (
              <div key={index} className="flex items-center gap-4 p-4 rounded-xl bg-surface border border-border-medium">
                <div className="w-10 h-10 rounded-full bg-gradient-to-br from-[#FFD700] to-[#C9A227] flex items-center justify-center">
                  <Users className="w-5 h-5 text-obsidian" />
                </div>
                <div className="flex-1">
                  <p className="text-text-primary font-medium">{checkIn.name}</p>
                  <p className="text-text-secondary text-sm">{checkIn.service}</p>
                </div>
                <div className="text-right">
                  <p className="text-text-secondary text-sm">{checkIn.time}</p>
                </div>
              </div>
            ))}
          </div>
        </motion.div>
      </div>

      {/* Search Customer Section */}
      <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        className="bg-card border border-border-light rounded-2xl p-6 backdrop-blur-2xl"
      >
        <h2 className="text-lg font-semibold text-text-primary mb-4">Search Customer</h2>
        
        <div className="relative">
          <Search className="w-5 h-5 text-text-secondary absolute left-4 top-1/2 -translate-y-1/2" />
          <input
            type="text"
            placeholder="Search by name or phone number..."
            className="w-full pl-12 pr-4 py-3 bg-card border border-border-light rounded-xl text-text-primary placeholder-[#A0A0A0] focus:outline-none focus:border-[rgba(255,215,0,0.4)] transition-colors"
          />
        </div>
      </motion.div>
    </div>
  );
}
