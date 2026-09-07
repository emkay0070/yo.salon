'use client';

import { useState } from 'react';
import { motion } from 'framer-motion';
import { Calendar, Clock, ChevronLeft, ChevronRight } from 'lucide-react';

interface TimeSlot {
  time: string;
  available: boolean;
  specialist?: string;
}

interface AvailabilityPanelProps {
  timeSlots: TimeSlot[];
  selectedDate?: string;
  onDateChange?: (date: string) => void;
  onTimeSelect?: (time: string) => void;
  selectedTime?: string;
  showCalendar?: boolean;
}

export function AvailabilityPanel({
  timeSlots,
  selectedDate,
  onDateChange,
  onTimeSelect,
  selectedTime,
  showCalendar = true
}: AvailabilityPanelProps) {
  const [currentDate, setCurrentDate] = useState(selectedDate || new Date().toISOString().split('T')[0]);

  const getDates = () => {
    const dates = [];
    const today = new Date();
    for (let i = 0; i < 7; i++) {
      const date = new Date(today);
      date.setDate(today.getDate() + i);
      dates.push({
        date: date.toISOString().split('T')[0],
        day: date.toLocaleDateString('en-US', { weekday: 'short' }),
        dateNum: date.getDate()
      });
    }
    return dates;
  };

  const dates = getDates();

  return (
    <div className="space-y-4">
      <h3 className="text-xl font-semibold text-text-primary">Availability</h3>

      {/* Date Selector */}
      {showCalendar && (
        <div className="flex gap-2 overflow-x-auto pb-2">
          <button
            onClick={() => {/* Previous week */}}
            className="p-2 rounded-full bg-surface border border-border-light hover:border-gold/30 transition-colors flex-shrink-0"
          >
            <ChevronLeft className="w-5 h-5 text-text-primary" />
          </button>
          
          {dates.map((dateObj) => (
            <button
              key={dateObj.date}
              onClick={() => {
                setCurrentDate(dateObj.date);
                onDateChange?.(dateObj.date);
              }}
              className={`flex-shrink-0 px-4 py-3 rounded-xl border transition-all ${
                currentDate === dateObj.date
                  ? 'border-gold bg-gold/10'
                  : 'border-border-light bg-surface hover:border-gold/30'
              }`}
              style={{
                borderRadius: 'var(--brand-border-radius, 16px)'
              }}
            >
              <p className="text-xs text-text-secondary mb-1">{dateObj.day}</p>
              <p className="text-lg font-semibold text-text-primary">{dateObj.dateNum}</p>
            </button>
          ))}
          
          <button
            onClick={() => {/* Next week */}}
            className="p-2 rounded-full bg-surface border border-border-light hover:border-gold/30 transition-colors flex-shrink-0"
          >
            <ChevronRight className="w-5 h-5 text-text-primary" />
          </button>
        </div>
      )}

      {/* Time Slots */}
      <div className="grid grid-cols-3 md:grid-cols-4 gap-3">
        {timeSlots.length > 0 ? (
          timeSlots.map((slot, index) => (
            <motion.button
              key={index}
              initial={{ opacity: 0, scale: 0.9 }}
              animate={{ opacity: 1, scale: 1 }}
              transition={{ delay: index * 0.02 }}
              onClick={() => slot.available && onTimeSelect?.(slot.time)}
              disabled={!slot.available}
              className={`p-3 rounded-xl border transition-all ${
                selectedTime === slot.time
                  ? 'border-gold bg-gold/10'
                  : slot.available
                  ? 'border-border-light bg-surface hover:border-gold/30'
                  : 'border-border-light bg-surface/50 opacity-50 cursor-not-allowed'
              }`}
              style={{
                borderRadius: 'var(--brand-border-radius, 16px)'
              }}
            >
              <div className="flex items-center justify-center gap-2">
                <Clock className="w-4 h-4" />
                <span className={`font-medium ${slot.available ? 'text-text-primary' : 'text-text-muted'}`}>
                  {slot.time}
                </span>
              </div>
              {slot.specialist && (
                <p className="text-xs text-text-secondary mt-1 truncate">{slot.specialist}</p>
              )}
            </motion.button>
          ))
        ) : (
          <div className="col-span-full text-center py-8 text-text-secondary">
            <Calendar className="w-12 h-12 mx-auto mb-4 opacity-50" />
            <p>No available slots for this date</p>
          </div>
        )}
      </div>
    </div>
  );
}
