'use client';

import { useState, useEffect, use } from 'react';
import { useRouter } from 'next/navigation';
import { motion, AnimatePresence } from 'framer-motion';
import DashboardLayout from '@/components/DashboardLayout';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useRole } from '@/contexts/RoleContext';
import { apiClient } from '@/lib/api-client';
import { ChevronLeft, ChevronRight, Calendar, Plus, Search, LayoutList, CheckCircle, User, Clock, Filter } from 'lucide-react';
import TimelineView from '@/components/booking/TimelineView';
import ListView from '@/components/booking/ListView';
import QuickActions from '@/components/booking/QuickActions';
import BookingDetailsDrawer from '@/components/booking/BookingDetailsDrawer';
import RequestPaymentModal from '@/components/payments/RequestPaymentModal';
import WalkInModal from '@/components/booking/WalkInModal';
import ResolutionModal from '@/components/booking/ResolutionModal';
import AssignStaffModal from '@/components/booking/AssignStaffModal';
import RescheduleModal from '@/components/booking/RescheduleModal';
import FloatingQuickActions from '@/components/booking/FloatingQuickActions';
import { providerRoutes } from '@/lib/routes';
import { resolveBusinessTimezone, formatDateKey } from '@/lib/timezone';

interface Booking {
  id: string;
  date: string;
  originalDate?: string; // YYYY-MM-DD for proper date comparisons
  time: string;
  customerName: string;
  service: string;
  staffName: string;
  status: string; // Lifecycle status
  operational_state?: string; // Operational state
  grace_period_started_at?: string | null;
  grace_period_ends_at?: string | null;
  endTime: string;
  duration: number;
  price: number;
  phone: string;
  notes?: string;
  resolution?: {
    outcome: string;
    reason_code: string;
    notes?: string;
    follow_up_required: boolean;
    follow_up_status?: string;
  } | null;
}

interface Specialist {
  id: string;
  name: string;
}

