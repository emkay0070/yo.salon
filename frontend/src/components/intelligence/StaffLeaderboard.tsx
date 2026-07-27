'use client';

import { motion } from 'framer-motion';
import { Award, Star } from 'lucide-react';

interface StaffIntel {
  id: string;
  name: string;
  rank: number;
  intelligence_score: number;
  score_breakdown: {
    revenue: number;
    repeat_clients: number;
    upsells: number;
    rating: number;
  };
  bookings: number;
  revenue_generated: number;
}

interface StaffLeaderboardProps {
  staff: StaffIntel[];
}

export default function StaffLeaderboard({ staff }: StaffLeaderboardProps) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      className="bg-card border border-border-light rounded-2xl p-6 h-full backdrop-blur-2xl"
    >
      <div className="flex items-center gap-2 mb-6">
        <Award className="w-5 h-5 text-gold" />
        <h2 className="text-lg font-semibold text-text-primary">Staff Intelligence</h2>
      </div>

      <div className="space-y-4">
        {staff.slice(0, 5).map((member, idx) => (
          <div key={member.id} className="bg-surface rounded-xl p-4 border border-border-medium relative">
            <div className="flex items-center justify-between mb-3">
              <div className="flex items-center gap-3">
                <div className={`w-8 h-8 rounded-full flex items-center justify-center font-bold text-obsidian ${
                  idx === 0 ? 'bg-gradient-to-br from-[#FFD700] to-[#C9A227] shadow-[0_0_10px_rgba(255,215,0,0.5)]' :
                  idx === 1 ? 'bg-gradient-to-br from-gray-300 to-gray-500' :
                  idx === 2 ? 'bg-gradient-to-br from-amber-700 to-amber-900' :
                  'bg-surface-light border border-border-light text-text-primary'
                }`}>
                  {idx + 1}
                </div>
                <div>
                  <h3 className="text-sm font-semibold text-text-primary">{member.name}</h3>
                  <p className="text-xs text-text-secondary">{member.bookings} bookings • UGX {member.revenue_generated.toLocaleString()}</p>
                </div>
              </div>
              
              <div className="text-right">
                <div className="text-sm font-bold text-gold">{member.intelligence_score}</div>
                <div className="text-[10px] text-text-muted uppercase">Int. Score</div>
              </div>
            </div>

            {/* Score Breakdown Bar */}
            <div className="h-1.5 w-full bg-surface-light rounded-full flex overflow-hidden">
              <div className="h-full bg-blue-500" style={{ width: `${member.score_breakdown.revenue * 0.4}%` }} title="Revenue"></div>
              <div className="h-full bg-green-500" style={{ width: `${member.score_breakdown.repeat_clients * 0.25}%` }} title="Repeat Clients"></div>
              <div className="h-full bg-purple-500" style={{ width: `${member.score_breakdown.upsells * 0.2}%` }} title="Upsells"></div>
              <div className="h-full bg-yellow-500" style={{ width: `${member.score_breakdown.rating * 0.15}%` }} title="Rating"></div>
            </div>
            <div className="flex justify-between text-[9px] text-text-muted mt-1 uppercase tracking-wider">
              <span>Rev</span>
              <span>Repeat</span>
              <span>Upsell</span>
              <span>Rate</span>
            </div>
          </div>
        ))}
      </div>
    </motion.div>
  );
}
