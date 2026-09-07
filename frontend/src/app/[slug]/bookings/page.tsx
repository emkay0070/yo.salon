'use client';

import { useState, useEffect, use } from 'react';
import { useRouter } from 'next/navigation';
import DashboardLayout from '@/components/DashboardLayout';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useRole } from '@/contexts/RoleContext';
import { apiClient } from '@/lib/api-client';
import { ChevronLeft, ChevronRight, Calendar, Plus, Search, LayoutList } from 'lucide-react';
import TimelineView from '@/components/booking/TimelineView';
import ListView from '@/components/booking/ListView';
import QuickActions from '@/components/booking/QuickActions';
import BookingDetailsDrawer from '@/components/booking/BookingDetailsDrawer';
import RequestPaymentModal from '@/components/payments/RequestPaymentModal';
import WalkInModal from '@/components/booking/WalkInModal';
import RescheduleModal from '@/components/booking/RescheduleModal';
import AssignStaffModal from '@/components/booking/AssignStaffModal';
import FloatingQuickActions from '@/components/booking/FloatingQuickActions';
import { salonRoutes } from '@/lib/routes';

interface Booking {
  id: string;
  date: string;
  time: string;
  customerName: string;
  service: string;
  staffName: string;
  status: string;
  endTime: string;
  duration: number;
  price: number;
  phone: string;
  notes?: string;
}

interface Specialist {
  id: string;
  name: string;
}

