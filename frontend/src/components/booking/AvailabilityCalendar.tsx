'use client';

import { useState, useEffect } from 'react';
import { Calendar as CalendarIcon, Clock, ChevronLeft, ChevronRight, Loader2 } from 'lucide-react';
import { useQuery } from '@tanstack/react-query';
import { motion, AnimatePresence } from 'framer-motion';
import { usePolling } from '@/hooks/usePolling';
import { apiClient } from '@/lib/api-client';

/**
 * AvailabilityPublicStatusValue
 *
 * Customer-facing availability status types from the API.
 * These are the 4 stable public statuses mapped from 17 internal domain statuses.
 *
 * Architecture:
 * - Mapped from AvailabilityDomainStatus via AvailabilityStatusMapper on backend
 * - Frontend consumes only these 4 values (API contract)
 * - Adding new internal states NEVER changes these values
 *
 * Status Meanings:
 * - available: Slots exist and can be booked
 * - closed: Entity is closed (no slots available today)
 * - configuration_required: Setup needed (no diagnostic detail exposed)
 * - unavailable: Temporary issue (break, leave, booked, etc.)
 */
export type AvailabilityPublicStatusValue =
  | 'available'
  | 'closed'
  | 'configuration_required'
  | 'unavailable';

/**
 * TimeSlot
 *
 * Individual bookable time slot from availability API.
 * Contains timing, duration, and optional specialist information.
 */
export interface TimeSlot {
  start: string;
  end: string;
  duration: number;
  available_specialists?: Array<{
    id: string;
    name: string;
    skill_level: string;
    price: number;
  }>;
  base_price?: number;
}

/**
 * AvailabilityData
 *
 * Complete availability response from the backend API.
 * Contains both customer-facing and internal diagnostic information.
 *
 * Customer-Facing Fields:
 * - status: Public status (available/closed/configuration_required/unavailable)
 * - message: Customer-friendly message for non-available states
 * - slots: Array of bookable time slots
 *
 * Internal/Diagnostic Fields:
 * - domain_status: Internal domain status (17 possible values)
 * - domain_statuses: All contributing statuses for diagnostics
 * - internal_reason: Owner-facing diagnostic reason
 * - reason: Lowercase domain status for analytics
 *
 * @see AvailabilityPublicStatusValue for the 4 public status values
 */
export interface AvailabilityData {
  salon_id: string;
  salon_name?: string | null;
  date: string;
  timezone?: string | null;
  operating_hours?: {
    open: string | null;
    close: string | null;
  } | null;
  is_closed: boolean;
  slots: TimeSlot[];
  first_available_slot: TimeSlot | null;
  total_available_slots: number;
  status: AvailabilityPublicStatusValue;
  reason: string;
  message?: string | null;
  domain_status?: string;
  internal_reason?: string | null;
  domain_statuses?: string[];
}

interface AvailabilityCalendarProps {
  salonId: string;
  serviceId?: string;
  specialistId?: string;
  onSlotSelect?: (slot: TimeSlot) => void;
  selectedSlot?: TimeSlot | null;
}

