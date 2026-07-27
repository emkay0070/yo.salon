'use client';

import { motion } from 'framer-motion';
import { ShieldAlert, UserX, MessageCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';

interface ChurnRisk {
  customer_id: string;
  name: string;
  risk_score: number;
  risk_label: 'critical' | 'high' | 'medium';
  days_since_visit: number;
  lifetime_value: number;
  recommended_action: string;
}

interface ChurnRadarProps {
  risks: ChurnRisk[];
}

export default function ChurnRadar({ risks }: ChurnRadarProps) {
  if (risks.length === 0) {
    return (
      <div className="bg-card border border-border-light rounded-2xl p-6 h-full flex flex-col items-center justify-center text-center">
        <ShieldAlert className="w-12 h-12 text-green-500/50 mb-4" />
        <h3 className="text-text-primary font-medium">Customer base healthy</h3>
        <p className="text-text-secondary text-sm mt-1">No significant churn risks detected.</p>
      </div>
    );
  }

  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      className="bg-card border border-border-light rounded-2xl p-6 h-full backdrop-blur-2xl"
    >
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center gap-2">
          <UserX className="w-5 h-5 text-red-400" />
          <h2 className="text-lg font-semibold text-text-primary">Churn Radar</h2>
        </div>
        <span className="bg-red-500/10 text-red-400 text-xs px-2.5 py-1 rounded-full font-medium">
          {risks.length} At Risk
        </span>
      </div>

      <div className="space-y-4">
        {risks.slice(0, 4).map((risk) => (
          <div key={risk.customer_id} className="bg-surface rounded-xl p-4 border border-border-medium relative overflow-hidden group">
            {/* Risk Indicator Line */}
            <div className={`absolute left-0 top-0 bottom-0 w-1 ${
              risk.risk_label === 'critical' ? 'bg-red-500' :
              risk.risk_label === 'high' ? 'bg-orange-500' : 'bg-yellow-500'
            }`}></div>

            <div className="flex justify-between items-start pl-2">
              <div>
                <h3 className="text-sm font-semibold text-text-primary flex items-center gap-2">
                  {risk.name}
                  <span className={`text-[10px] uppercase font-bold tracking-wider ${
                    risk.risk_label === 'critical' ? 'text-red-400' :
                    risk.risk_label === 'high' ? 'text-orange-400' : 'text-yellow-400'
                  }`}>
                    {risk.risk_score}% RISK
                  </span>
                </h3>
                <p className="text-xs text-text-secondary mt-1">
                  Last visit: <span className="text-text-primary">{risk.days_since_visit} days ago</span>
                </p>
                <p className="text-xs text-text-secondary">
                  Lifetime Value: <span className="text-gold font-medium">UGX {risk.lifetime_value.toLocaleString()}</span>
                </p>
              </div>

              <Button size="sm" variant="glass" className="h-8 px-3 text-xs gap-1.5 bg-white/5 hover:bg-white/10">
                <MessageCircle className="w-3.5 h-3.5" />
                Action
              </Button>
            </div>
            
            <div className="mt-3 pl-2 pt-3 border-t border-border-light">
              <p className="text-xs text-gold flex items-center gap-1.5 font-medium">
                <span className="w-1.5 h-1.5 rounded-full bg-gold"></span>
                Recommend: {risk.recommended_action}
              </p>
            </div>
          </div>
        ))}
        {risks.length > 4 && (
          <p className="text-center text-xs text-text-muted mt-2">
            + {risks.length - 4} more at-risk customers
          </p>
        )}
      </div>
    </motion.div>
  );
}
