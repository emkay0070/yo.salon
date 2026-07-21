'use client';

import { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { X, Calendar, Clock } from 'lucide-react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useRole } from '@/contexts/RoleContext';
import { apiClient } from '@/lib/api-client';
import CalendarPicker from './CalendarPicker';
import TimePicker from './TimePicker';

interface RescheduleModalProps {
  isOpen: boolean;
  onClose: () => void;
  booking: {
    id: string;
    customerName: string;
    service: string;
    staffName: string;
    time: string;
    date?: string;
  };
  onSuccess?: () => void;
}

export default function RescheduleModal({ isOpen, onClose, booking, onSuccess }: RescheduleModalProps) {
  const queryClient = useQueryClient();
  const { salonId } = useRole();
  const [selectedDate, setSelectedDate] = useState(new Date(booking.date || new Date()));
  const [selectedTime, setSelectedTime] = useState('');

  const updateBookingMutation = useMutation({
    mutationFn: async (data: any) => {
      return await apiClient.updateBooking(booking.id, data);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['bookings', salonId] });
      setSelectedDate(new Date());
      setSelectedTime('');
      onClose();
      onSuccess?.();
    },
    onError: (error) => {
      console.error('Failed to reschedule booking:', error);
      alert('Failed to reschedule booking. Please try again.');
    },
  });

  const handleReschedule = () => {
    if (!selectedDate || !selectedTime) {
      alert('Please select both date and time');
      return;
    }

    const bookingDateTime = new Date(selectedDate);
    const [hours, minutes] = selectedTime.split(':').map(Number);
    bookingDateTime.setHours(hours, minutes, 0, 0);

    updateBookingMutation.mutate({
      date: bookingDateTime.toISOString(),
    });
  };

  return (
    <AnimatePresence>
      {isOpen && (
        <>
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            onClick={onClose}
            className="fixed inset-0 bg-black/50 backdrop-blur-sm z-50"
          />
          <motion.div
            initial={{ opacity: 0, scale: 0.95 }}
            animate={{ opacity: 1, scale: 1 }}
            exit={{ opacity: 0, scale: 0.95 }}
            className="fixed inset-0 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4"
          >
            <div className="bg-card border border-border-light rounded-t-2xl sm:rounded-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
              <div className="p-4 sm:p-6">
                <div className="flex items-center justify-between mb-6">
                  <div>
                    <h2 className="text-xl font-bold text-text-primary">Reschedule Booking</h2>
                    <p className="text-text-secondary text-sm mt-1">
                      {booking.customerName} - {booking.service}
                    </p>
                  </div>
                  <button
                    onClick={onClose}
                    className="p-2 rounded-lg hover:bg-white/10 transition-colors"
                  >
                    <X className="w-5 h-5 text-text-secondary" />
                  </button>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                  <CalendarPicker selectedDate={selectedDate} onDateSelect={setSelectedDate} />
                  <TimePicker
                    selectedTime={selectedTime}
                    onTimeSelect={setSelectedTime}
                  />
                </div>

                <div className="bg-card border border-border-light rounded-xl p-4 mb-6">
                  <h3 className="text-text-primary font-semibold mb-2">Current Appointment</h3>
                  <div className="flex items-center gap-2 text-text-secondary">
                    <Clock className="w-4 h-4" />
                    <span>{booking.time}</span>
                  </div>
                </div>

                <div className="flex gap-3">
                  <button
                    onClick={onClose}
                    className="flex-1 py-3 rounded-xl font-medium bg-white/10 text-text-primary hover:bg-white/20 transition-colors"
                  >
                    Cancel
                  </button>
                  <button
                    onClick={handleReschedule}
                    disabled={updateBookingMutation.isPending || !selectedDate || !selectedTime}
                    className="flex-1 py-3 rounded-xl font-medium bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-obsidian hover:opacity-90 transition-opacity disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    {updateBookingMutation.isPending ? 'Rescheduling...' : 'Reschedule'}
                  </button>
                </div>
              </div>
            </div>
          </motion.div>
        </>
      )}
    </AnimatePresence>
  );
}
