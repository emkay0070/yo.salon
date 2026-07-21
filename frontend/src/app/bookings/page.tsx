'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { motion } from 'framer-motion';
import { Calendar, LayoutList, Plus } from 'lucide-react';
import DashboardLayout from '@/components/DashboardLayout';
import { useRole } from '@/contexts/RoleContext';
import TimelineView from '@/components/booking/TimelineView';
import ListView from '@/components/booking/ListView';

import QuickActions from '@/components/booking/QuickActions';
import BookingDetailsDrawer from '@/components/booking/BookingDetailsDrawer';
import RequestPaymentModal from '@/components/payments/RequestPaymentModal';
import WalkInModal from '@/components/booking/WalkInModal';
import RescheduleModal from '@/components/booking/RescheduleModal';
import AssignStaffModal from '@/components/booking/AssignStaffModal';

interface Booking {
  id: string;
  customerName: string;
  service: string;
  staffName: string;
  time: string;
  endTime: string;
  duration: number;
  status: string;
  paymentStatus?: string;
  price: number;
  phone: string;
  notes?: string;
}

interface Staff {
  id: string;
  name: string;
}

import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';

export default function BookingsPage() {
  const router = useRouter();
  const queryClient = useQueryClient();
  const { role, userName, salonId } = useRole();
  const [viewMode, setViewMode] = useState<'timeline' | 'list'>('timeline');
  const [selectedBooking, setSelectedBooking] = useState<Booking | null>(null);
  const [isDrawerOpen, setIsDrawerOpen] = useState(false);
  const [isPaymentModalOpen, setIsPaymentModalOpen] = useState(false);
  const [paymentBooking, setPaymentBooking] = useState<Booking | null>(null);
  const [isWalkInModalOpen, setIsWalkInModalOpen] = useState(false);
  const [isRescheduleModalOpen, setIsRescheduleModalOpen] = useState(false);
  const [isAssignStaffModalOpen, setIsAssignStaffModalOpen] = useState(false);

  const { data: apiBookings = [], isLoading } = useQuery({
    queryKey: ['bookings', salonId],
    queryFn: () => apiClient.getBookings({ salon_id: salonId }),
    enabled: !!salonId,
  });

  const { data: apiStaff = [] } = useQuery({
    queryKey: ['staff', salonId],
    queryFn: () => apiClient.getStaff({ salon_id: salonId }),
    enabled: !!salonId,
  });

  const bookings: Booking[] = apiBookings.map((b: any) => ({
    id: b.id,
    customerName: b.customer?.name || 'Walk-in',
    service: b.service?.name || 'Service',
    staffName: b.staff?.name || 'Unassigned',
    time: b.date ? new Date(b.date).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '09:00',
    endTime: b.date ? new Date(new Date(b.date).getTime() + (b.service?.duration || 30)*60000).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '09:30',
    duration: b.service?.duration || 30,
    status: b.status || 'confirmed',
    price: b.service?.price || 0,
    phone: b.customer?.phone || '',
    notes: b.notes,
  }));

  const staff: Staff[] = apiStaff.map((s: any) => ({
    id: s.id,
    name: s.name
  }));



  const handleBookingClick = (booking: Booking) => {
    setSelectedBooking(booking);
    setIsDrawerOpen(true);
  };

  const handleNewBooking = () => {
    router.push('/booking');
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

  return (
    <DashboardLayout>
      <div className="max-w-[1600px] mx-auto font-sans pb-12 overflow-x-hidden space-y-5">

        {/* ── Header ──────────────────────────────────────────── */}
        <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
          <div>
            <p className="text-text-secondary text-xs font-semibold uppercase tracking-widest mb-1">Schedule</p>
            <h1 className="text-2xl font-bold text-text-primary tracking-tight">Bookings</h1>
            <p className="text-text-secondary text-sm mt-1 flex items-center gap-2">
              <span className="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" />
              {bookings.length} bookings today
            </p>
          </div>

          {/* View Toggle — pill switcher */}
          <div className="flex items-center bg-card border border-border-medium rounded-xl p-1 shrink-0 self-start sm:self-auto">
            <button
              onClick={() => setViewMode('timeline')}
              className={`flex items-center gap-2 px-4 py-2 rounded-lg transition-all text-sm font-medium ${
                viewMode === 'timeline'
                  ? 'bg-primary/10 text-primary border border-primary/20 shadow-sm'
                  : 'text-text-secondary hover:text-text-primary'
              }`}
            >
              <Calendar className="w-4 h-4" />
              Timeline
            </button>
            <button
              onClick={() => setViewMode('list')}
              className={`flex items-center gap-2 px-4 py-2 rounded-lg transition-all text-sm font-medium ${
                viewMode === 'list'
                  ? 'bg-[#FFD700]/15 text-gold border border-[#FFD700]/20 shadow-sm'
                  : 'text-text-secondary hover:text-text-primary'
              }`}
            >
              <LayoutList className="w-4 h-4" />
              List
            </button>
          </div>
        </div>

        {/* ── Quick Actions ────────────────────────────────────── */}
        <QuickActions
          onNewBooking={handleNewBooking}
          onWalkIn={handleWalkIn}
          onReschedule={handleReschedule}
          onCancel={handleCancel}
          onAssignStaff={handleAssignStaff}
          currentUserRole={role}
        />


        {/* Main Content */}
        {viewMode === 'timeline' ? (
          <TimelineView
            bookings={bookings}
            staff={staff}
            onBookingClick={handleBookingClick}
            currentUserRole={role}
            currentUserName={userName}
          />
        ) : (
          <ListView
            bookings={bookings}
            onBookingClick={handleBookingClick}
            currentUserRole={role}
            currentUserName={userName}
          />
        )}

        {/* Booking Details Drawer */}
        {selectedBooking && (
          <BookingDetailsDrawer
            isOpen={isDrawerOpen}
            onClose={() => setIsDrawerOpen(false)}
            booking={selectedBooking}
            onCheckIn={handleCheckIn}
            onStartService={handleStartService}
            onCompleteService={handleCompleteService}
            onReschedule={handleReschedule}
            onCancel={handleCancel}
            currentUserRole={role}
          />
        )}

        {/* Request Payment Modal — triggered from booking context */}
        {paymentBooking && (
          <RequestPaymentModal
            isOpen={isPaymentModalOpen}
            onClose={() => setIsPaymentModalOpen(false)}
            customerName={paymentBooking.customerName}
            serviceName={paymentBooking.service}
            amount={paymentBooking.price}
            bookingId={paymentBooking.id}
            onSuccess={(tx) => {
              console.log('Payment recorded for booking:', paymentBooking.id, tx);
              setIsPaymentModalOpen(false);
            }}
          />
        )}

        {/* Walk-in Modal */}
        <WalkInModal
          isOpen={isWalkInModalOpen}
          onClose={() => setIsWalkInModalOpen(false)}
          onSuccess={() => {
            console.log('Walk-in booking created');
          }}
        />

        {/* Reschedule Modal */}
        {selectedBooking && (
          <RescheduleModal
            isOpen={isRescheduleModalOpen}
            onClose={() => setIsRescheduleModalOpen(false)}
            booking={selectedBooking}
            onSuccess={() => {
              console.log('Booking rescheduled');
            }}
          />
        )}

        {/* Assign Staff Modal */}
        {selectedBooking && (
          <AssignStaffModal
            isOpen={isAssignStaffModalOpen}
            onClose={() => setIsAssignStaffModalOpen(false)}
            booking={selectedBooking}
            onSuccess={() => {
              console.log('Staff assigned');
            }}
          />
        )}
      </div>
    </DashboardLayout>
  );
}