export default function AvailabilityCalendar({
  salonId,
  serviceId,
  specialistId,
  onSlotSelect,
  selectedSlot,
}: AvailabilityCalendarProps) {
  const [currentDate, setCurrentDate] = useState(new Date());
  const [selectedDate, setSelectedDate] = useState(new Date());

  const formatDate = (date: Date) => {
    return date.toISOString().split('T')[0];
  };

  const { data: availability, isLoading, refetch } = useQuery({
    queryKey: ['availability', salonId, formatDate(selectedDate), serviceId, specialistId],
    queryFn: () => apiClient.get(`/salons/${salonId}/availability`, {
      params: {
        date: formatDate(selectedDate),
        service_id: serviceId,
        specialist_id: specialistId,
      },
    }),
    enabled: !!salonId,
  });

  // Poll for availability updates every 30 seconds
  usePolling(
    async () => {
      await refetch();
    },
    {
      enabled: !!salonId,
      interval: 30000, // 30 seconds
    }
  );

  const { data: batchAvailability } = useQuery({
    queryKey: ['batch-availability', salonId, formatDate(currentDate)],
    queryFn: () => apiClient.get(`/salons/${salonId}/availability/batch`, {
      params: {
        start_date: formatDate(currentDate),
        end_date: formatDate(new Date(currentDate.getTime() + 13 * 24 * 60 * 60 * 1000)), // 14 days
        service_id: serviceId,
        specialist_id: specialistId,
      },
    }),
    enabled: !!salonId,
  });

  const goToPreviousDay = () => {
    const newDate = new Date(selectedDate);
    newDate.setDate(newDate.getDate() - 1);
    setSelectedDate(newDate);
  };

  const goToNextDay = () => {
    const newDate = new Date(selectedDate);
    newDate.setDate(newDate.getDate() + 1);
    setSelectedDate(newDate);
  };

  const goToToday = () => {
    setSelectedDate(new Date());
  };

  const selectDate = (date: Date) => {
    setSelectedDate(date);
  };

  const getDayName = (date: Date) => {
    return date.toLocaleDateString('en-US', { weekday: 'short' });
  };

  const getDayNumber = (date: Date) => {
    return date.getDate();
  };

  const isToday = (date: Date) => {
    const today = new Date();
    return date.toDateString() === today.toDateString();
  };

  const isSelected = (date: Date) => {
    return date.toDateString() === selectedDate.toDateString();
  };

  const getAvailabilityForDate = (date: Date) => {
    if (!batchAvailability?.dates) return null;
    const dateStr = formatDate(date);
    return batchAvailability.dates.find((d: any) => d.date === dateStr);
  };

  const renderDays = () => {
    const days = [];
    for (let i = 0; i < 14; i++) {
      const date = new Date(currentDate);
      date.setDate(date.getDate() + i);
      days.push(date);
    }
    return days;
  };

  return (
    <div className="space-y-6">
      {/* Date Selector */}
      <div className="bg-card border border-border-light rounded-xl p-4">
        <div className="flex items-center justify-between mb-4">
          <div className="flex items-center gap-2">
            <CalendarIcon className="w-5 h-5 text-gold" />
            <h3 className="font-semibold text-text-primary">Select Date</h3>
          </div>
          <button
            onClick={goToToday}
            className="text-sm text-text-secondary hover:text-text-primary transition-colors"
          >
            Today
          </button>
        </div>

        <div className="flex gap-2 overflow-x-auto pb-2">
          {renderDays().map((date, index) => {
            const availability = getAvailabilityForDate(date);
            const isClosed = availability?.is_closed;
            const hasSlots = availability?.available_slots > 0;

            return (
              <button
                key={index}
                onClick={() => selectDate(date)}
                disabled={isClosed}
                className={`flex-shrink-0 w-14 h-16 rounded-xl flex flex-col items-center justify-center transition-all ${
                  isSelected(date)
                    ? 'bg-gradient-to-br from-gold to-amber-600 text-black'
                    : isClosed
                    ? 'bg-surface text-text-secondary opacity-50 cursor-not-allowed'
                    : hasSlots
                    ? 'bg-card border border-border-light hover:border-gold/50 text-text-primary'
                    : 'bg-card border border-border-light text-text-secondary opacity-60'
                }`}
              >
                <span className="text-xs font-medium">{getDayName(date)}</span>
                <span className="text-lg font-bold">{getDayNumber(date)}</span>
                {isToday(date) && !isSelected(date) && (
                  <span className="text-[10px] mt-0.5">Today</span>
                )}
              </button>
            );
          })}
        </div>
      </div>

      {/* Time Slots */}
      <div className="bg-card border border-border-light rounded-xl p-4">
        <div className="flex items-center justify-between mb-4">
          <div className="flex items-center gap-2">
            <Clock className="w-5 h-5 text-gold" />
            <h3 className="font-semibold text-text-primary">
              {selectedDate.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' })}
            </h3>
          </div>
          <div className="flex items-center gap-2">
            <button
              onClick={goToPreviousDay}
              className="p-2 rounded-lg bg-surface hover:bg-card transition-colors text-text-secondary"
            >
              <ChevronLeft className="w-4 h-4" />
            </button>
            <button
              onClick={goToNextDay}
              className="p-2 rounded-lg bg-surface hover:bg-card transition-colors text-text-secondary"
            >
              <ChevronRight className="w-4 h-4" />
            </button>
          </div>
        </div>

        {isLoading ? (
          <div className="flex items-center justify-center py-8">
            <Loader2 className="w-6 h-6 text-gold animate-spin" />
          </div>
        ) : availability?.is_closed ? (
          <div className="text-center py-8">
            <p className="text-text-secondary">Salon is closed on this day</p>
          </div>
        ) : availability?.slots && availability.slots.length > 0 ? (
          <div className="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 gap-2">
            <AnimatePresence>
              {availability.slots.map((slot: TimeSlot, index: number) => (
                <motion.button
                  key={index}
                  initial={{ opacity: 0, scale: 0.9 }}
                  animate={{ opacity: 1, scale: 1 }}
                  transition={{ delay: index * 0.05 }}
                  onClick={() => onSlotSelect?.(slot)}
                  className={`p-3 rounded-xl text-center transition-all ${
                    selectedSlot?.start === slot.start
                      ? 'bg-gradient-to-br from-gold to-amber-600 text-black font-semibold'
                      : 'bg-surface border border-border-light hover:border-gold/50 text-text-primary'
                  }`}
                >
                  <p className="font-medium text-sm">{slot.start}</p>
                  <p className="text-xs opacity-70">{slot.end}</p>
                </motion.button>
              ))}
            </AnimatePresence>
          </div>
        ) : (
          <div className="text-center py-8">
            <p className="text-text-secondary">No available slots for this day</p>
          </div>
        )}

        {availability && (
          <div className="mt-4 pt-4 border-t border-border-light flex items-center justify-between text-sm text-text-secondary">
            <span>{availability.total_available_slots} slots available</span>
            <span>Operating hours: {availability.operating_hours.open} - {availability.operating_hours.close}</span>
          </div>
        )}
      </div>
    </div>
  );
}
