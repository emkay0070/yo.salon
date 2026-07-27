'use client';

import { Clock, User, Calendar } from 'lucide-react';

interface BookingSummaryProps {
  service?: { name: string; price: number; duration: number };
  stylist?: { name: string };
  date?: Date;
  time?: string;
}

export default function BookingSummary({
  service,
  stylist,
  date,
  time,
}: BookingSummaryProps) {
  const formatDate = (date: Date) => {
    return date.toLocaleDateString('en-US', {
      weekday: 'long',
      day: 'numeric',
      month: 'long',
      year: 'numeric'
    });
  };

  const formatTime = (time: string) => {
    const [hours, minutes] = time.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour > 12 ? hour - 12 : hour === 0 ? 12 : hour;
    return `${displayHour}:${minutes} ${ampm}`;
  };

  const totalPrice = service?.price || 0;

  return (
    <div className="bg-card border border-border-light rounded-2xl p-3 lg:p-6 backdrop-blur-2xl">
      <h3 className="text-sm lg:text-lg font-semibold text-text-primary mb-3 lg:mb-6">Booking Summary</h3>
      
      <div className="space-y-2.5 lg:space-y-4">
        {/* Service */}
        {service && (
          <div className="flex items-start gap-2.5 lg:gap-4">
            <div className="w-7 h-7 lg:w-10 lg:h-10 rounded-xl bg-gradient-to-br from-[rgba(255,215,0,0.2)] to-[rgba(255,215,0,0.05)] flex items-center justify-center flex-shrink-0">
              <Clock className="w-3.5 h-3.5 lg:w-5 lg:h-5 text-gold" />
            </div>
            <div className="flex-1 min-w-0">
              <p className="text-text-secondary text-[10px] lg:text-sm">Service</p>
              <p className="text-text-primary font-semibold mt-0.5 lg:mt-1 text-xs lg:text-base truncate">{service.name}</p>
              <div className="flex items-center gap-1.5 lg:gap-3 mt-0.5 lg:mt-1">
                <span className="text-gold text-xs lg:text-sm font-medium">UGX {service.price.toLocaleString()}</span>
                <span className="text-text-secondary text-[10px] lg:text-sm">•</span>
                <span className="text-text-secondary text-[10px] lg:text-sm">{service.duration} min</span>
              </div>
            </div>
          </div>
        )}

        {/* Stylist */}
        {stylist && (
          <div className="flex items-start gap-2.5 lg:gap-4">
            <div className="w-7 h-7 lg:w-10 lg:h-10 rounded-xl bg-gradient-to-br from-[rgba(255,215,0,0.2)] to-[rgba(255,215,0,0.05)] flex items-center justify-center flex-shrink-0">
              <User className="w-3.5 h-3.5 lg:w-5 lg:h-5 text-gold" />
            </div>
            <div className="flex-1 min-w-0">
              <p className="text-text-secondary text-[10px] lg:text-sm">Stylist</p>
              <p className="text-text-primary font-semibold mt-0.5 lg:mt-1 text-xs lg:text-base truncate">{stylist.name}</p>
            </div>
          </div>
        )}

        {/* Date */}
        {date && (
          <div className="flex items-start gap-2.5 lg:gap-4">
            <div className="w-7 h-7 lg:w-10 lg:h-10 rounded-xl bg-gradient-to-br from-[rgba(255,215,0,0.2)] to-[rgba(255,215,0,0.05)] flex items-center justify-center flex-shrink-0">
              <Calendar className="w-3.5 h-3.5 lg:w-5 lg:h-5 text-gold" />
            </div>
            <div className="flex-1 min-w-0">
              <p className="text-text-secondary text-[10px] lg:text-sm">Date</p>
              <p className="text-text-primary font-semibold mt-0.5 lg:mt-1 text-xs lg:text-base truncate">{formatDate(date)}</p>
            </div>
          </div>
        )}

        {/* Time */}
        {time && (
          <div className="flex items-start gap-2.5 lg:gap-4">
            <div className="w-7 h-7 lg:w-10 lg:h-10 rounded-xl bg-gradient-to-br from-[rgba(255,215,0,0.2)] to-[rgba(255,215,0,0.05)] flex items-center justify-center flex-shrink-0">
              <Clock className="w-3.5 h-3.5 lg:w-5 lg:h-5 text-gold" />
            </div>
            <div className="flex-1 min-w-0">
              <p className="text-text-secondary text-[10px] lg:text-sm">Time</p>
              <p className="text-text-primary font-semibold mt-0.5 lg:mt-1 text-xs lg:text-base">{formatTime(time)}</p>
            </div>
          </div>
        )}
      </div>

      {/* Total */}
      {service && (
        <div className="mt-3 lg:mt-6 pt-3 lg:pt-6 border-t border-border-light">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-1.5 lg:gap-2">
              <span className="text-gold font-bold text-xs lg:text-sm">UGX</span>
              <span className="text-text-primary font-semibold text-xs lg:text-base">Total</span>
            </div>
            <span className="text-lg lg:text-2xl font-bold text-gold">UGX {totalPrice.toLocaleString()}</span>
          </div>
        </div>
      )}
    </div>
  );
}
