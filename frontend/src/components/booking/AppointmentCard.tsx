'use client';

import { motion } from 'framer-motion';
import { User, Clock, CheckCircle, DollarSign } from 'lucide-react';
import { Booking } from './TimelineView';

interface AppointmentCardProps {
  booking: Booking;
  onClick: () => void;
}

export default function AppointmentCard({
  booking,
  onClick
}: AppointmentCardProps) {
  const getStatusColor = (status: string) => {
    switch (status) {
      case 'confirmed': return 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20';
      case 'checked_in': return 'bg-blue-500/10 text-blue-400 border-blue-500/20';
      case 'in_service': return 'bg-gold/10 text-gold border-gold/20';
      case 'completed': return 'bg-text-secondary/10 text-text-secondary border-border-light';
      case 'cancelled': return 'bg-terracotta/10 text-terracotta border-terracotta/20';
      default: return 'bg-card text-text-primary border-border-light';
    }
  };

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'confirmed': return CheckCircle;
      case 'checked_in': return CheckCircle;
      case 'in_service': return Clock;
      case 'completed': return CheckCircle;
      case 'cancelled': return null;
      default: return null;
    }
  };

  const StatusIcon = getStatusIcon(booking.status);

  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      whileHover={{ scale: 1.01, borderColor: 'var(--color-gold)' }}
      whileTap={{ scale: 0.98 }}
      onClick={onClick}
      className={`p-4 rounded-xl border cursor-pointer transition-all bg-surface/50 backdrop-blur-md shadow-sm ${getStatusColor(booking.status)}`}
    >
      <div className="flex flex-col sm:flex-row sm:items-start gap-4">
        {/* Top Area: Avatar & Main info */}
        <div className="flex items-start gap-4 flex-1 min-w-0">
          {/* Customer Avatar */}
          <div className="w-12 h-12 rounded-full bg-gradient-to-br from-gold to-dark-gold flex items-center justify-center flex-shrink-0 shadow-sm">
            <User className="w-6 h-6 text-obsidian" />
          </div>

          {/* Booking Details */}
          <div className="flex-1 min-w-0">
            <div className="flex items-center justify-between gap-2 mb-1">
              <h3 className="text-text-primary font-semibold truncate">{booking.customerName}</h3>
              {StatusIcon && <StatusIcon className="w-4 h-4 flex-shrink-0" />}
            </div>
            
            <div className="flex items-center gap-2 text-text-secondary text-sm mb-2">
              <Clock className="w-3 h-3" />
              <span>{booking.time}</span>
            </div>

            <p className="text-text-primary text-sm mb-1 line-clamp-1">{booking.service}</p>
            <p className="text-text-muted text-xs">with {booking.staffName}</p>
          </div>
        </div>

        {/* Price and Status - Stacks on tiny screens */}
        <div className="sm:text-right flex items-center justify-between sm:flex-col sm:items-end flex-shrink-0 mt-3 sm:mt-0 pt-3 sm:pt-0 border-t border-border-light sm:border-t-0">
          <div className="flex items-center gap-1">
            <DollarSign className="w-4 h-4 text-gold" />
            <span className="text-text-primary font-semibold">UGX {booking.price.toLocaleString()}</span>
          </div>
          <span className={`text-[10px] font-medium px-2 py-1 rounded-md mt-0 sm:mt-2 ${getStatusColor(booking.status)}`}>
            {booking.status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}
          </span>
        </div>
      </div>
    </motion.div>
  );
}
