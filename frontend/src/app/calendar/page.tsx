'use client';

import { useEffect, useState } from 'react';
import {
  ChevronLeft,
  ChevronRight,
  Plus,
  User,
  Clock,
  Search
} from 'lucide-react';
import { apiClient } from '@/lib/api-client';
import { motion } from 'framer-motion';
import DashboardLayout from '@/components/DashboardLayout';

interface Booking {
  id: string;
  date: string;
  time: string;
  status: string;
  customer: {
    name: string;
    phone: string;
  };
  service: {
    name: string;
    price: number;
    duration: number;
  };
  staff?: {
    id: string;
    name: string;
  };
}

interface Staff {
  id: string;
  name: string;
  specializations?: string[];
}

// Generate time slots from 9 AM to 8 PM
const generateTimeSlots = () => {
  const slots = [];
  for (let hour = 9; hour <= 20; hour++) {
    slots.push(`${hour.toString().padStart(2, '0')}:00`);
    slots.push(`${hour.toString().padStart(2, '0')}:30`);
  }
  return slots;
};

const timeSlots = generateTimeSlots();

export default function CalendarPage() {
  const [bookings, setBookings] = useState<Booking[]>([]);
  const [staff, setStaff] = useState<Staff[]>([]);
  const [selectedDate, setSelectedDate] = useState(new Date());
  const [error, setError] = useState<string | null>(null);
  const [mounted, setMounted] = useState(false);
  const [draggedBooking, setDraggedBooking] = useState<Booking | null>(null);

  useEffect(() => {
    setMounted(true);
  }, []);

  useEffect(() => {
    async function loadData() {
      try {
        const [bookingsData, staffData] = await Promise.all([
          apiClient.getBookings(),
          apiClient.getStaff(),
        ]);
        setBookings(Array.isArray(bookingsData) ? bookingsData : []);
        setStaff(Array.isArray(staffData) ? staffData : []);
      } catch (err: any) {
        if (err.response?.status === 401) {
          window.location.href = '/login';
          return;
        }
        setError('Failed to load data');
        console.error(err);
      }
    }

    const token = localStorage.getItem('auth_token');
    if (!token) {
      window.location.href = '/login';
      return;
    }

    loadData();
  }, []);

  const navigateDate = (days: number) => {
    const newDate = new Date(selectedDate);
    newDate.setDate(newDate.getDate() + days);
    setSelectedDate(newDate);
  };

  const formatDate = (date: Date) => {
    return date.toLocaleDateString('en-US', { 
      weekday: 'long', 
      day: 'numeric', 
      month: 'long' 
    });
  };

  // Filter bookings for selected date
  const dayBookings = bookings.filter(booking => {
    const bookingDate = new Date(booking.date);
    return bookingDate.toDateString() === selectedDate.toDateString();
  });

  // Get booking for specific time slot and staff
  const getBookingForSlot = (time: string, staffId: string) => {
    return dayBookings.find(booking => 
      booking.staff?.id === staffId && isBookingAtTime(booking, time)
    );
  };

  // Check if booking is at a specific time (considering duration)
  const isBookingAtTime = (booking: Booking, time: string) => {
    const bookingTime = booking.time;
    const bookingDate = new Date(`${booking.date}T${bookingTime}`);
    const slotDate = new Date(`${booking.date}T${time}`);
    
    // Calculate end time based on service duration
    const endTime = new Date(bookingDate.getTime() + booking.service.duration * 60000);
    
    return slotDate >= bookingDate && slotDate < endTime;
  };

  // Calculate how many slots a booking spans
  const getBookingSpan = (booking: Booking) => {
    const duration = booking.service.duration;
    return Math.ceil(duration / 30); // Assuming 30-minute slots
  };

  // Handle drag start
  const handleDragStart = (booking: Booking) => {
    setDraggedBooking(booking);
  };

  // Handle drag end
  const handleDragEnd = () => {
    setDraggedBooking(null);
  };

  // Handle drop
  const handleDrop = async (time: string, staffId: string) => {
    if (!draggedBooking) return;

    try {
      // Update booking with new time and staff
      await apiClient.updateBooking(draggedBooking.id, {
        time,
        staff_id: staffId,
      });

      // Refresh bookings
      const updatedBookings = await apiClient.getBookings();
      setBookings(Array.isArray(updatedBookings) ? updatedBookings : []);
    } catch (err) {
      console.error('Failed to reschedule booking:', err);
      setError('Failed to reschedule booking');
    }

    setDraggedBooking(null);
  };

  if (error) {
    return (
      <DashboardLayout>
        <div className="flex items-center justify-center h-full">
          <div className="text-gold">{error}</div>
        </div>
      </DashboardLayout>
    );
  }

  return (
    <DashboardLayout>
      <div className="max-w-[1600px] mx-auto font-sans h-full overflow-x-hidden flex flex-col">
        {/* Calendar Header */}
        <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
          <div className="flex items-center gap-4 w-full sm:w-auto">
            <button
              onClick={() => navigateDate(-1)}
              className="p-2 rounded-xl bg-card hover:bg-white/10 transition-colors shrink-0"
            >
              <ChevronLeft className="w-5 h-5 text-text-primary" />
            </button>
            <div className="flex-1 sm:flex-none">
              <h1 className="text-2xl font-semibold text-text-primary tracking-tight">{formatDate(selectedDate)}</h1>
              <p className="text-text-secondary text-sm mt-1">Schedule</p>
            </div>
            <button
              onClick={() => navigateDate(1)}
              className="p-2 rounded-xl bg-card hover:bg-white/10 transition-colors shrink-0"
            >
              <ChevronRight className="w-5 h-5 text-text-primary" />
            </button>
          </div>

          <div className="flex items-center gap-2 sm:gap-3 w-full sm:w-auto">
            <div className="relative w-full sm:w-64">
              <Search className="w-5 h-5 text-text-secondary absolute left-3 top-1/2 -translate-y-1/2" />
              <input
                type="text"
                placeholder="Search booking..."
                className="pl-10 pr-4 py-2 bg-card border border-border-light rounded-xl text-text-primary placeholder-[#A0A0A0] focus:outline-none focus:border-[rgba(255,215,0,0.4)] transition-colors w-full"
              />
            </div>
            <button className="flex items-center gap-2 px-3 sm:px-4 py-2 bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-obsidian rounded-xl font-medium hover:opacity-90 transition-opacity text-sm">
              <Plus className="w-4 sm:w-5 h-4 sm:h-5" />
              <span className="hidden sm:inline">New Booking</span>
              <span className="sm:hidden">Add</span>
            </button>
          </div>
        </div>

        {/* Calendar Grid */}
        <motion.div
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.3 }}
          className={`flex-1 bg-card border border-border-light rounded-2xl backdrop-blur-2xl overflow-hidden flex flex-col ${
            draggedBooking ? 'ring-2 ring-[#FFD700]/50' : ''
          }`}
        >
          {/* Staff Headers */}
          <div className="flex border-b border-border-light bg-white/[0.02]">
            <div className="w-24 flex-shrink-0 p-4 text-text-secondary text-sm font-medium border-r border-border-light">
              Time
            </div>
            {staff.map((staffMember) => (
              <div
                key={staffMember.id}
                className="flex-1 p-4 text-center border-r border-border-light last:border-r-0 min-w-[140px]"
              >
                <div className="flex items-center justify-center gap-2 mb-1">
                  <div className="w-10 h-10 rounded-full bg-gradient-to-br from-[rgba(255,215,0,0.2)] to-[rgba(255,215,0,0.05)] flex items-center justify-center">
                    <User className="w-5 h-5 text-gold" />
                  </div>
                  <span className="text-text-primary font-semibold">{staffMember.name}</span>
                </div>
                <p className="text-text-secondary text-xs">{staffMember.specializations?.join(', ') || 'All services'}</p>
              </div>
            ))}
          </div>

          {/* Time Slots */}
          <div className="flex-1 overflow-y-auto">
            {timeSlots.map((time, index) => (
              <div
                key={time}
                className={`flex border-b border-white/5 ${index % 2 === 0 ? 'bg-white/[0.02]' : ''}`}
              >
                {/* Time Column */}
                <div className="w-24 flex-shrink-0 p-4 text-text-secondary text-sm border-r border-border-light flex items-center justify-center font-medium">
                  {time}
                </div>

                {/* Staff Columns */}
                {staff.map((staffMember) => {
                  const booking = getBookingForSlot(time, staffMember.id);
                  const isFirstSlot = booking && booking.time === time;
                  
                  return (
                    <div
                      key={staffMember.id}
                      className="flex-1 p-2 border-r border-border-light last:border-r-0 min-h-[70px] hover:bg-card transition-colors cursor-pointer relative group"
                      onDragOver={(e) => e.preventDefault()}
                      onDrop={() => handleDrop(time, staffMember.id)}
                    >
                      {booking && isFirstSlot ? (
                        <motion.div
                          draggable
                          onDragStart={() => handleDragStart(booking)}
                          onDragEnd={handleDragEnd}
                          initial={{ opacity: 0, scale: 0.95 }}
                          animate={{ opacity: 1, scale: 1 }}
                          transition={{ duration: 0.2 }}
                          style={{ 
                            height: `${getBookingSpan(booking) * 70 - 8}px`,
                            marginTop: '4px',
                            marginBottom: '4px'
                          }}
                          className={`rounded-xl p-3 border-2 cursor-move hover:scale-[1.02] transition-transform ${
                            booking.status === 'completed' ? 'bg-green-500/20 border-green-500/50' :
                            booking.status === 'cancelled' ? 'bg-red-500/20 border-red-500/50' :
                            'bg-[#FFD700]/20 border-[#FFD700]/50'
                          }`}
                        >
                          <div className="flex items-start gap-3">
                            <div className="w-8 h-8 rounded-full bg-gradient-to-br from-[rgba(255,215,0,0.3)] to-[rgba(255,215,0,0.1)] flex items-center justify-center flex-shrink-0">
                              <User className="w-4 h-4 text-gold" />
                            </div>
                            <div className="flex-1 min-w-0">
                              <p className="text-text-primary font-semibold text-sm truncate">{booking.customer.name}</p>
                              <p className="text-text-secondary text-xs truncate">{booking.service.name}</p>
                              <div className="flex items-center gap-2 mt-2">
                                <span className="text-gold text-xs font-semibold">${booking.service.price}</span>
                                <span className="text-text-secondary text-xs">•</span>
                                <span className="text-text-secondary text-xs">{booking.service.duration} min</span>
                              </div>
                            </div>
                          </div>
                        </motion.div>
                      ) : booking && !isFirstSlot ? (
                        // Occupied slot (part of a multi-slot booking)
                        <div className="h-full bg-card" />
                      ) : (
                        <div className="h-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                          <Plus className="w-6 h-6 text-text-secondary" />
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>
            ))}
          </div>
        </motion.div>

        {/* Quick Stats Footer */}
        <div className="grid grid-cols-4 gap-4 mt-6">
          <motion.div
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.3 }}
            className="bg-card border border-border-light rounded-xl p-4 backdrop-blur-2xl"
          >
            <div className="flex items-center gap-3">
              <Clock className="w-5 h-5 text-gold" />
              <div>
                <p className="text-text-secondary text-xs">Total Bookings</p>
                <p className="text-lg font-bold text-text-primary">{dayBookings.length}</p>
              </div>
            </div>
          </motion.div>

          <motion.div
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.3, delay: 0.1 }}
            className="bg-card border border-border-light rounded-xl p-4 backdrop-blur-2xl"
          >
            <div className="flex items-center gap-3">
              <User className="w-5 h-5 text-gold" />
              <div>
                <p className="text-text-secondary text-xs">Staff Working</p>
                <p className="text-lg font-bold text-text-primary">{staff.length}</p>
              </div>
            </div>
          </motion.div>

          <motion.div
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.3, delay: 0.2 }}
            className="bg-card border border-border-light rounded-xl p-4 backdrop-blur-2xl"
          >
            <div className="flex items-center gap-3">
              <div className="w-5 h-5 rounded-full bg-green-400" />
              <div>
                <p className="text-text-secondary text-xs">Completed</p>
                <p className="text-lg font-bold text-text-primary">{dayBookings.filter(b => b.status === 'completed').length}</p>
              </div>
            </div>
          </motion.div>

          <motion.div
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.3, delay: 0.3 }}
            className="bg-card border border-border-light rounded-xl p-4 backdrop-blur-2xl"
          >
            <div className="flex items-center gap-3">
              <div className="w-5 h-5 rounded-full bg-[#FFD700]" />
              <div>
                <p className="text-text-secondary text-xs">Pending</p>
                <p className="text-lg font-bold text-text-primary">{dayBookings.filter(b => b.status === 'pending').length}</p>
              </div>
            </div>
          </motion.div>
        </div>
      </div>
    </DashboardLayout>
  );
}
