'use client';

import { motion } from 'framer-motion';
import { Users, Clock, UserPlus, Calendar, ArrowRight, CheckCircle, AlertCircle, Plus, RotateCcw, X, UserCheck } from 'lucide-react';
import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';
import { useRole } from '@/contexts/RoleContext';

interface ManagerDashboardProps {
  userName: string;
}

export default function ManagerDashboard({ userName }: ManagerDashboardProps) {
  const { salonId } = useRole();
  const today = new Date();
  const formattedDate = today.toLocaleDateString('en-US', {
    weekday: 'long',
    day: 'numeric',
    month: 'long'
  });

  // Fetch real data from API
  const { data: bookings = [] } = useQuery({
    queryKey: ['bookings', salonId, 'today'],
    queryFn: () => apiClient.getBookings({ salon_id: salonId, date: 'today' }),
    enabled: !!salonId,
  });

  const { data: staff = [] } = useQuery({
    queryKey: ['staff', salonId],
    queryFn: () => apiClient.getStaff({ salon_id: salonId }),
    enabled: !!salonId,
  });

  // Calculate stats from real data
  const queueStats = {
    waiting: bookings.filter((b: any) => b.status === 'pending').length,
    inProgress: bookings.filter((b: any) => b.status === 'in_service').length,
    completed: bookings.filter((b: any) => b.status === 'completed').length,
  };

  const staffAvailability = staff.map((s: any) => ({
    name: s.name,
    status: s.status || 'available',
  }));

  const upcomingAppointments = bookings
    .filter((b: any) => ['pending', 'confirmed'].includes(b.status))
    .slice(0, 3)
    .map((b: any) => ({
      time: b.date ? new Date(b.date).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '00:00',
      name: b.customer?.name || 'Unknown',
      service: b.service?.name || 'Service',
    }));

  const walkIns = {
    waiting: bookings.filter((b: any) => b.status === 'walk_in').length,
    averageWait: 0, // Would need timestamp data to calculate
  };

  const recentActivity = bookings
    .slice(0, 3)
    .map((b: any) => ({
      action: `${b.staff?.name || 'Staff'} ${b.status === 'completed' ? 'finished' : 'started'} ${b.service?.name || 'service'}`,
      time: b.date ? new Date(b.date).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '00:00',
    }));

  const quickActions = [
    { icon: Plus, label: 'New Booking', color: 'from-[#FFD700] to-[#C9A227]' },
    { icon: UserPlus, label: 'Walk-In', color: 'from-[#2F7A5C] to-[#1E523D]' },
    { icon: RotateCcw, label: 'Reschedule', color: 'from-[#FF622B] to-[#CC4E22]' },
    { icon: X, label: 'Cancel', color: 'from-[#FF4444] to-[#CC3333]' },
    { icon: UserCheck, label: 'Assign Staff', color: 'from-[#6366F1] to-[#4F46E5]' },
  ];

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'available': return 'bg-green-400';
      case 'busy': return 'bg-red-400';
      case 'lunch': return 'bg-yellow-400';
      default: return 'bg-gray-400';
    }
  };

  const getStatusText = (status: string) => {
    switch (status) {
      case 'available': return 'Available';
      case 'busy': return 'Busy';
      case 'lunch': return 'Lunch';
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
          <h1 className="text-2xl font-bold text-text-primary">Manager Dashboard</h1>
          <p className="text-text-secondary mt-1">{formattedDate}</p>
        </div>
      </motion.div>

      {/* Today's Queue */}
      <div className="grid grid-cols-3 gap-4">
        {[
          { label: 'Waiting', value: queueStats.waiting, icon: Clock, color: 'text-yellow-400', bg: 'from-[rgba(250,204,21,0.2)] to-[rgba(250,204,21,0.05)]' },
          { label: 'In Progress', value: queueStats.inProgress, icon: AlertCircle, color: 'text-blue-400', bg: 'from-[rgba(96,165,250,0.2)] to-[rgba(96,165,250,0.05)]' },
          { label: 'Completed', value: queueStats.completed, icon: CheckCircle, color: 'text-green-400', bg: 'from-[rgba(74,222,128,0.2)] to-[rgba(74,222,128,0.05)]' },
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
                <p className="text-2xl font-bold text-text-primary">{stat.value}</p>
              </div>
            </div>
          </motion.div>
        ))}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Staff Availability */}
        <motion.div
          initial={{ opacity: 0, x: -10 }}
          animate={{ opacity: 1, x: 0 }}
          className="bg-card border border-border-light rounded-2xl p-6 backdrop-blur-2xl"
        >
          <h2 className="text-lg font-semibold text-text-primary mb-4">Staff Availability</h2>
          
          <div className="space-y-3">
            {staffAvailability.map((staff: any) => (
              <div key={staff.name} className="flex items-center justify-between p-3 rounded-xl bg-surface border border-border-medium">
                <div className="flex items-center gap-3">
                  <div className="w-10 h-10 rounded-full bg-gradient-to-br from-[rgba(255,215,0,0.2)] to-[rgba(255,215,0,0.05)] flex items-center justify-center">
                    <Users className="w-5 h-5 text-gold" />
                  </div>
                  <p className="text-text-primary font-medium">{staff.name}</p>
                </div>
                <div className="flex items-center gap-2">
                  <div className={`w-2 h-2 rounded-full ${getStatusColor(staff.status)}`} />
                  <span className="text-text-secondary text-sm">{getStatusText(staff.status)}</span>
                </div>
              </div>
            ))}
          </div>
        </motion.div>

        {/* Walk-ins */}
        <motion.div
          initial={{ opacity: 0, x: 10 }}
          animate={{ opacity: 1, x: 0 }}
          className="bg-card border border-border-light rounded-2xl p-6 backdrop-blur-2xl"
        >
          <h2 className="text-lg font-semibold text-text-primary mb-4 flex items-center gap-2">
            <UserPlus className="w-5 h-5 text-gold" />
            Walk-ins
          </h2>
          
          <div className="grid grid-cols-2 gap-4">
            <div className="p-4 rounded-xl bg-surface border border-border-medium">
              <p className="text-text-secondary text-sm">Waiting</p>
              <p className="text-3xl font-bold text-text-primary mt-1">{walkIns.waiting}</p>
            </div>
            <div className="p-4 rounded-xl bg-surface border border-border-medium">
              <p className="text-text-secondary text-sm">Average Wait</p>
              <p className="text-3xl font-bold text-text-primary mt-1">{walkIns.averageWait} min</p>
            </div>
          </div>
        </motion.div>
      </div>

      {/* Upcoming Appointments */}
      <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        className="bg-card border border-border-light rounded-2xl p-6 backdrop-blur-2xl"
      >
        <h2 className="text-lg font-semibold text-text-primary mb-4">Upcoming Appointments</h2>
        
        <div className="space-y-3">
          {upcomingAppointments.map((appointment: any) => (
            <div key={appointment.time} className="flex items-center gap-4 p-4 rounded-xl bg-surface border border-border-medium">
              <div className="flex flex-col items-center min-w-[60px]">
                <span className="text-text-primary font-semibold">{appointment.time}</span>
              </div>
              <div className="w-10 h-10 rounded-full bg-gradient-to-br from-[#FFD700] to-[#C9A227] flex items-center justify-center">
                <Users className="w-5 h-5 text-obsidian" />
              </div>
              <div className="flex-1">
                <p className="text-text-primary font-medium">{appointment.name}</p>
                <p className="text-text-secondary text-sm">{appointment.service}</p>
              </div>
              <ArrowRight className="w-5 h-5 text-text-secondary" />
            </div>
          ))}
        </div>
      </motion.div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Recent Activity */}
        <motion.div
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          className="bg-card border border-border-light rounded-2xl p-6 backdrop-blur-2xl"
        >
          <h2 className="text-lg font-semibold text-text-primary mb-4">Recent Activity</h2>
          
          <div className="space-y-3">
            {recentActivity.map((activity: any, index: number) => (
              <div key={index} className="flex items-center gap-3 p-3 rounded-xl bg-surface border border-border-medium">
                <div className="w-2 h-2 rounded-full bg-[#FFD700]" />
                <div className="flex-1">
                  <p className="text-text-primary text-sm">{activity.action}</p>
                  <p className="text-text-secondary text-xs">{activity.time}</p>
                </div>
              </div>
            ))}
          </div>
        </motion.div>

        {/* Quick Actions */}
        <motion.div
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          className="bg-card border border-border-light rounded-2xl p-6 backdrop-blur-2xl"
        >
          <h2 className="text-lg font-semibold text-text-primary mb-4">Quick Actions</h2>
          
          <div className="grid grid-cols-2 gap-3">
            {quickActions.map((action) => (
              <button
                key={action.label}
                className="flex items-center gap-3 p-4 rounded-xl bg-gradient-to-r from-white/5 to-white/10 border border-border-light hover:border-[rgba(255,215,0,0.4)] transition-all group"
              >
                <div className={`p-2 rounded-lg bg-gradient-to-br ${action.color}`}>
                  <action.icon className="w-4 h-4 text-text-primary" />
                </div>
                <span className="text-text-primary font-medium text-sm">{action.label}</span>
              </button>
            ))}
          </div>
        </motion.div>
      </div>
    </div>
  );
}
