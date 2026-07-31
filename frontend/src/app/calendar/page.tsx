'use client';

import { useEffect, useState } from 'react';
import {
  ChevronLeft,
  ChevronRight,
  Plus,
  User,
  Clock,
  Search,
} from 'lucide-react';
import { apiClient } from '@/lib/api-client';
import { motion } from 'framer-motion';
import DashboardLayout from '@/components/DashboardLayout';
import { useRole } from '@/contexts/RoleContext';

interface Booking {
  id: string;
  date: string;
  time: string;
  status: string;
  customer: {
    name: string;
    phone: string;
  };
  services: Array<{
    name: string;
    price: number;
    duration: number;
  }>;
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

type ViewMode = 'day' | 'week' | 'month';

export default function CalendarPage() {
  const [bookings, setBookings] = useState<Booking[]>([]);
  const [staff, setStaff] = useState<Staff[]>([]);
  const [selectedDate, setSelectedDate] = useState(new Date());
  const [viewMode, setViewMode] = useState<ViewMode>('day');
  const [error, setError] = useState<string | null>(null);
  const { salonId } = useRole();

  useEffect(() => {
    async function loadData() {
      try {
        const [bookingsData, staffData] = await Promise.all([
          apiClient.getBookings({ salon_id: salonId }),
          apiClient.getStaff({ salon_id: salonId }),
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

    if (salonId) {
      loadData();
    }
  }, [salonId]);

  const navigateDate = (days: number) => {
    const newDate = new Date(selectedDate);
    newDate.setDate(newDate.getDate() + days);
    setSelectedDate(newDate);
  };

  const navigateWeek = (weeks: number) => {
    const newDate = new Date(selectedDate);
    newDate.setDate(newDate.getDate() + (weeks * 7));
    setSelectedDate(newDate);
  };

  const navigateMonth = (months: number) => {
    const newDate = new Date(selectedDate);
    newDate.setMonth(newDate.getMonth() + months);
    setSelectedDate(newDate);
  };

  const goToToday = () => {
    setSelectedDate(new Date());
  };

  const getWeekDates = (date: Date) => {
    const start = new Date(date);
    const day = start.getDay();
    start.setDate(start.getDate() - day);

    const weekDates = [];
    for (let i = 0; i < 7; i++) {
      const d = new Date(start);
      d.setDate(start.getDate() + i);
      weekDates.push(d);
    }
    return weekDates;
  };

  const getMonthDates = (date: Date) => {
    const year = date.getFullYear();
    const month = date.getMonth();
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const daysInMonth = lastDay.getDate();
    const startingDayOfWeek = firstDay.getDay();

    const dates = [];
    // Add empty cells for days before the first day of the month
    for (let i = 0; i < startingDayOfWeek; i++) {
      dates.push(null);
    }
    // Add days of the month
    for (let i = 1; i <= daysInMonth; i++) {
      dates.push(new Date(year, month, i));
    }
    return dates;
  };

  const getBookingsForDate = (date: Date) => {
    return bookings.filter(booking => {
      const bookingDate = new Date(booking.date);
      return bookingDate.toDateString() === date.toDateString();
    });
  };

  const formatDate = (date: Date) => {
    return date.toLocaleDateString('en-US', { 
      weekday: 'long', 
      day: 'numeric', 
      month: 'long' 
    });
  };

  const formatMonthYear = (date: Date) => {
    return date.toLocaleDateString('en-US', { 
      month: 'long', 
      year: 'numeric' 
    });
  };

  const getTotalPrice = (booking: Booking) => {
    return booking.services.reduce((sum, s) => sum + s.price, 0);
  };

  const getServiceNames = (booking: Booking) => {
    return booking.services.map(s => s.name).join(', ');
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'completed':
        return 'bg-green-500/20 border-green-500/50';
      case 'cancelled':
        return 'bg-red-500/20 border-red-500/50';
      case 'checked_in':
        return 'bg-blue-500/20 border-blue-500/50';
      case 'in_service':
        return 'bg-purple-500/20 border-purple-500/50';
      default:
        return 'bg-[#FFD700]/20 border-[#FFD700]/50';
    }
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
              onClick={() => {
                if (viewMode === 'day') navigateDate(-1);
                else if (viewMode === 'week') navigateWeek(-1);
                else navigateMonth(-1);
              }}
              className="p-2 rounded-xl bg-card hover:bg-white/10 transition-colors shrink-0"
            >
              <ChevronLeft className="w-5 h-5 text-text-primary" />
            </button>
            <div className="flex-1 sm:flex-none">
              <h1 className="text-2xl font-semibold text-text-primary tracking-tight">
                {viewMode === 'month' ? formatMonthYear(selectedDate) : formatDate(selectedDate)}
              </h1>
              <p className="text-text-secondary text-sm mt-1">Schedule</p>
            </div>
            <button
              onClick={() => {
                if (viewMode === 'day') navigateDate(1);
                else if (viewMode === 'week') navigateWeek(1);
                else navigateMonth(1);
              }}
              className="p-2 rounded-xl bg-card hover:bg-white/10 transition-colors shrink-0"
            >
              <ChevronRight className="w-5 h-5 text-text-primary" />
            </button>
          </div>

          <div className="flex items-center gap-2 sm:gap-3 w-full sm:w-auto flex-wrap">
            {/* View Mode Toggles */}
            <div className="flex bg-card border border-border-light rounded-xl p-1 shrink-0">
              <button
                onClick={() => setViewMode('day')}
                className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-all ${
                  viewMode === 'day' ? 'bg-[#FFD700] text-obsidian' : 'text-text-secondary hover:text-text-primary hover:bg-white/5'
                }`}
              >
                Day
              </button>
              <button
                onClick={() => setViewMode('week')}
                className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-all ${
                  viewMode === 'week' ? 'bg-[#FFD700] text-obsidian' : 'text-text-secondary hover:text-text-primary hover:bg-white/5'
                }`}
              >
                Week
              </button>
              <button
                onClick={() => setViewMode('month')}
                className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-all ${
                  viewMode === 'month' ? 'bg-[#FFD700] text-obsidian' : 'text-text-secondary hover:text-text-primary hover:bg-white/5'
                }`}
              >
                Month
              </button>
            </div>

            <button
              onClick={goToToday}
              className="px-3 py-2 bg-card border border-border-light rounded-xl text-text-secondary hover:text-text-primary hover:border-[rgba(255,215,0,0.4)] transition-all text-sm font-medium shrink-0"
            >
              Today
            </button>

            <div className="relative w-full sm:w-64 shrink-0">
              <Search className="w-5 h-5 text-text-secondary absolute left-3 top-1/2 -translate-y-1/2" />
              <input
                type="text"
                placeholder="Search booking..."
                className="pl-10 pr-4 py-2 bg-card border border-border-light rounded-xl text-text-primary placeholder-[#A0A0A0] focus:outline-none focus:border-[rgba(255,215,0,0.4)] transition-colors w-full"
              />
            </div>
            <button className="flex items-center gap-2 px-3 sm:px-4 py-2 bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-obsidian rounded-xl font-medium hover:opacity-90 transition-opacity text-sm shrink-0">
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
          className="flex-1 bg-card border border-border-light rounded-2xl backdrop-blur-2xl overflow-hidden"
        >
          {viewMode === 'day' && (
            <div className="h-full overflow-auto">
              <div className="min-w-[800px]">
                {/* Staff Headers */}
                <div className="flex border-b border-border-light bg-white/[0.02] sticky top-0 z-10">
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
                <div>
                  {timeSlots.map((time, index) => {
                    const slotBookings = getBookingsForDate(selectedDate).filter(b => b.time === time);
                    
                    return (
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
                          const booking = slotBookings.find(b => b.staff?.id === staffMember.id);

                          return (
                            <div
                              key={staffMember.id}
                              className="flex-1 p-2 border-r border-border-light last:border-r-0 min-h-[70px] hover:bg-card transition-colors cursor-pointer relative"
                            >
                              {booking ? (
                                <motion.div
                                  initial={{ opacity: 0, scale: 0.95 }}
                                  animate={{ opacity: 1, scale: 1 }}
                                  transition={{ duration: 0.2 }}
                                  className={`rounded-xl p-3 border-2 ${getStatusColor(booking.status)}`}
                                >
                                  <div className="flex items-start gap-3">
                                    <div className="w-8 h-8 rounded-full bg-gradient-to-br from-[rgba(255,215,0,0.3)] to-[rgba(255,215,0,0.1)] flex items-center justify-center flex-shrink-0">
                                      <User className="w-4 h-4 text-gold" />
                                    </div>
                                    <div className="flex-1 min-w-0">
                                      <p className="text-text-primary font-semibold text-sm truncate">{booking.customer.name}</p>
                                      <p className="text-text-secondary text-xs truncate">{getServiceNames(booking)}</p>
                                      <div className="flex items-center gap-2 mt-2">
                                        <span className="text-gold text-xs font-semibold">{getTotalPrice(booking).toLocaleString()} UGX</span>
                                        <span className="text-text-secondary text-xs">•</span>
                                        <span className="text-text-secondary text-xs">{booking.services.reduce((sum, s) => sum + s.duration, 0)} min</span>
                                      </div>
                                    </div>
                                  </div>
                                </motion.div>
                              ) : (
                                <div className="h-full flex items-center justify-center opacity-0 hover:opacity-100 transition-opacity">
                                  <Plus className="w-6 h-6 text-text-secondary" />
                                </div>
                              )}
                            </div>
                          );
                        })}
                      </div>
                    );
                  })}
                </div>
              </div>
            </div>
          )}

          {viewMode === 'week' && (
            <div className="h-full overflow-auto">
              <div className="min-w-[1200px]">
                {/* Week Header */}
                <div className="grid grid-cols-8 border-b border-border-light bg-white/[0.02] sticky top-0 z-10">
                  <div className="p-4 text-text-secondary text-sm font-medium border-r border-border-light">Time</div>
                  {getWeekDates(selectedDate).map((date, index) => {
                    const isToday = date.toDateString() === new Date().toDateString();
                    return (
                      <div key={index} className="p-4 text-center border-r border-border-light last:border-r-0">
                        <div className="text-text-primary font-semibold">{date.toLocaleDateString('en-US', { weekday: 'short' })}</div>
                        <div className={`text-2xl font-bold ${isToday ? 'text-gold' : 'text-text-secondary'}`}>
                          {date.getDate()}
                        </div>
                      </div>
                    );
                  })}
                </div>

                {/* Week Time Slots */}
                {timeSlots.map((time, index) => (
                  <div key={time} className={`grid grid-cols-8 border-b border-white/5 ${index % 2 === 0 ? 'bg-white/[0.02]' : ''}`}>
                    <div className="p-4 text-text-secondary text-sm border-r border-border-light flex items-center justify-center font-medium">
                      {time}
                    </div>
                    {getWeekDates(selectedDate).map((date, dateIndex) => {
                      const dateBookings = getBookingsForDate(date);
                      const booking = dateBookings.find(b => b.time === time);

                      return (
                        <div key={dateIndex} className="p-2 border-r border-border-light last:border-r-0 min-h-[60px] hover:bg-card transition-colors cursor-pointer">
                          {booking ? (
                            <div className={`rounded-lg p-2 border-2 ${getStatusColor(booking.status)}`}>
                              <p className="text-text-primary font-semibold text-xs truncate">{booking.customer.name}</p>
                              <p className="text-text-secondary text-[10px] truncate">{getServiceNames(booking)}</p>
                            </div>
                          ) : (
                            <div className="h-full flex items-center justify-center opacity-0 hover:opacity-100 transition-opacity">
                              <Plus className="w-4 h-4 text-text-secondary" />
                            </div>
                          )}
                        </div>
                      );
                    })}
                  </div>
                ))}
              </div>
            </div>
          )}

          {viewMode === 'month' && (
            <div className="h-full overflow-auto p-4">
              <div className="grid grid-cols-7 gap-2">
                {/* Day Headers */}
                {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map(day => (
                  <div key={day} className="text-center text-text-secondary text-sm font-medium p-2">
                    {day}
                  </div>
                ))}

                {/* Month Days */}
                {getMonthDates(selectedDate).map((date, index) => {
                  if (!date) {
                    return <div key={index} className="aspect-square" />;
                  }

                  const dateBookings = getBookingsForDate(date);
                  const isToday = date.toDateString() === new Date().toDateString();
                  const isSelected = date.toDateString() === selectedDate.toDateString();

                  return (
                    <div
                      key={index}
                      onClick={() => setSelectedDate(date)}
                      className={`aspect-square rounded-xl border p-2 cursor-pointer transition-all hover:scale-105 ${
                        isSelected ? 'border-[#FFD700] bg-[#FFD700]/10' :
                          isToday ? 'border-[#FFD700]/50 bg-white/5' :
                          'border-border-light bg-card'
                      }`}
                    >
                      <div className={`text-sm font-semibold ${isToday ? 'text-gold' : 'text-text-primary'}`}>
                        {date.getDate()}
                      </div>
                      <div className="mt-1 space-y-1">
                        {dateBookings.slice(0, 3).map(booking => (
                          <div
                            key={booking.id}
                            className={`text-[10px] px-1 py-0.5 rounded truncate ${getStatusColor(booking.status)}`}
                          >
                            {booking.time} {booking.customer.name}
                          </div>
                        ))}
                        {dateBookings.length > 3 && (
                          <div className="text-[10px] text-text-secondary">
                            +{dateBookings.length - 3} more
                          </div>
                        )}
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>
          )}
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
                <p className="text-lg font-bold text-text-primary">{getBookingsForDate(selectedDate).length}</p>
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
                <p className="text-lg font-bold text-text-primary">{getBookingsForDate(selectedDate).filter(b => b.status === 'completed').length}</p>
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
                <p className="text-lg font-bold text-text-primary">{getBookingsForDate(selectedDate).filter(b => b.status === 'pending' || b.status === 'pending_payment').length}</p>
              </div>
            </div>
          </motion.div>
        </div>
      </div>
    </DashboardLayout>
  );
}
