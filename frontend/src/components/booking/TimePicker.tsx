'use client';

import type { Stylist } from '@/app/booking/page';

interface TimePickerProps {
  selectedTime: string;
  onTimeSelect: (time: string) => void;
  selectedStylist?: Stylist;
}

// Mock availability data - in real app, this would come from API
const getSlotAvailability = (time: string, stylist?: Stylist) => {
  const [hours] = time.split(':').map(Number);
  const currentHour = new Date().getHours();
  
  // Simulate some slots being unavailable
  const unavailableSlots = ['10:00', '10:30', '14:00', '14:30', '15:00'];
  const limitedSlots = ['11:00', '11:30', '16:00'];
  
  if (unavailableSlots.includes(time)) {
    return { available: false, reason: 'Booked', queuePosition: 3 };
  }
  
  if (limitedSlots.includes(time)) {
    return { available: true, limited: true, queuePosition: 1 };
  }
  
  // If stylist is not available now, show when they'll be free
  if (!stylist?.available && hours <= currentHour) {
    return { available: false, reason: 'Stylist Busy', nextAvailable: stylist?.nextAvailable };
  }
  
  return { available: true };
};

export default function TimePicker({ selectedTime, onTimeSelect, selectedStylist }: TimePickerProps) {
  const generateTimeSlots = () => {
    const slots = [];
    for (let hour = 9; hour <= 20; hour++) {
      slots.push(`${hour.toString().padStart(2, '0')}:00`);
      slots.push(`${hour.toString().padStart(2, '0')}:30`);
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
    for (const slot of timeSlots) {
      const availability = getSlotAvailability(slot, selectedStylist);
      if (availability.available) {
        return slot;
      }
    }
    return null;
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

      <div className="grid grid-cols-3 gap-1 lg:gap-2">
        {timeSlots.map((time) => {
          const availability = getSlotAvailability(time, selectedStylist);
          const isSelected = selectedTime === time;

          return (
            <button
              key={time}
              onClick={() => availability.available && onTimeSelect(time)}
              disabled={!availability.available}
              className={`py-1.5 lg:py-3 px-0.5 lg:px-2 rounded-lg lg:rounded-xl font-medium transition-all duration-200 text-[10px] lg:text-xs relative ${
                isSelected
                  ? 'bg-gradient-to-br from-[#FFD700] to-[#C9A227] text-obsidian scale-105'
                  : availability.available
                  ? availability.limited
                    ? 'bg-yellow-500/20 text-yellow-400 border border-yellow-500/50 hover:bg-yellow-500/30'
                    : 'bg-white/10 text-text-primary hover:bg-white/20'
                  : 'bg-card text-[#606060] cursor-not-allowed'
              }`}
            >
              {formatTimeDisplay(time)}
              {!availability.available && (
                <div className="absolute top-0.5 right-0.5 w-1 h-1 lg:w-2 lg:h-2 bg-red-500 rounded-full" />
              )}
              {availability.limited && (
                <div className="absolute top-0.5 right-0.5 w-1 h-1 lg:w-2 lg:h-2 bg-yellow-500 rounded-full" />
              )}
            </button>
          );
        })}
      </div>

      <div className="mt-2 lg:mt-4 flex items-center gap-1.5 lg:gap-4 text-[10px] lg:text-xs">
        <div className="flex items-center gap-1 lg:gap-2">
          <div className="w-1.5 h-1.5 lg:w-3 lg:h-3 rounded-full bg-green-500" />
          <span className="text-text-secondary">Available</span>
        </div>
        <div className="flex items-center gap-1 lg:gap-2">
          <div className="w-1.5 h-1.5 lg:w-3 lg:h-3 rounded-full bg-yellow-500" />
          <span className="text-text-secondary">Limited</span>
        </div>
        <div className="flex items-center gap-1 lg:gap-2">
          <div className="w-1.5 h-1.5 lg:w-3 lg:h-3 rounded-full bg-red-500" />
          <span className="text-text-secondary">Booked</span>
        </div>
      </div>
    </div>
  );
}