export default function SalonBookingsPage({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = use(params);
  const router = useRouter();
  const queryClient = useQueryClient();
  const { role, userName, salonId, user, activeSalon, salonSlug } = useRole();
  const routes = salonRoutes(salonSlug);
  const [selectedBooking, setSelectedBooking] = useState<Booking | null>(null);
  const [isDrawerOpen, setIsDrawerOpen] = useState(false);
  const [viewMode, setViewMode] = useState<'timeline' | 'list'>('timeline');
  const [calendarView, setCalendarView] = useState<'day' | 'week' | 'month'>('day');
  const [selectedDate, setSelectedDate] = useState(new Date());
  const [paymentBooking, setPaymentBooking] = useState<{id: string, customerName: string, service: string, price: number} | null>(null);
  const [isPaymentModalOpen, setIsPaymentModalOpen] = useState(false);
  const [draggedBooking, setDraggedBooking] = useState<Booking | null>(null);
  const [isWalkInModalOpen, setIsWalkInModalOpen] = useState(false);
  const [isRescheduleModalOpen, setIsRescheduleModalOpen] = useState(false);
  const [isAssignStaffModalOpen, setIsAssignStaffModalOpen] = useState(false);

  // Verify user has access to this salon
  useEffect(() => {
    if (!user) {
      router.push(`/login?returnTo=/${slug}/bookings`);
      return;
    }

    if (!salonId) {
      console.warn('[SalonBookingsPage] User has no salon assignment');
      return;
    }

    // Verify the salon slug matches the user's active salon
    // Use activeSalon from RoleContext instead of API call to avoid network errors
    if (activeSalon && activeSalon.slug !== slug) {
      console.error('[SalonBookingsPage] Access denied: user salon does not match URL salon');
      router.push(routes.dashboard);
    }
  }, [salonId, user, router, slug, activeSalon]);

  const { data: apiBookings = [], isLoading } = useQuery({
    queryKey: ['bookings', salonId],
    queryFn: () => apiClient.getBookings({ salon_id: salonId! }),
    enabled: !!salonId,
  });

  const { data: apiSpecialists = [] } = useQuery({
    queryKey: ['specialists', salonId, slug],
    queryFn: () => apiClient.getSalonSpecialistsWithFallback(salonId || undefined, slug),
    enabled: !!salonId || !!slug,
  });

  console.log('[SalonBookingsPage] Salon ID:', salonId);
  console.log('[SalonBookingsPage] Salon Slug:', slug);
  console.log('[SalonBookingsPage] API Bookings:', apiBookings);
  console.log('[SalonBookingsPage] Is loading:', isLoading);

  const bookings: Booking[] = apiBookings.map((b: any) => {
    const bookingDate = b.date ? new Date(b.date) : new Date();
    
    const formatDate = (dateStr: string): string => {
      if (!dateStr) return '';
      const date = new Date(dateStr);
      return date.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
    };
    
    const formatTime = (timeStr: string): string => {
      if (!timeStr) return '';
      const time = new Date(timeStr);
      return time.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
    };
    
    return {
      id: b.id,
      customerName: b.customer?.name || 'Walk-in',
      service: b.service?.name || 'Service',
      staffName: b.specialist?.name || b.staff?.name || 'Unassigned',
      time: formatTime(b.time || (b.date ? bookingDate.toISOString() : '09:00')),
      date: formatDate(b.date || bookingDate.toISOString()),
      endTime: formatTime(b.date ? new Date(new Date(b.date).getTime() + (b.service?.duration || 30)*60000).toISOString() : '09:30'),
      duration: b.service?.duration || 30,
      status: b.status || 'confirmed',
      price: b.service?.price || 0,
      phone: b.customer?.phone || '',
      notes: b.notes,
    };
  });

  const specialists: Specialist[] = apiSpecialists.map((s: any) => ({
    id: s.id,
    name: s.name
  }));

  const handleBookingClick = (booking: Booking) => {
    setSelectedBooking(booking);
    setIsDrawerOpen(true);
  };

  const handleDragStart = (booking: Booking) => {
    setDraggedBooking(booking);
  };

  const handleDragEnd = () => {
    setDraggedBooking(null);
  };

  const handleDrop = async (time: string, date: Date) => {
    if (!draggedBooking) return;

    try {
      const newDate = date.toISOString().split('T')[0];
      await apiClient.updateBooking(draggedBooking.id, {
        date: newDate,
        time,
      });

      queryClient.invalidateQueries({ queryKey: ['bookings', salonId] });
    } catch (err) {
      console.error('Failed to reschedule booking:', err);
    }

    setDraggedBooking(null);
  };

  const handleNewBooking = () => {
    router.push(`/${slug}/booking`);
  };

  const handleWalkIn = () => {
    setIsWalkInModalOpen(true);
  };

  const handleReschedule = () => {
    if (!selectedBooking) return;
    setIsRescheduleModalOpen(true);
  };

  const handleCancel = () => {
    if (!selectedBooking) return;
    if (confirm('Are you sure you want to cancel this booking?')) {
      updateBookingMutation.mutate({
        id: selectedBooking.id,
        data: { status: 'cancelled' },
      });
      setIsDrawerOpen(false);
    }
  };

  const handleAssignStaff = () => {
    if (!selectedBooking) return;
    setIsAssignStaffModalOpen(true);
  };

  const updateBookingMutation = useMutation({
    mutationFn: async ({ id, data }: { id: string; data: any }) => {
      return await apiClient.updateBooking(id, data);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['bookings', salonId] });
    },
    onError: (error) => {
      console.error('Failed to update booking:', error);
      alert('Failed to update booking. Please try again.');
    },
  });

  const handleCheckIn = () => {
    if (!selectedBooking) return;
    updateBookingMutation.mutate({
      id: selectedBooking.id,
      data: { status: 'checked_in' },
    });
    setIsDrawerOpen(false);
  };

  const handleStartService = () => {
    if (!selectedBooking) return;
    updateBookingMutation.mutate({
      id: selectedBooking.id,
      data: { status: 'in_service' },
    });
    setIsDrawerOpen(false);
  };

  const handleCompleteService = () => {
    if (!selectedBooking) return;
    updateBookingMutation.mutate({
      id: selectedBooking.id,
      data: { status: 'completed' },
    });
    setIsDrawerOpen(false);
  };

  const handleRequestPayment = (booking: Booking) => {
    setPaymentBooking(booking);
    setIsDrawerOpen(false);
    setIsPaymentModalOpen(true);
  };

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
    for (let i = 0; i < startingDayOfWeek; i++) {
      dates.push(null);
    }
    for (let i = 1; i <= daysInMonth; i++) {
      dates.push(new Date(year, month, i));
    }
    return dates;
  };

  const timeSlots = (() => {
    const slots = [];
    for (let hour = 9; hour <= 20; hour++) {
      slots.push(`${hour.toString().padStart(2, '0')}:00`);
      slots.push(`${hour.toString().padStart(2, '0')}:30`);
    }
    return slots;
  })();

  return (
    <DashboardLayout>
      <div className="max-w-7xl mx-auto p-6 font-sans space-y-6">

        {/* No salon assignment state */}
        {!salonId && user && (
          <div className="flex flex-col items-center justify-center py-24 text-center">
            <div className="w-16 h-16 rounded-full bg-gold/10 flex items-center justify-center mb-4">
              <Calendar className="w-8 h-8 text-gold" />
            </div>
            <h2 className="text-xl font-semibold text-text-primary mb-2">No Salon Access</h2>
            <p className="text-text-secondary mb-6 max-w-md">
              You don't have access to this salon's booking system.
            </p>
            <button
              onClick={() => router.push('/dashboard')}
              className="px-6 py-2.5 bg-gold text-black rounded-xl font-medium hover:bg-gold/90 transition-colors"
            >
              Dashboard
            </button>
          </div>
        )}

        {/* Booking management UI - only shown when salonId exists */}
        {salonId && (
          <>
            <div className="flex items-center justify-between">
          <div className="flex items-center gap-4">
            <button
              onClick={() => {
                if (calendarView === 'day') navigateDate(-1);
                else if (calendarView === 'week') navigateWeek(-1);
                else navigateMonth(-1);
              }}
              className="p-2 rounded-xl bg-card/80 backdrop-blur border border-border-light hover:bg-white/10 transition-colors"
            >
              <ChevronLeft className="w-5 h-5 text-text-primary" />
            </button>
            <div>
              <p className="text-text-secondary text-xs font-semibold uppercase tracking-widest mb-1">Bookings</p>
              <h1 className="text-2xl font-bold text-text-primary tracking-tight">
                {calendarView === 'month' ? formatMonthYear(selectedDate) : formatDate(selectedDate)}
              </h1>
            </div>
            <button
              onClick={() => {
                if (calendarView === 'day') navigateDate(1);
                else if (calendarView === 'week') navigateWeek(1);
                else navigateMonth(1);
              }}
              className="p-2 rounded-xl bg-card/80 backdrop-blur border border-border-light hover:bg-white/10 transition-colors"
            >
              <ChevronRight className="w-5 h-5 text-text-primary" />
            </button>
          </div>

          <div className="flex items-center gap-3">
            <div className="flex bg-card/80 backdrop-blur border border-border-light rounded-xl p-1">
              <button
                onClick={() => setCalendarView('day')}
                className={`px-4 py-2 rounded-lg text-sm font-medium transition-all ${
                  calendarView === 'day' ? 'bg-[#FFD700] text-obsidian' : 'text-text-secondary hover:text-text-primary hover:bg-white/5'
                }`}
              >
                Today
              </button>
              <button
                onClick={() => setCalendarView('week')}
                className={`px-4 py-2 rounded-lg text-sm font-medium transition-all ${
                  calendarView === 'week' ? 'bg-[#FFD700] text-obsidian' : 'text-text-secondary hover:text-text-primary hover:bg-white/5'
                }`}
              >
                Week
              </button>
              <button
                onClick={() => setCalendarView('month')}
                className={`px-4 py-2 rounded-lg text-sm font-medium transition-all ${
                  calendarView === 'month' ? 'bg-[#FFD700] text-obsidian' : 'text-text-secondary hover:text-text-primary hover:bg-white/5'
                }`}
              >
                Month
              </button>
            </div>

            <div className="relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-text-secondary" />
              <input
                type="text"
                placeholder="Search bookings..."
                className="pl-10 pr-4 py-2 bg-card/80 backdrop-blur border border-border-light rounded-xl text-text-primary placeholder:text-text-secondary text-sm focus:outline-none focus:border-[rgba(255,215,0,0.4)] transition-colors w-48"
              />
            </div>
          </div>
        </div>

        {/* Status Line */}
        <div className="text-text-secondary text-sm">
          {bookings.length} bookings today • {bookings.filter(b => b.status === 'confirmed').length} waiting • {bookings.filter(b => b.status === 'completed').length} completed
        </div>

        {/* Main Content Grid */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Calendar - takes 2 columns */}
          <div className="lg:col-span-2 bg-card/80 backdrop-blur-xl border border-border-light rounded-2xl overflow-hidden min-h-[600px]">
          {calendarView === 'day' && (
            <TimelineView
              bookings={bookings}
              staff={specialists}
              onBookingClick={handleBookingClick}
              currentUserRole={role}
              currentUserName={userName}
            />
          )}
          {calendarView === 'week' && (
            <div className="h-[600px] overflow-auto p-4">
              <div className="w-full">
                {/* Week Header */}
                <div className="grid grid-cols-8 border-b border-border-light bg-white/[0.02] sticky top-0 z-10">
                  <div className="p-3 text-text-secondary text-xs font-medium border-r border-border-light">Time</div>
                  {getWeekDates(selectedDate).map((date, index) => {
                    const isToday = date.toDateString() === new Date().toDateString();
                    return (
                      <div key={index} className="p-3 text-center border-r border-border-light last:border-r-0">
                        <div className="text-text-primary font-semibold text-xs">{date.toLocaleDateString('en-US', { weekday: 'short' })}</div>
                        <div className={`text-lg font-bold ${isToday ? 'text-gold' : 'text-text-secondary'}`}>
                          {date.getDate()}
                        </div>
                      </div>
                    );
                  })}
                </div>

                {/* Week Time Slots */}
                {timeSlots.map((time, index) => (
                  <div key={time} className={`grid grid-cols-8 border-b border-white/5 ${index % 2 === 0 ? 'bg-white/[0.02]' : ''}`}>
                    <div className="p-2 text-text-secondary text-xs border-r border-border-light flex items-center justify-center font-medium">
                      {time}
                    </div>
                    {getWeekDates(selectedDate).map((date, dateIndex) => {
                      const dateBookings = bookings.filter(b => {
                        const bookingDate = new Date(b.date);
                        return bookingDate.toDateString() === date.toDateString();
                      });
                      const booking = dateBookings.find(b => b.time === time);

                      return (
                        <div
                          key={dateIndex}
                          className="p-1 border-r border-border-light last:border-r-0 min-h-[50px] hover:bg-card transition-colors cursor-pointer"
                          onDragOver={(e) => e.preventDefault()}
                          onDrop={() => handleDrop(time, date)}
                        >
                          {booking ? (
                            <div
                              draggable
                              onDragStart={() => handleDragStart(booking)}
                              onDragEnd={handleDragEnd}
                              onClick={() => handleBookingClick(booking)}
                              className={`rounded-lg p-1.5 border-2 cursor-pointer hover:scale-105 transition-transform ${
                                booking.status === 'completed' ? 'bg-green-500/20 border-green-500/50' :
                                  booking.status === 'cancelled' ? 'bg-red-500/20 border-red-500/50' :
                                  'bg-[#FFD700]/20 border-[#FFD700]/50'
                              } ${draggedBooking?.id === booking.id ? 'opacity-50' : ''}`}
                            >
                              <p className="text-text-primary font-semibold text-[10px] truncate">{booking.customerName}</p>
                              <p className="text-text-secondary text-[8px] truncate">{booking.service}</p>
                            </div>
                          ) : (
                            <div
                              onClick={() => {
                                setSelectedDate(date);
                                router.push(`/${slug}/booking`);
                              }}
                              className="h-full flex items-center justify-center opacity-0 hover:opacity-100 transition-opacity cursor-pointer"
                            >
                              <Plus className="w-3 h-3 text-text-secondary" />
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
          {calendarView === 'month' && (
            <div className="h-[600px] overflow-auto p-4">
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

                  const dateBookings = bookings.filter(b => {
                    const bookingDate = new Date(b.date);
                    return bookingDate.toDateString() === date.toDateString();
                  });
                  const isToday = date.toDateString() === new Date().toDateString();
                  const isSelected = date.toDateString() === selectedDate.toDateString();

                  return (
                    <div
                      key={index}
                      onClick={() => setSelectedDate(date)}
                      onDragOver={(e) => e.preventDefault()}
                      onDrop={() => handleDrop('09:00', date)}
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
                            draggable
                            onDragStart={() => handleDragStart(booking)}
                            onDragEnd={handleDragEnd}
                            onClick={() => handleBookingClick(booking)}
                            className={`text-[10px] px-1 py-0.5 rounded truncate cursor-pointer hover:scale-105 transition-transform ${
                              booking.status === 'completed' ? 'bg-green-500/20 text-green-300' :
                                booking.status === 'cancelled' ? 'bg-red-500/20 text-red-300' :
                                'bg-[#FFD700]/20 text-gold'
                            } ${draggedBooking?.id === booking.id ? 'opacity-50' : ''}`}
                          >
                            {booking.time} {booking.customerName}
                          </div>
                        ))}
                        {dateBookings.length > 3 && (
                          <div className="text-[10px] text-text-secondary">
                            +{dateBookings.length - 3} more
                          </div>
                        )}
                        {dateBookings.length === 0 && (
                          <div
                            onClick={(e) => {
                              e.stopPropagation();
                              setSelectedDate(date);
                              router.push(`/${slug}/booking`);
                            }}
                            className="text-[10px] text-text-secondary opacity-0 hover:opacity-100 cursor-pointer text-center py-1"
                          >
                            + Add
                          </div>
                        )}
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>
          )}
          </div>

          {/* Sidebar - Upcoming Appointments */}
          <div className="bg-card/80 backdrop-blur-xl border border-border-light rounded-2xl p-5">
            <h3 className="text-text-primary font-semibold mb-4">Upcoming</h3>
            <div className="space-y-4">
              {bookings.slice(0, 5).map((booking) => (
                <div
                  key={booking.id}
                  onClick={() => handleBookingClick(booking)}
                  className="cursor-pointer group"
                >
                  <p className="text-gold font-semibold text-lg">{booking.time}</p>
                  <p className="text-text-primary font-medium mt-1 group-hover:text-gold transition-colors">{booking.customerName}</p>
                  <p className="text-text-secondary text-sm mt-1">{booking.service}</p>
                  <p className="text-text-secondary text-xs mt-1">{booking.staffName}</p>
                  <div className="h-px bg-border-light mt-3"></div>
                </div>
              ))}
              {bookings.length === 0 && (
                <p className="text-text-secondary text-sm text-center py-4">No upcoming appointments</p>
              )}
            </div>
          </div>
        </div>

        {/* Modals */}
        {selectedBooking && (
          <BookingDetailsDrawer
            booking={selectedBooking}
            isOpen={isDrawerOpen}
            onClose={() => setIsDrawerOpen(false)}
            onCheckIn={handleCheckIn}
            onStartService={handleStartService}
            onCompleteService={handleCompleteService}
            onCancel={handleCancel}
            onReschedule={handleReschedule}
            onAssignStaff={handleAssignStaff}
            currentUserRole={role}
          />
        )}

        <RequestPaymentModal
          isOpen={isPaymentModalOpen}
          onClose={() => setIsPaymentModalOpen(false)}
          bookingId={paymentBooking?.id}
          customerName={paymentBooking?.customerName}
          serviceName={paymentBooking?.service}
          amount={paymentBooking?.price}
        />

        <WalkInModal
          isOpen={isWalkInModalOpen}
          onClose={() => setIsWalkInModalOpen(false)}
          onSuccess={() => queryClient.invalidateQueries({ queryKey: ['bookings', salonId] })}
        />

        {selectedBooking && (
          <RescheduleModal
            isOpen={isRescheduleModalOpen}
            onClose={() => setIsRescheduleModalOpen(false)}
            booking={selectedBooking}
            onSuccess={async () => {
              // Reschedule logic handled inside the modal
              queryClient.invalidateQueries({ queryKey: ['bookings', salonId] });
              setIsRescheduleModalOpen(false);
            }}
          />
        )}

        {selectedBooking && (
          <AssignStaffModal
            isOpen={isAssignStaffModalOpen}
            onClose={() => setIsAssignStaffModalOpen(false)}
            booking={selectedBooking}
            onSuccess={async () => {
              queryClient.invalidateQueries({ queryKey: ['bookings', salonId] });
              setIsAssignStaffModalOpen(false);
            }}
          />
        )}

        <FloatingQuickActions
          onNewBooking={handleNewBooking}
          onWalkIn={handleWalkIn}
          onReschedule={handleReschedule}
          onCancel={handleCancel}
          onAssignStaff={handleAssignStaff}
        />
          </>
        )}
      </div>
    </DashboardLayout>
  );
}
