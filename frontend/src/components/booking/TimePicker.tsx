'use client';

import { useQuery } from '@tanstack/react-query';
import { useRole } from '@/contexts/RoleContext';
import { apiClient } from '@/lib/api-client';
import type { Stylist } from '@/app/booking/BookingContent';
import { Lock, LockOpen } from 'lucide-react';

interface TimePickerProps {
  selectedTime: string;
  onTimeSelect: (time: string) => void;
  selectedStylist?: Stylist;
  selectedDate?: Date;
  selectedService?: { id: string; duration: number };
}

interface TimeSlot {
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

interface AvailabilityData {
  slots: TimeSlot[];
  is_closed: boolean;
  total_available_slots: number;
  operating_hours?: {
    open: string | null;
    close: string | null;
  } | null;
}

// Check if a time slot is available based on availability data
const getSlotAvailability = (time: string, availabilityData?: AvailabilityData, specialistId?: string) => {
  if (!availabilityData || availabilityData.is_closed) {
    return { available: false, reason: 'Closed' };
  }

  const slot = availabilityData.slots.find(s => s.start === time);
  
  if (!slot) {
    return { available: false, reason: 'Unavailable' };
  }

  // If a specialist is selected, check if they're available for this slot
  if (specialistId && slot.available_specialists) {
    const specialistAvailable = slot.available_specialists.some(s => s.id === specialistId);
    if (!specialistAvailable) {
      return { available: false, reason: 'Specialist unavailable' };
    }
  }

  return { available: true };
};

export default function TimePicker({ selectedTime, onTimeSelect, selectedStylist, selectedDate, selectedService }: TimePickerProps) {
  const { providerId } = useRole();

  const formatDate = (date: Date) => {
    return date.toISOString().split('T')[0];
  };

  const { data: availabilityData, isLoading } = useQuery({
    queryKey: ['availability', providerId, selectedDate ? formatDate(selectedDate) : formatDate(new Date()), selectedStylist?.specialist_id, selectedService?.id],
    queryFn: () => apiClient.get(`/providers/${providerId}/availability`, {
      params: {
        date: selectedDate ? formatDate(selectedDate) : formatDate(new Date()),
        specialist_id: selectedStylist?.specialist_id,
        service_id: selectedService?.id,
      },
    }),
    enabled: !!providerId,
  });

  const generateTimeSlots = () => {
    const slots = [];

    // Use operating hours from backend response, or default to 9:00-20:00
    const openHour = availabilityData?.operating_hours?.open
      ? parseInt(availabilityData.operating_hours.open.split(':')[0])
      : 9;
    const closeHour = availabilityData?.operating_hours?.close
      ? parseInt(availabilityData.operating_hours.close.split(':')[0])
      : 20;

    // Generate 15-minute intervals within operating hours
    for (let hour = openHour; hour < closeHour; hour++) {
      for (let minute = 0; minute < 60; minute += 15) {
        slots.push(`${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`);
      }
    }

    return slots;
  };

  const timeSlots = generateTimeSlots();

  const formatTimeDisplay = (time: string) => {
    const [hours, minutes] = time.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour > 12 ? hour - 12 : hour === 0 ? 12 : hour;
    return `${displayHour}:${minutes} ${ampm}`;
  };

  const getNextAvailableSlot = () => {
    if (!availabilityData || availabilityData.is_closed) return null;
    return availabilityData.slots[0]?.start || null;
  };

  const nextAvailable = getNextAvailableSlot();

  return (
    <div className="bg-card border border-border-light rounded-2xl p-3 lg:p-6 backdrop-blur-2xl">
      <div className="flex items-center justify-between mb-2 lg:mb-4">
        <h3 className="text-sm lg:text-lg font-semibold text-text-primary">Select Time</h3>
        {selectedStylist && !selectedStylist.available && (
          <div className="text-[10px] lg:text-xs text-yellow-400 bg-yellow-400/10 px-1.5 lg:px-3 py-0.5 lg:py-1 rounded-full">
            Next: {selectedStylist.nextAvailable}
          </div>
        )}
      </div>

      {nextAvailable && (
        <div className="mb-2 lg:mb-4 p-1.5 lg:p-3 bg-green-500/10 border border-green-500/30 rounded-lg lg:rounded-xl">
          <p className="text-green-400 text-[10px] lg:text-sm font-medium">
            Next available: {formatTimeDisplay(nextAvailable)}
          </p>
        </div>
      )}

      {isLoading ? (
        <div className="flex items-center justify-center py-8">
          <div className="w-6 h-6 border-2 border-gold border-t-transparent rounded-full animate-spin" />
        </div>
      ) : availabilityData?.is_closed ? (
        <div className="text-center py-8">
          <p className="text-text-secondary text-sm">Closed on this day</p>
          <p className="text-text-muted text-xs mt-1">
            Operating hours: {availabilityData.operating_hours?.open} - {availabilityData.operating_hours?.close}
          </p>
        </div>
      ) : (
        <div className="grid grid-cols-3 gap-1 lg:gap-2">
          {timeSlots.map((time) => {
            const availability = getSlotAvailability(time, availabilityData, selectedStylist?.id);
            const isSelected = selectedTime === time;

            return (
              <button
                key={time}
                onClick={() => availability.available && onTimeSelect(time)}
                disabled={!availability.available}
                className={`py-1.5 lg:py-3 px-0.5 lg:px-2 rounded-lg lg:rounded-xl font-medium transition-all duration-200 text-[10px] lg:text-xs relative flex flex-col items-center justify-center gap-0.5 ${
                  isSelected
                    ? 'bg-gradient-to-br from-[#FFD700] to-[#C9A227] text-obsidian scale-105'
                    : availability.available
                    ? 'bg-white/10 text-text-primary hover:bg-white/20'
                    : 'bg-card text-[#606060] cursor-not-allowed'
                }`}
              >
                {formatTimeDisplay(time)}
                {availability.available ? (
                  <LockOpen className="w-2.5 h-2.5 lg:w-3 lg:h-3 text-green-400" />
                ) : (
                  <Lock className="w-2.5 h-2.5 lg:w-3 lg:h-3 text-red-400" />
                )}
                <span className="text-[8px] lg:text-[9px] opacity-70">
                  {availability.available ? 'Available' : 'Unavailable'}
                </span>
              </button>
            );
          })}
        </div>
      )}

      {availabilityData && (
        <div className="mt-2 lg:mt-4 pt-2 lg:pt-4 border-t border-border-light flex items-center justify-between text-[10px] lg:text-xs text-text-secondary">
          <span>{availabilityData.total_available_slots} slots available</span>
          <span>
            {availabilityData.operating_hours?.open} - {availabilityData.operating_hours?.close}
          </span>
        </div>
      )}
    </div>
  );
}