export default function ProviderBookingsPage({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = use(params);
  const router = useRouter();
  const queryClient = useQueryClient();
  const { role, userName, providerId, locationId, user, activeProvider, providerSlug } = useRole();
  const routes = providerRoutes(providerSlug);
  const [selectedBooking, setSelectedBooking] = useState<Booking | null>(null);
  const [isDrawerOpen, setIsDrawerOpen] = useState(false);
  const [viewMode, setViewMode] = useState<'timeline' | 'list'>('timeline');
  const [calendarView, setCalendarView] = useState<'day' | 'work-week' | 'week' | 'month'>('day');
  const [selectedDate, setSelectedDate] = useState(new Date());
  const [paymentBooking, setPaymentBooking] = useState<{id: string, customerName: string, service: string, price: number} | null>(null);
  const [isPaymentModalOpen, setIsPaymentModalOpen] = useState(false);
  const [draggedBooking, setDraggedBooking] = useState<Booking | null>(null);
  const [isWalkInModalOpen, setIsWalkInModalOpen] = useState(false);
  const [isRescheduleModalOpen, setIsRescheduleModalOpen] = useState(false);
  const [isResolutionModalOpen, setIsResolutionModalOpen] = useState(false);
  const [isAssignStaffModalOpen, setIsAssignStaffModalOpen] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [peopleFilter, setPeopleFilter] = useState<'everyone' | 'unassigned' | string>('everyone');
  const [statusFilter, setStatusFilter] = useState<'all' | 'upcoming' | 'active' | 'needs-attention'>('all');
  const [isMobileUpcomingOpen, setIsMobileUpcomingOpen] = useState(false);

  // Verify user has access to this provider
  useEffect(() => {
    if (!user) {
      router.push(`/login?returnTo=/${slug}/bookings`);
      return;
    }

    if (!providerId) {
      console.warn('[ProviderBookingsPage] User has no provider assignment');
      return;
    }

    // Verify the provider slug matches the user's active provider
    if (activeProvider && activeProvider.slug !== slug) {
      console.error('[ProviderBookingsPage] Access denied: user provider does not match URL provider', {
        userProviderSlug: activeProvider.slug,
        urlSlug: slug,
      });
      router.push(`/${activeProvider.slug}/bookings`);
    }
  }, [providerId, user, router, slug, activeProvider]);

  const { data: apiBookings = [], isLoading } = useQuery({
    queryKey: ['bookings', providerId, locationId],
    queryFn: () => apiClient.getBookings(),
    enabled: !!providerId,
    refetchInterval: 15_000,
    refetchIntervalInBackground: false,
  });

  // Fetch provider data to get timezone
  const { data: providerData } = useQuery({
    queryKey: ['provider', slug],
    queryFn: () => apiClient.getProviderBySlug(slug),
    enabled: !!slug,
  });

  const { data: apiSpecialists = [] } = useQuery({
    queryKey: ['specialists', providerId, slug],
    queryFn: () => apiClient.getAssignableSpecialists(slug, locationId || undefined),
    enabled: !!providerId || !!slug,
  });

  const { data: apiNeedsResolution = [] } = useQuery({
    queryKey: ['bookings-needs-resolution', providerId, locationId],
    queryFn: () => apiClient.getBookingsNeedingResolution(),
    enabled: !!providerId,
    refetchInterval: 30_000,
    refetchIntervalInBackground: false,
  });

  console.log('[ProviderBookingsPage] Provider ID:', providerId);
  console.log('[ProviderBookingsPage] Provider Slug:', slug);
  console.log('[ProviderBookingsPage] API Bookings:', apiBookings);
  console.log('[ProviderBookingsPage] Is loading:', isLoading);
  console.log('[ProviderBookingsPage] Provider Data:', providerData);

  // Resolve business timezone from provider/location
  const businessTimezone = providerData?.timezone || 'Africa/Kampala';
  console.log('[ProviderBookingsPage] Business Timezone:', businessTimezone);

  const filteredBookings = apiBookings.filter((b: any) => {
    // Search filter
    if (searchQuery) {
      const query = searchQuery.toLowerCase();
      const customerName = (b.customer?.name || '').toLowerCase();
      const serviceName = (b.service?.name || '').toLowerCase();
      const staffName = (b.specialist?.name || b.staff?.name || '').toLowerCase();
      if (!customerName.includes(query) && !serviceName.includes(query) && !staffName.includes(query)) {
        return false;
      }
    }

    // People filter
    if (peopleFilter === 'unassigned') {
      if (b.specialist?.name || b.staff?.name) return false;
    } else if (peopleFilter !== 'everyone') {
      const staffName = b.specialist?.name || b.staff?.name || '';
      if (staffName !== peopleFilter) return false;
    }

    // Status filter
    if (statusFilter === 'upcoming') {
      if (b.status !== 'confirmed' && b.status !== 'pending') return false;
      // Exclude bookings that need resolution (past their grace period)
      if (b.operational_state === 'needs_resolution') return false;
    } else if (statusFilter === 'active') {
      if (b.status !== 'checked_in' && b.status !== 'in_service') return false;
    } else if (statusFilter === 'needs-attention') {
      if (b.operational_state !== 'needs_resolution') return false;
    }

    return true;
  });

  const bookings: Booking[] = filteredBookings.map((b: any) => {
    // b.date is "YYYY-MM-DD", b.time is "HH:MM" — never full ISO datetimes.
    // Replacing "-" with "/" in the date string prevents UTC offset from shifting
    // the date backward by one day on engines that parse YYYY-MM-DD as UTC midnight.
    const formatDate = (dateStr: string): string => {
      if (!dateStr) return '';
      const date = new Date(dateStr.replace(/-/g, '/'));
      if (isNaN(date.getTime())) return dateStr;
      return date.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
    };

    // Use canonical timezone-resolved data from backend
    const scheduled = b.scheduled || {
      date: b.date,
      start_time: b.time,
      end_time: b.end_time,
      timezone: businessTimezone,
    };

    // b.time is a plain "HH:MM" or "HH:MM:SS" string — not a parseable datetime.
    // Format it directly without constructing a Date object.
    const formatTime = (timeStr: string): string => {
      if (!timeStr) return '';
      // Already a formatted time (e.g. "09:30 AM") — return as-is
      if (timeStr.includes('AM') || timeStr.includes('PM')) return timeStr;
      const [hStr, mStr] = timeStr.split(':');
      const h = parseInt(hStr, 10);
      const m = mStr ?? '00';
      if (isNaN(h)) return timeStr;
      const suffix = h >= 12 ? 'PM' : 'AM';
      const h12 = h % 12 === 0 ? 12 : h % 12;
      return `${h12.toString().padStart(2, '0')}:${m.padStart(2, '0')} ${suffix}`;
    };

    // Use canonical scheduled date for comparisons
    const originalDate = scheduled.date ?? '';

    return {
      id: b.id,
      customerName: b.customer?.name || 'Walk-in',
      service: b.service?.name || 'Service',
      staffName: b.specialist?.name || b.staff?.name || 'Unassigned',
      time: formatTime(scheduled.start_time ?? ''),
      date: formatDate(originalDate),
      originalDate: originalDate, // Keep YYYY-MM-DD for comparisons
      endTime: formatTime(scheduled.end_time ?? ''),
      duration: b.service?.duration || 30,
      status: b.status || 'confirmed',
      operational_state: b.operational_state,
      grace_period_started_at: b.grace_period_started_at,
      grace_period_ends_at: b.grace_period_ends_at,
      price: b.service?.price || 0,
      phone: b.customer?.phone || '',
      notes: b.notes,
      resolution: b.resolution,
      // Store canonical timezone for reference
      timezone: scheduled.timezone,
    };
  });


  // Fallback: if no specialists from API, create from unique staffNames in bookings
  const specialists: Specialist[] = Array.isArray(apiSpecialists) && apiSpecialists.length > 0
    ? apiSpecialists.map((s: any) => ({
        id: s.id,
        name: s.name
      }))
    : (() => {
        const uniqueStaffNames = new Set(bookings.map(b => b.staffName).filter(Boolean));
        return Array.from(uniqueStaffNames).map(name => ({
          id: name.toLowerCase().replace(/\s+/g, '-'),
          name
        }));
      })();

  console.log('[ProviderBookingsPage] Specialists:', specialists);
  console.log('[ProviderBookingsPage] Bookings:', bookings);
  console.log('[ProviderBookingsPage] Bookings staffNames:', bookings.map(b => ({ id: b.id, staffName: b.staffName, time: b.time })));

  const bookingsNeedingResolution: Booking[] = Array.isArray(apiNeedsResolution) ? apiNeedsResolution.map((b: any) => {
    const formatDate = (dateStr: string): string => {
      if (!dateStr) return '';
      const date = new Date(dateStr.replace(/-/g, '/'));
      if (isNaN(date.getTime())) return dateStr;
      return date.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
    };

    const formatTime = (timeStr: string): string => {
      if (!timeStr) return '';
      if (timeStr.includes('AM') || timeStr.includes('PM')) return timeStr;
      const [hStr, mStr] = timeStr.split(':');
      const h = parseInt(hStr, 10);
      const m = mStr ?? '00';
      if (isNaN(h)) return timeStr;
      const suffix = h >= 12 ? 'PM' : 'AM';
      const h12 = h % 12 === 0 ? 12 : h % 12;
      return `${h12.toString().padStart(2, '0')}:${m.padStart(2, '0')} ${suffix}`;
    };

    return {
      id: b.id,
      customerName: b.customer?.name || 'Walk-in',
      service: b.service?.name || 'Service',
      staffName: b.specialist?.name || b.staff?.name || 'Unassigned',
      time: formatTime(b.time ?? ''),
      date: formatDate(b.date ?? ''),
      endTime: '',
      duration: b.service?.duration || 30,
      status: b.status || 'confirmed',
      operational_state: b.operational_state,
      grace_period_started_at: b.grace_period_started_at,
      grace_period_ends_at: b.grace_period_ends_at,
      price: b.service?.price || 0,
      phone: b.customer?.phone || '',
      notes: b.notes,
      resolution: b.resolution,
    };
  }) : [];

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

      queryClient.invalidateQueries({ queryKey: ['bookings', providerId, locationId] });
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

  const handleResolve = () => {
    if (!selectedBooking) return;
    setIsResolutionModalOpen(true);
  };

  const updateBookingMutation = useMutation({
    mutationFn: async ({ id, data }: { id: string; data: any }) => {
      return await apiClient.updateBooking(id, data);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['bookings', providerId, locationId] });
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

  const formatCompactDate = (dateStr: string): string => {
    const date = new Date(dateStr.replace(/-/g, '/'));
    if (isNaN(date.getTime())) return dateStr;

    const today = new Date();
    const tomorrow = new Date(today);
    tomorrow.setDate(tomorrow.getDate() + 1);

    const isToday = date.toDateString() === today.toDateString();
    const isTomorrow = date.toDateString() === tomorrow.toDateString();

    const day = date.getDate();
    const month = date.toLocaleDateString('en-US', { month: 'short' });
    const weekday = date.toLocaleDateString('en-US', { weekday: 'short' });

    if (isToday) return `Today, ${day} ${month}`;
    if (isTomorrow) return `Tomorrow, ${day} ${month}`;
    return `${weekday}, ${day} ${month}`;
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
    console.log('[getWeekDates] Input date:', date.toDateString(), 'Output dates:', weekDates.map(d => d.toDateString()));
    return weekDates;
  };

  const getWorkWeekDates = (date: Date) => {
    const start = new Date(date);
    const day = start.getDay();
    // Adjust to Monday (0 = Sunday, 1 = Monday, etc.)
    const offset = day === 0 ? -6 : 1 - day;
    start.setDate(start.getDate() + offset);

    const workWeekDates = [];
    for (let i = 0; i < 5; i++) {
      const d = new Date(start);
      d.setDate(start.getDate() + i);
      workWeekDates.push(d);
    }
    console.log('[getWorkWeekDates] Input date:', date.toDateString(), 'Output dates:', workWeekDates.map(d => d.toDateString()));
    return workWeekDates;
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
      <div className="max-w-7xl mx-auto px-4 sm:px-6 py-6 font-sans space-y-6">

        {/* No salon assignment state */}
        {!providerId && user && (
          <div className="flex flex-col items-center justify-center py-24 text-center">
            <div className="w-16 h-16 rounded-full bg-gold/10 flex items-center justify-center mb-4">
              <Calendar className="w-8 h-8 text-gold" />
            </div>
            <h2 className="text-xl font-semibold text-text-primary mb-2">No Salon Access</h2>
            <p className="text-text-secondary mb-6 max-w-md">
              You don't have access to this salon's booking system.
            </p>
            <button
              onClick={() => router.push(`/${slug}/dashboard`)}
              className="px-6 py-2.5 bg-gold text-black rounded-xl font-medium hover:bg-gold/90 transition-colors"
            >
              Dashboard
            </button>
          </div>
        )}

        {/* Booking management UI - only shown when providerId exists */}
        {providerId && (
          <>
            {/* Premium Header */}
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
              <div className="flex items-center gap-4">
                <div>
                  <p className="text-text-secondary text-xs font-semibold uppercase tracking-widest mb-1">Schedule</p>
                  <h1 className="text-2xl sm:text-3xl font-bold text-text-primary tracking-tight">
                    {formatDate(selectedDate)}
                  </h1>
                </div>
              </div>
              <div className="flex items-center gap-2">
                <button
                  onClick={() => {
                    if (calendarView === 'day' || calendarView === 'work-week') navigateDate(-1);
                    else if (calendarView === 'week') navigateWeek(-1);
                    else navigateMonth(-1);
                  }}
                  className="p-2 rounded-xl bg-card/80 backdrop-blur border border-border-light hover:bg-white/10 transition-colors"
                >
                  <ChevronLeft className="w-5 h-5 text-text-primary" />
                </button>
                <button
                  onClick={goToToday}
                  className="px-3 py-2 rounded-xl bg-card/80 backdrop-blur border border-border-light hover:bg-white/10 transition-colors text-text-primary font-medium text-sm"
                >
                  Today
                </button>
                <button
                  onClick={() => {
                    if (calendarView === 'day' || calendarView === 'work-week') navigateDate(1);
                    else if (calendarView === 'week') navigateWeek(1);
                    else navigateMonth(1);
                  }}
                  className="p-2 rounded-xl bg-card/80 backdrop-blur border border-border-light hover:bg-white/10 transition-colors"
                >
                  <ChevronRight className="w-5 h-5 text-text-primary" />
                </button>
              </div>
            </div>

            {/* Primary Actions - Mobile Optimized */}
            <div className="flex items-center gap-3 mb-6">
              <button
                onClick={handleNewBooking}
                className="flex-1 sm:flex-none flex items-center justify-center gap-2 px-4 py-2.5 bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black rounded-xl font-semibold hover:opacity-90 transition-opacity shadow-lg shadow-[#FFD700]/30"
              >
                <Plus className="w-4 h-4" />
                <span>New booking</span>
              </button>
              <button
                onClick={handleWalkIn}
                className="flex-1 sm:flex-none px-4 py-2.5 bg-card/80 backdrop-blur border border-border-light rounded-xl text-text-primary font-medium hover:bg-white/10 transition-colors"
              >
                Walk-in
              </button>
            </div>

        {/* Compact Filter Layer - Mobile Optimized */}
        <div className="flex flex-col sm:flex-row sm:items-center gap-3 mb-6">
          {/* Search - Full width on mobile */}
          <div className="relative w-full sm:max-w-xs">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-text-secondary" />
            <input
              type="text"
              placeholder="Search customer, service, or staff..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="w-full pl-10 pr-4 py-2 bg-card/80 backdrop-blur border border-border-light rounded-lg text-text-primary placeholder:text-text-secondary text-sm focus:outline-none focus:border-[rgba(255,215,0,0.4)] transition-colors"
            />
          </div>

          {/* People Filter */}
          <div className="flex items-center gap-2 flex-1 sm:flex-none">
            <User className="w-4 h-4 text-text-secondary" />
            <select
              value={peopleFilter}
              onChange={(e) => setPeopleFilter(e.target.value as 'everyone' | 'unassigned' | string)}
              className="flex-1 sm:flex-none px-3 py-2 bg-card/80 backdrop-blur border border-border-light rounded-lg text-text-primary text-sm focus:outline-none focus:border-[rgba(255,215,0,0.4)] transition-colors"
            >
              <option value="everyone">Everyone</option>
              <option value="unassigned">Unassigned</option>
              {specialists.map((s) => (
                <option key={s.id} value={s.name}>{s.name}</option>
              ))}
            </select>
          </div>

          {/* Status Filter */}
          <div className="flex items-center gap-2 flex-1 sm:flex-none">
            <Clock className="w-4 h-4 text-text-secondary" />
            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value as 'all' | 'upcoming' | 'active' | 'needs-attention')}
              className="flex-1 sm:flex-none px-3 py-2 bg-card/80 backdrop-blur border border-border-light rounded-lg text-text-primary text-sm focus:outline-none focus:border-[rgba(255,215,0,0.4)] transition-colors"
            >
              <option value="all">All</option>
              <option value="upcoming">Upcoming</option>
              <option value="active">Active</option>
              <option value="needs-attention">Needs attention</option>
            </select>
          </div>

          {/* View Toggles - Hide on mobile, show on desktop */}
          <div className="hidden sm:flex bg-card/80 backdrop-blur border border-border-light rounded-lg p-1">
            <button
              onClick={() => {
                console.log('[View Toggle] Switching to Day view, selectedDate:', selectedDate.toDateString());
                setCalendarView('day');
              }}
              className={`px-3 py-1.5 rounded-md text-xs font-medium transition-all ${
                calendarView === 'day' ? 'bg-[#FFD700] text-obsidian' : 'text-text-secondary hover:text-text-primary hover:bg-white/5'
              }`}
            >
              Day
            </button>
            <button
              onClick={() => {
                console.log('[View Toggle] Switching to 5-Day view, selectedDate:', selectedDate.toDateString());
                setCalendarView('work-week');
              }}
              className={`px-3 py-1.5 rounded-md text-xs font-medium transition-all ${
                calendarView === 'work-week' ? 'bg-[#FFD700] text-obsidian' : 'text-text-secondary hover:text-text-primary hover:bg-white/5'
              }`}
            >
              5-Day
            </button>
            <button
              onClick={() => {
                console.log('[View Toggle] Switching to Week view, selectedDate:', selectedDate.toDateString());
                setCalendarView('week');
              }}
              className={`px-3 py-1.5 rounded-md text-xs font-medium transition-all ${
                calendarView === 'week' ? 'bg-[#FFD700] text-obsidian' : 'text-text-secondary hover:text-text-primary hover:bg-white/5'
              }`}
            >
              Week
            </button>
            <button
              onClick={() => {
                console.log('[View Toggle] Switching to Month view, selectedDate:', selectedDate.toDateString());
                setCalendarView('month');
              }}
              className={`px-3 py-1.5 rounded-md text-xs font-medium transition-all ${
                calendarView === 'month' ? 'bg-[#FFD700] text-obsidian' : 'text-text-secondary hover:text-text-primary hover:bg-white/5'
              }`}
            >
              Month
            </button>
          </div>
        </div>

        {/* Mobile View Toggle - Only on mobile */}
        <div className="sm:hidden flex bg-card/80 backdrop-blur border border-border-light rounded-lg p-1 mb-6">
          <button
            onClick={() => {
              console.log('[Mobile View Toggle] Switching to Day view, selectedDate:', selectedDate.toDateString());
              setCalendarView('day');
            }}
            className={`flex-1 px-3 py-1.5 rounded-md text-xs font-medium transition-all ${
              calendarView === 'day' ? 'bg-[#FFD700] text-obsidian' : 'text-text-secondary hover:text-text-primary hover:bg-white/5'
            }`}
          >
            Day
          </button>
          <button
            onClick={() => {
              console.log('[Mobile View Toggle] Switching to 5-Day view, selectedDate:', selectedDate.toDateString());
              setCalendarView('work-week');
            }}
            className={`flex-1 px-3 py-1.5 rounded-md text-xs font-medium transition-all ${
              calendarView === 'work-week' ? 'bg-[#FFD700] text-obsidian' : 'text-text-secondary hover:text-text-primary hover:bg-white/5'
            }`}
          >
            5-Day
          </button>
          <button
            onClick={() => {
              console.log('[Mobile View Toggle] Switching to Week view, selectedDate:', selectedDate.toDateString());
              setCalendarView('week');
            }}
            className={`flex-1 px-3 py-1.5 rounded-md text-xs font-medium transition-all ${
              calendarView === 'week' ? 'bg-[#FFD700] text-obsidian' : 'text-text-secondary hover:text-text-primary hover:bg-white/5'
            }`}
          >
            Week
          </button>
          <button
            onClick={() => {
              console.log('[Mobile View Toggle] Switching to Month view, selectedDate:', selectedDate.toDateString());
              setCalendarView('month');
            }}
            className={`flex-1 px-3 py-1.5 rounded-md text-xs font-medium transition-all ${
              calendarView === 'month' ? 'bg-[#FFD700] text-obsidian' : 'text-text-secondary hover:text-text-primary hover:bg-white/5'
            }`}
          >
            Month
          </button>
        </div>

        {/* Needs Attention - Calm Inbox Style */}
        {bookingsNeedingResolution.length > 0 && (
          <div className="bg-card/50 backdrop-blur border border-border-light rounded-xl p-4 mb-6">
            <div className="flex items-center justify-between mb-3">
              <div className="flex items-center gap-2">
                <div className="w-2 h-2 rounded-full bg-amber-500" />
                <h3 className="text-text-primary font-medium text-sm">Needs attention · {bookingsNeedingResolution.length}</h3>
              </div>
            </div>
            <div className="space-y-2">
              {bookingsNeedingResolution.slice(0, 2).map((booking) => {
                const overdueMinutes = booking.grace_period_ends_at 
                  ? Math.floor((new Date().getTime() - new Date(booking.grace_period_ends_at).getTime()) / 60000)
                  : 0;
                return (
                  <div
                    key={booking.id}
                    onClick={() => {
                      setSelectedBooking(booking);
                      setIsDrawerOpen(true);
                    }}
                    className="flex items-center justify-between p-3 bg-card/30 border border-border-light/50 rounded-lg cursor-pointer hover:bg-card transition-colors"
                  >
                    <div>
                      <p className="text-text-primary font-medium text-sm">{booking.customerName}</p>
                      <p className="text-text-secondary text-xs">{booking.time} · {booking.service} · {booking.staffName}</p>
                      <p className="text-text-secondary text-xs">{formatCompactDate(booking.originalDate || booking.date)}</p>
                    </div>
                    <div className="text-right">
                      <p className="text-amber-400 text-xs font-medium">{overdueMinutes} min overdue</p>
                      <button className="text-gold text-xs font-medium hover:underline mt-1">
                        Decide what happened →
                      </button>
                    </div>
                  </div>
                );
              })}
              {bookingsNeedingResolution.length > 2 && (
                <button
                  onClick={() => setStatusFilter('needs-attention')}
                  className="w-full py-2 text-center text-text-secondary text-xs hover:text-text-primary transition-colors"
                >
                  View all {bookingsNeedingResolution.length} →
                </button>
              )}
            </div>
          </div>
        )}

        {/* Main Content Grid */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Calendar - takes 2 columns on desktop, full on mobile */}
          <div className="lg:col-span-2 bg-card/80 backdrop-blur-xl border border-border-light rounded-2xl overflow-hidden min-h-[500px] lg:min-h-[600px]">
          {calendarView === 'day' && (
            <TimelineView
              bookings={bookings}
              staff={specialists}
              onBookingClick={handleBookingClick}
              currentUserRole={role}
              currentUserName={userName}
            />
          )}

          {/* Mobile Upcoming Sidebar - Swipe to pull out */}
          <AnimatePresence>
            {isMobileUpcomingOpen && (
              <>
                <motion.div
                  initial={{ opacity: 0 }}
                  animate={{ opacity: 1 }}
                  exit={{ opacity: 0 }}
                  onClick={() => setIsMobileUpcomingOpen(false)}
                  className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 lg:hidden"
                />
                <motion.div
                  initial={{ x: '100%' }}
                  animate={{ x: 0 }}
                  exit={{ x: '100%' }}
                  drag="x"
                  dragConstraints={{ left: 0, right: 0 }}
                  dragElastic={0.2}
                  onDragEnd={(e, { offset, velocity }) => {
                    if (offset.x > 100 || velocity.x > 500) {
                      setIsMobileUpcomingOpen(false);
                    }
                  }}
                  transition={{ type: 'spring', damping: 25, stiffness: 300 }}
                  className="fixed inset-y-0 right-0 w-full max-w-sm bg-card/95 backdrop-blur-xl border-l border-border-light z-50 lg:hidden overflow-hidden"
                >
                  <div className="h-full flex flex-col">
                    <div className="flex items-center justify-between p-4 border-b border-border-light">
                      <h2 className="text-text-primary font-semibold">Upcoming</h2>
                      <button
                        onClick={() => setIsMobileUpcomingOpen(false)}
                        className="w-8 h-8 flex items-center justify-center rounded-lg bg-card border border-border-light hover:bg-card/80 transition-colors"
                      >
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                          <line x1="18" y1="6" x2="6" y2="18"></line>
                          <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                      </button>
                    </div>
                    <div className="flex-1 overflow-auto p-4">
                      {bookings
                        .filter(b => b.status !== 'cancelled' && b.status !== 'completed')
                        .sort((a, b) => {
                          const timeA = parseInt(a.time.split(':')[0]);
                          const timeB = parseInt(b.time.split(':')[0]);
                          return timeA - timeB;
                        })
                        .slice(0, 5)
                        .map((booking) => {
                          const getOperationalStateDisplay = () => {
                            const now = new Date();
                            const bookingDate = new Date((booking.originalDate || booking.date).replace(/-/g, '/'));
                            const isPastBooking = bookingDate < new Date(now.getFullYear(), now.getMonth(), now.getDate());

                            if (booking.operational_state === 'upcoming' && isPastBooking) {
                              return 'Past due';
                            }

                            if (booking.operational_state === 'needs_resolution') {
                              const overdueMinutes = booking.grace_period_ends_at
                                ? Math.floor((new Date().getTime() - new Date(booking.grace_period_ends_at).getTime()) / 60000)
                                : 0;
                              return `${overdueMinutes} min overdue`;
                            }
                            if (booking.operational_state === 'grace_period') {
                              return 'Grace period';
                            }
                            if (booking.operational_state === 'due_soon') {
                              return 'Starting soon';
                            }
                            return 'Upcoming';
                          };

                          return (
                            <div
                              key={booking.id}
                              onClick={() => handleBookingClick(booking)}
                              className="cursor-pointer group p-3 rounded-xl hover:bg-card/50 transition-colors mb-3"
                            >
                              <div className="flex items-start justify-between mb-2">
                                <p className="text-gold font-semibold text-lg">{booking.time}</p>
                                <span className="text-xs text-text-secondary bg-card/50 px-2 py-1 rounded-full">
                                  {getOperationalStateDisplay()}
                                </span>
                              </div>
                              <p className="text-text-primary font-medium text-sm group-hover:text-gold transition-colors">{booking.customerName}</p>
                              <p className="text-text-secondary text-xs mt-1">{booking.service} · {booking.staffName}</p>
                              <p className="text-text-secondary text-xs mt-1">{formatCompactDate(booking.originalDate || booking.date)}</p>
                              <div className="h-px bg-border-light/50 mt-3"></div>
                            </div>
                          );
                        })}
                      {bookings.filter(b => b.status !== 'cancelled' && b.status !== 'completed').length === 0 && (
                        <p className="text-text-secondary text-sm text-center py-8">No upcoming appointments</p>
                      )}
                    </div>
                  </div>
                </motion.div>
              </>
            )}
          </AnimatePresence>

          {/* Swipe handle for mobile */}
          <div className="lg:hidden fixed right-0 top-1/2 -translate-y-1/2 z-30">
            <motion.div
              drag="x"
              dragConstraints={{ left: 0, right: 50 }}
              dragElastic={0.1}
              onDragEnd={(e, { offset, velocity }) => {
                if (offset.x < -50 || velocity.x < -500) {
                  setIsMobileUpcomingOpen(true);
                }
              }}
              className="w-1 h-20 bg-gold/30 rounded-full cursor-grab active:cursor-grabbing"
            />
          </div>
          {calendarView === 'work-week' && (
            <div className="h-[600px] overflow-auto p-4">
              <div className="w-full">
                {/* Work Week Header */}
                <div className="grid grid-cols-6 border-b border-border-light bg-white/[0.02] sticky top-0 z-10">
                  <div className="p-3 text-text-secondary text-xs font-medium border-r border-border-light">Time</div>
                  {getWorkWeekDates(selectedDate).map((date, index) => {
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

                {/* Work Week Time Slots */}
                {timeSlots.map((time, index) => (
                  <div key={time} className={`grid grid-cols-6 border-b border-white/5 ${index % 2 === 0 ? 'bg-white/[0.02]' : ''}`}>
                    <div className="p-2 text-text-secondary text-xs border-r border-border-light flex items-center justify-center font-medium">
                      {time}
                    </div>
                    {getWorkWeekDates(selectedDate).map((date, dateIndex) => {
                      const viewDateKey = formatDateKey(date);
                      const dateBookings = bookings.filter(b => {
                        // Use canonical scheduled date directly from backend
                        const bookingDateKey = b.originalDate;
                        return bookingDateKey === viewDateKey;
                      });

                      // Match booking by time - use range-based matching for 30-minute slots
                      const booking = dateBookings.find(b => {
                        let bookingHour = 0;
                        let bookingMinute = 0;
                        if (b.time.includes('AM') || b.time.includes('PM')) {
                          const [timePart, period] = b.time.split(' ');
                          const [hStr, mStr] = timePart.split(':');
                          bookingHour = parseInt(hStr, 10);
                          bookingMinute = parseInt(mStr || '0', 10);
                          if (period === 'PM' && bookingHour !== 12) bookingHour += 12;
                          if (period === 'AM' && bookingHour === 12) bookingHour = 0;
                        } else {
                          const [hStr, mStr] = b.time.split(':');
                          bookingHour = parseInt(hStr, 10);
                          bookingMinute = parseInt(mStr || '0', 10);
                        }

                        const [slotHour, slotMinute] = time.split(':').map(Number);
                        // Match if booking time falls within the 30-minute slot
                        const slotStartMinutes = slotHour * 60 + slotMinute;
                        const bookingMinutes = bookingHour * 60 + bookingMinute;
                        return bookingMinutes >= slotStartMinutes && bookingMinutes < slotStartMinutes + 30;
                      });

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
                      const viewDateKey = formatDateKey(date);
                      const dateBookings = bookings.filter(b => {
                        // Use canonical scheduled date directly from backend
                        const bookingDateKey = b.originalDate;
                        return bookingDateKey === viewDateKey;
                      });

                      // Match booking by time - use range-based matching for 30-minute slots
                      const booking = dateBookings.find(b => {
                        let bookingHour = 0;
                        let bookingMinute = 0;
                        if (b.time.includes('AM') || b.time.includes('PM')) {
                          const [timePart, period] = b.time.split(' ');
                          const [hStr, mStr] = timePart.split(':');
                          bookingHour = parseInt(hStr, 10);
                          bookingMinute = parseInt(mStr || '0', 10);
                          if (period === 'PM' && bookingHour !== 12) bookingHour += 12;
                          if (period === 'AM' && bookingHour === 12) bookingHour = 0;
                        } else {
                          const [hStr, mStr] = b.time.split(':');
                          bookingHour = parseInt(hStr, 10);
                          bookingMinute = parseInt(mStr || '0', 10);
                        }

                        const [slotHour, slotMinute] = time.split(':').map(Number);
                        // Match if booking time falls within the 30-minute slot
                        const slotStartMinutes = slotHour * 60 + slotMinute;
                        const bookingMinutes = bookingHour * 60 + bookingMinute;
                        return bookingMinutes >= slotStartMinutes && bookingMinutes < slotStartMinutes + 30;
                      });

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
                    // Use canonical scheduled date directly from backend
                    const bookingDateKey = b.originalDate;
                    const viewDateKey = formatDateKey(date);
                    return bookingDateKey === viewDateKey;
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

          {/* Sidebar - Next Companion - Hidden on mobile */}
          <div className="hidden lg:block bg-card/80 backdrop-blur-xl border border-border-light rounded-2xl p-5">
            <h3 className="text-text-primary font-semibold mb-4">Next</h3>
            <div className="space-y-3">
              {bookings
                .filter(b => b.status !== 'cancelled' && b.status !== 'completed')
                .sort((a, b) => {
                  const timeA = parseInt(a.time.split(':')[0]);
                  const timeB = parseInt(b.time.split(':')[0]);
                  return timeA - timeB;
                })
                .slice(0, 3)
                .map((booking) => {
                  const getOperationalStateDisplay = () => {
                    const now = new Date();
                    const bookingDate = new Date((booking.originalDate || booking.date).replace(/-/g, '/'));
                    const isPastBooking = bookingDate < new Date(now.getFullYear(), now.getMonth(), now.getDate());

                    if (booking.operational_state === 'upcoming' && isPastBooking) {
                      return 'Past due';
                    }

                    if (booking.operational_state === 'needs_resolution') {
                      const overdueMinutes = booking.grace_period_ends_at
                        ? Math.floor((new Date().getTime() - new Date(booking.grace_period_ends_at).getTime()) / 60000)
                        : 0;
                      return `${overdueMinutes} min overdue`;
                    }
                    if (booking.operational_state === 'grace_period') {
                      return 'Grace period';
                    }
                    if (booking.operational_state === 'due_soon') {
                      return 'Starting soon';
                    }
                    return 'Upcoming';
                  };

                  return (
                    <div
                      key={booking.id}
                      onClick={() => handleBookingClick(booking)}
                      className="cursor-pointer group p-3 rounded-xl hover:bg-card/50 transition-colors"
                    >
                      <div className="flex items-start justify-between mb-2">
                        <p className="text-gold font-semibold text-lg">{booking.time}</p>
                        <span className="text-xs text-text-secondary bg-card/50 px-2 py-1 rounded-full">
                          {getOperationalStateDisplay()}
                        </span>
                      </div>
                      <p className="text-text-primary font-medium text-sm group-hover:text-gold transition-colors">{booking.customerName}</p>
                      <p className="text-text-secondary text-xs mt-1">{booking.service} · {booking.staffName}</p>
                      <p className="text-text-secondary text-xs mt-1">{formatCompactDate(booking.originalDate || booking.date)}</p>
                      <div className="h-px bg-border-light/50 mt-3"></div>
                    </div>
                  );
                })}
              {bookings.filter(b => b.status !== 'cancelled' && b.status !== 'completed').length === 0 && (
                <p className="text-text-secondary text-sm text-center py-8">No upcoming appointments</p>
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
            onResolve={handleResolve}
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
        />

        {selectedBooking && (
          <RescheduleModal
            isOpen={isRescheduleModalOpen}
            onClose={() => setIsRescheduleModalOpen(false)}
            booking={selectedBooking}
            onSuccess={() => setIsRescheduleModalOpen(false)}
          />
        )}

        {selectedBooking && (
          <AssignStaffModal
            isOpen={isAssignStaffModalOpen}
            onClose={() => setIsAssignStaffModalOpen(false)}
            booking={selectedBooking}
            onSuccess={() => setIsAssignStaffModalOpen(false)}
          />
        )}

        {selectedBooking && (
          <ResolutionModal
            isOpen={isResolutionModalOpen}
            onClose={() => setIsResolutionModalOpen(false)}
            booking={selectedBooking}
            onSuccess={() => setIsResolutionModalOpen(false)}
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
