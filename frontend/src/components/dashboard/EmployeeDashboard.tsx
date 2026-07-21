'use client';

import { motion } from 'framer-motion';
import { Play, CheckCircle, MessageSquare, DollarSign, Users, Star, Clock, Coffee } from 'lucide-react';
import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';
import { useRole } from '@/contexts/RoleContext';

interface EmployeeDashboardProps {
  userName: string;
}

export default function EmployeeDashboard({ userName }: EmployeeDashboardProps) {
  const { salonId } = useRole();
  const today = new Date();
  const formattedDate = today.toLocaleDateString('en-US', {
    weekday: 'long',
    day: 'numeric',
    month: 'long'
  });

  const greeting = today.getHours() < 12 ? 'Good Morning' : today.getHours() < 18 ? 'Good Afternoon' : 'Good Evening';

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

  // Get current staff member (would need auth context to get current user's staff ID)
  const currentStaff = staff[0]; // Placeholder - should use current user's staff ID

  // Filter bookings for current staff
  const staffBookings = bookings.filter((b: any) => b.staff_id === currentStaff?.id);

  // Get next client
  const nextBooking = staffBookings.find((b: any) => ['pending', 'confirmed'].includes(b.status));
  const nextClient = nextBooking ? {
    name: nextBooking.customer?.name || 'Unknown',
    time: nextBooking.date ? new Date(nextBooking.date).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '00:00',
    service: nextBooking.service?.name || 'Service',
    duration: `${nextBooking.service?.duration || 30} min`,
    paid: nextBooking.payment_status === 'paid',
  } : null;

  // Transform bookings to timeline format
  const todayTimeline = staffBookings
    .map((b: any) => ({
      time: b.date ? new Date(b.date).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '00:00',
      service: b.service?.name || 'Service',
      status: b.status === 'completed' ? 'completed' : ['pending', 'confirmed'].includes(b.status) ? 'upcoming' : 'break',
    }))
    .sort((a: any, b: any) => a.time.localeCompare(b.time));

  const customerNotes: any[] = []; // Would need customer notes API

  // Calculate performance from real data
  const performance = {
    earnings: staffBookings.reduce((sum: number, b: any) => sum + (b.service?.price || 0), 0),
    customers: staffBookings.length,
    rating: currentStaff?.rating || 4.5,
  };

  const quickActions = [
    { icon: Play, label: 'Start Service', color: 'from-[#2F7A5C] to-[#1E523D]' },
    { icon: CheckCircle, label: 'Finish Service', color: 'from-[#FFD700] to-[#C9A227]' },
    { icon: MessageSquare, label: 'Add Note', color: 'from-[#6366F1] to-[#4F46E5]' },
    { icon: DollarSign, label: 'Sell Product', color: 'from-[#FF622B] to-[#CC4E22]' },
  ];

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'completed': return 'bg-green-400';
      case 'upcoming': return 'bg-[#FFD700]';
      case 'break': return 'bg-blue-400';
      default: return 'bg-gray-400';
    }
  };

  return (
    <div className="space-y-6">
      {/* Greeting */}
      <motion.div
        initial={{ opacity: 0, y: -10 }}
        animate={{ opacity: 1, y: 0 }}
        className="bg-gradient-to-r from-[rgba(255,215,0,0.1)] to-[rgba(255,215,0,0.05)] border border-[rgba(255,215,0,0.2)] rounded-2xl p-6"
      >
        <h1 className="text-2xl font-bold text-text-primary">
          {greeting}, {userName}
        </h1>
        <p className="text-text-secondary mt-1">You have {performance.customers} appointments today.</p>
      </motion.div>

      {/* Next Client - Big Card */}
      {nextClient ? (
        <motion.div
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          className="bg-gradient-to-br from-[rgba(255,215,0,0.15)] to-[rgba(255,215,0,0.05)] border-2 border-[rgba(255,215,0,0.3)] rounded-2xl p-6"
        >
          <div className="flex items-center gap-2 mb-4">
            <Clock className="w-5 h-5 text-gold" />
            <h2 className="text-lg font-semibold text-text-primary">Next Client</h2>
          </div>
          
          <div className="flex items-start gap-6">
            <div className="w-20 h-20 rounded-2xl bg-gradient-to-br from-[#FFD700] to-[#C9A227] flex items-center justify-center flex-shrink-0">
              <span className="text-3xl font-bold text-obsidian">{nextClient.name[0]}</span>
            </div>
            
            <div className="flex-1">
              <h3 className="text-2xl font-bold text-text-primary">{nextClient.name}</h3>
              <div className="flex items-center gap-4 mt-2">
                <div className="flex items-center gap-2">
                  <Clock className="w-4 h-4 text-text-secondary" />
                  <span className="text-text-secondary">{nextClient.time}</span>
                </div>
                <div className="flex items-center gap-2">
                  <span className="text-text-secondary">{nextClient.duration}</span>
                </div>
                {nextClient.paid && (
                  <div className="flex items-center gap-2">
                    <CheckCircle className="w-4 h-4 text-green-400" />
                    <span className="text-green-400 text-sm">Paid</span>
                  </div>
                )}
              </div>
              <p className="text-text-primary font-medium mt-2">{nextClient.service}</p>
            </div>
          </div>
        </motion.div>
      ) : (
        <motion.div
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          className="relative overflow-hidden border-2 border-[rgba(255,215,0,0.1)] rounded-2xl p-8 flex flex-col items-center justify-center text-center min-h-[220px]"
        >
          <div className="absolute inset-0 z-0">
            <img src="/images/salon_empty_chair.png" alt="Empty Chair" className="w-full h-full object-cover opacity-20" />
            <div className="absolute inset-0 bg-gradient-to-t from-surface via-surface/80 to-transparent"></div>
          </div>
          <div className="relative z-10">
            <div className="w-16 h-16 mx-auto rounded-full bg-card border border-border-light flex items-center justify-center mb-4">
              <Clock className="w-8 h-8 text-gold" />
            </div>
            <h2 className="text-xl font-semibold text-text-primary mb-2">No upcoming appointments</h2>
            <p className="text-text-secondary">Time to rest, organize, or catch up on training.</p>
          </div>
        </motion.div>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Today's Timeline */}
        <motion.div
          initial={{ opacity: 0, x: -10 }}
          animate={{ opacity: 1, x: 0 }}
          className="bg-card border border-border-light rounded-2xl p-6 backdrop-blur-2xl"
        >
          <h2 className="text-lg font-semibold text-text-primary mb-4">Today's Timeline</h2>
          
          <div className="space-y-4">
            {todayTimeline.map((item: any, index: number) => (
              <div key={index} className="flex items-start gap-4">
                <div className="flex flex-col items-center">
                  <div className={`w-3 h-3 rounded-full ${getStatusColor(item.status)}`} />
                  {index < todayTimeline.length - 1 && (
                    <div className="w-0.5 h-12 bg-white/10 mt-2" />
                  )}
                </div>
                <div className="flex-1 pb-4">
                  <p className="text-text-primary font-medium">{item.time}</p>
                  <p className="text-text-secondary text-sm mt-1">{item.service}</p>
                  {item.status === 'break' && (
                    <div className="flex items-center gap-2 mt-2">
                      <Coffee className="w-3 h-3 text-blue-400" />
                      <span className="text-blue-400 text-xs">Break Time</span>
                    </div>
                  )}
                </div>
              </div>
            ))}
          </div>
        </motion.div>

        {/* Customer Notes */}
        <motion.div
          initial={{ opacity: 0, x: 10 }}
          animate={{ opacity: 1, x: 0 }}
          className="bg-card border border-border-light rounded-2xl p-6 backdrop-blur-2xl"
        >
          <h2 className="text-lg font-semibold text-text-primary mb-4 flex items-center gap-2">
            <MessageSquare className="w-5 h-5 text-gold" />
            Customer Notes
          </h2>
          
          <div className="space-y-3">
            {customerNotes.map((note, index) => (
              <div key={index} className="p-4 rounded-xl bg-surface border border-border-medium">
                <p className="text-text-primary font-medium mb-2">{note.customer}</p>
                <p className="text-text-secondary text-sm">{note.notes}</p>
              </div>
            ))}
          </div>
          
          <div className="mt-4 p-4 rounded-xl bg-[rgba(255,215,0,0.1)] border border-[rgba(255,215,0,0.2)]">
            <p className="text-gold text-sm">
              💡 Adding notes helps customers feel remembered and improves their experience.
            </p>
          </div>
        </motion.div>
      </div>

      {/* Performance */}
      <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        className="bg-card border border-border-light rounded-2xl p-6 backdrop-blur-2xl"
      >
        <h2 className="text-lg font-semibold text-text-primary mb-4">Today's Performance</h2>
        
        <div className="grid grid-cols-3 gap-4">
          <div className="p-4 rounded-xl bg-surface border border-border-medium">
            <div className="flex items-center gap-2 mb-2">
              <DollarSign className="w-4 h-4 text-gold" />
              <p className="text-text-secondary text-sm">Earnings</p>
            </div>
            <p className="text-2xl font-bold text-text-primary">UGX {performance.earnings.toLocaleString()}</p>
          </div>
          
          <div className="p-4 rounded-xl bg-surface border border-border-medium">
            <div className="flex items-center gap-2 mb-2">
              <Users className="w-4 h-4 text-gold" />
              <p className="text-text-secondary text-sm">Customers</p>
            </div>
            <p className="text-2xl font-bold text-text-primary">{performance.customers}</p>
          </div>
          
          <div className="p-4 rounded-xl bg-surface border border-border-medium">
            <div className="flex items-center gap-2 mb-2">
              <Star className="w-4 h-4 text-gold" />
              <p className="text-text-secondary text-sm">Rating</p>
            </div>
            <p className="text-2xl font-bold text-text-primary">{performance.rating}</p>
          </div>
        </div>
      </motion.div>

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
              <span className="text-text-primary font-medium text-sm">{action.label}</span>
            </button>
          ))}
        </div>
      </motion.div>
    </div>
  );
}
