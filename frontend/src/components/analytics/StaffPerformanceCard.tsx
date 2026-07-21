'use client';
import { motion } from 'framer-motion';
import { Star } from 'lucide-react';

interface StaffPerformanceCardProps {
  name: string;
  role: string;
  revenue: number;
  clients: number;
  rating: number;
  utilization: number;
  avatarText: string;
}

export function StaffPerformanceCard({ name, role, revenue, clients, rating, utilization, avatarText }: StaffPerformanceCardProps) {
  return (
    <motion.div 
      initial={{ opacity: 0, scale: 0.95 }}
      animate={{ opacity: 1, scale: 1 }}
      className="bg-card border border-border-light rounded-2xl p-5 hover:bg-white/[0.07] transition-all"
    >
      <div className="flex items-center gap-4 mb-5">
        <div className="w-12 h-12 rounded-full bg-gradient-to-br from-[#FFD700] to-[#C9A227] flex items-center justify-center text-obsidian font-bold text-lg">
          {avatarText}
        </div>
        <div>
          <h3 className="font-semibold text-text-primary">{name}</h3>
          <p className="text-xs text-text-secondary">{role}</p>
        </div>
        <div className="ml-auto flex items-center gap-1 bg-[#FFD700]/10 text-gold px-2 py-1 rounded-lg text-sm font-medium">
          <Star className="w-3.5 h-3.5 fill-current" />
          {rating.toFixed(1)}
        </div>
      </div>
      
      <div className="grid grid-cols-2 gap-4">
        <div>
          <p className="text-xs text-text-secondary mb-1">Revenue</p>
          <p className="font-medium text-text-primary">UGX {(revenue / 1000).toFixed(0)}k</p>
        </div>
        <div>
          <p className="text-xs text-text-secondary mb-1">Clients</p>
          <p className="font-medium text-text-primary">{clients}</p>
        </div>
        <div className="col-span-2">
          <div className="flex justify-between text-xs mb-1">
            <span className="text-text-secondary">Utilization</span>
            <span className="text-text-primary font-medium">{utilization}%</span>
          </div>
          <div className="h-1.5 w-full bg-border-medium rounded-full overflow-hidden">
            <div 
              className={`h-full rounded-full ${utilization >= 80 ? 'bg-emerald-400' : utilization >= 60 ? 'bg-[#FFD700]' : 'bg-rose-400'}`}
              style={{ width: `${utilization}%` }}
            />
          </div>
        </div>
      </div>
    </motion.div>
  );
}
