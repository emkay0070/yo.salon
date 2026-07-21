'use client';

import { motion } from 'framer-motion';
import { Calendar, CheckCircle, Clock, XCircle, Users } from 'lucide-react';

interface BookingStatsProps {
  totalBookings: number;
  confirmed: number;
  waiting: number;
  inService: number;
  cancelled: number;
}

export default function BookingStats({
  totalBookings,
  confirmed,
  waiting,
  inService,
  cancelled
}: BookingStatsProps) {
  const stats = [
    { 
      label: "Today's Bookings", 
      value: totalBookings, 
      icon: Calendar, 
      color: 'text-gold',
      bg: 'from-[rgba(255,215,0,0.2)] to-[rgba(255,215,0,0.05)]'
    },
    { 
      label: 'Confirmed', 
      value: confirmed, 
      icon: CheckCircle, 
      color: 'text-green-400',
      bg: 'from-[rgba(74,222,128,0.2)] to-[rgba(74,222,128,0.05)]'
    },
    { 
      label: 'Waiting', 
      value: waiting, 
      icon: Clock, 
      color: 'text-yellow-400',
      bg: 'from-[rgba(250,204,21,0.2)] to-[rgba(250,204,21,0.05)]'
    },
    { 
      label: 'In Service', 
      value: inService, 
      icon: Users, 
      color: 'text-blue-400',
      bg: 'from-[rgba(96,165,250,0.2)] to-[rgba(96,165,250,0.05)]'
    },
    { 
      label: 'Cancelled', 
      value: cancelled, 
      icon: XCircle, 
      color: 'text-red-400',
      bg: 'from-[rgba(248,113,113,0.2)] to-[rgba(248,113,113,0.05)]'
    },
  ];

  return (
    <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
      {stats.map((stat, index) => (
        <motion.div
          key={stat.label}
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: index * 0.1 }}
          className="bg-card border border-border-light rounded-2xl p-4 backdrop-blur-2xl"
        >
          <div className="flex items-center gap-3">
            <div className={`p-2 rounded-xl bg-gradient-to-br ${stat.bg}`}>
              <stat.icon className={`w-5 h-5 ${stat.color}`} />
            </div>
            <div>
              <p className="text-text-secondary text-xs">{stat.label}</p>
              <p className="text-xl font-bold text-text-primary">{stat.value}</p>
            </div>
          </div>
        </motion.div>
      ))}
    </div>
  );
}
