'use client';

import { useState } from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

interface CalendarPickerProps {
  selectedDate: Date;
  onDateSelect: (date: Date) => void;
}

export default function CalendarPicker({ selectedDate, onDateSelect }: CalendarPickerProps) {
  const [currentMonth, setCurrentMonth] = useState(new Date());

  const getDaysInMonth = (date: Date) => {
    const year = date.getFullYear();
    const month = date.getMonth();
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const daysInMonth = lastDay.getDate();
    const startingDayOfWeek = firstDay.getDay();
    
    return { daysInMonth, startingDayOfWeek };
  };

  const navigateMonth = (direction: number) => {
    const newMonth = new Date(currentMonth);
    newMonth.setMonth(newMonth.getMonth() + direction);
    setCurrentMonth(newMonth);
  };

  const selectDate = (day: number) => {
    const newDate = new Date(currentMonth.getFullYear(), currentMonth.getMonth(), day);
    onDateSelect(newDate);
  };

  const isSelectedDate = (day: number) => {
    return (
      selectedDate.getDate() === day &&
      selectedDate.getMonth() === currentMonth.getMonth() &&
      selectedDate.getFullYear() === currentMonth.getFullYear()
    );
  };

  const isToday = (day: number) => {
    const today = new Date();
    return (
      today.getDate() === day &&
      today.getMonth() === currentMonth.getMonth() &&
      today.getFullYear() === currentMonth.getFullYear()
    );
  };

  const isPastDate = (day: number) => {
    const date = new Date(currentMonth.getFullYear(), currentMonth.getMonth(), day);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return date < today;
  };

  const { daysInMonth, startingDayOfWeek } = getDaysInMonth(currentMonth);
  const monthNames = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
  ];
  const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

  return (
    <div className="bg-card border border-border-light rounded-2xl p-3 lg:p-6 backdrop-blur-2xl">
      {/* Calendar Header */}
      <div className="flex items-center justify-between mb-3 lg:mb-6">
        <button
          onClick={() => navigateMonth(-1)}
          className="p-1.5 lg:p-2 rounded-xl bg-card hover:bg-white/10 transition-colors"
        >
          <ChevronLeft className="w-4 h-4 lg:w-5 lg:h-5 text-text-primary" />
        </button>
        <h2 className="text-sm lg:text-xl font-semibold text-text-primary">
          {monthNames[currentMonth.getMonth()]} {currentMonth.getFullYear()}
        </h2>
        <button
          onClick={() => navigateMonth(1)}
          className="p-1.5 lg:p-2 rounded-xl bg-card hover:bg-white/10 transition-colors"
        >
          <ChevronRight className="w-4 h-4 lg:w-5 lg:h-5 text-text-primary" />
        </button>
      </div>

      {/* Day Names */}
      <div className="grid grid-cols-7 gap-0.5 lg:gap-2 mb-2 lg:mb-4">
        {dayNames.map((day) => (
          <div key={day} className="text-center text-text-secondary text-[10px] lg:text-sm font-medium">
            {day}
          </div>
        ))}
      </div>

      {/* Calendar Grid */}
      <div className="grid grid-cols-7 gap-0.5 lg:gap-2">
        {/* Empty cells for days before the first day of the month */}
        {Array.from({ length: startingDayOfWeek }).map((_, index) => (
          <div key={`empty-${index}`} className="aspect-square" />
        ))}

        {/* Days of the month */}
        {Array.from({ length: daysInMonth }).map((_, index) => {
          const day = index + 1;
          const selected = isSelectedDate(day);
          const today = isToday(day);
          const past = isPastDate(day);

          return (
            <button
              key={day}
              onClick={() => !past && selectDate(day)}
              disabled={past}
              className={`aspect-square rounded-lg lg:rounded-xl flex items-center justify-center font-medium transition-all duration-200 text-[10px] lg:text-sm ${
                selected
                  ? 'bg-gradient-to-br from-[#FFD700] to-[#C9A227] text-obsidian scale-105'
                  : today
                  ? 'bg-white/10 text-text-primary border border-[#FFD700]/50'
                  : past
                  ? 'text-[#606060] cursor-not-allowed'
                  : 'text-text-primary hover:bg-white/10'
              }`}
            >
              {day}
            </button>
          );
        })}
      </div>
    </div>
  );
}
