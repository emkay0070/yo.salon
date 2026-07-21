'use client';
import { motion } from 'framer-motion';

export interface ServiceStat {
  name: string;
  revenue: number;
  bookings: number;
  percentage: number;
}

interface ServiceRankingProps {
  services: ServiceStat[];
}

export function ServiceRanking({ services }: ServiceRankingProps) {
  return (
    <div className="space-y-4">
      {services.map((service, idx) => (
        <motion.div 
          key={service.name}
          initial={{ opacity: 0, x: -10 }}
          animate={{ opacity: 1, x: 0 }}
          transition={{ delay: idx * 0.1 }}
          className="flex flex-col gap-2"
        >
          <div className="flex flex-col sm:flex-row sm:justify-between text-sm gap-1 sm:gap-2">
            <span className="font-medium text-text-primary truncate">{service.name}</span>
            <span className="text-text-secondary shrink-0">
              <span className="text-text-primary font-medium">UGX {(service.revenue / 1000).toFixed(0)}k</span> ({service.bookings} bookings)
            </span>
          </div>
          <div className="h-2 w-full bg-surface/50 rounded-full overflow-hidden border border-border-light">
            <motion.div 
              initial={{ width: 0 }}
              animate={{ width: `${service.percentage}%` }}
              transition={{ duration: 1, ease: "easeOut" }}
              className="h-full bg-gradient-to-r from-dark-gold to-gold rounded-full"
            />
          </div>
        </motion.div>
      ))}
    </div>
  );
}
