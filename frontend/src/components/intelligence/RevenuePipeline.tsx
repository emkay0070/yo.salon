'use client';

import { motion } from 'framer-motion';
import { ArrowDown, DollarSign } from 'lucide-react';

interface RevenuePipelineProps {
  revenue: {
    gross: number;
    gateway_fees: number;
    platform_fees: number;
    refunds: number;
    net: number;
    settlement_pending: number;
    cash_in_hand: number;
  };
  fees: {
    gross_pct: number;
    gateway_pct: number;
    platform_pct: number;
    refunds_pct: number;
    net_pct: number;
  };
}

export default function RevenuePipeline({ revenue, fees }: RevenuePipelineProps) {
  const formatAmt = (amt: number) => `UGX ${amt.toLocaleString()}`;

  const nodes = [
    { label: 'Gross Revenue', amount: revenue.gross, pct: fees.gross_pct, color: 'text-text-primary' },
    { label: 'Gateway Fees', amount: revenue.gateway_fees, pct: fees.gateway_pct, color: 'text-red-400', isDeduction: true },
    { label: 'Platform Fees', amount: revenue.platform_fees, pct: fees.platform_pct, color: 'text-orange-400', isDeduction: true },
    { label: 'Refunds', amount: revenue.refunds, pct: fees.refunds_pct, color: 'text-yellow-400', isDeduction: true },
    { label: 'Net Revenue', amount: revenue.net, pct: fees.net_pct, color: 'text-gold', isImportant: true },
    { label: 'Pending Settlement', amount: revenue.settlement_pending, pct: null, color: 'text-text-secondary', isDeduction: true },
    { label: 'Cash in Hand', amount: revenue.cash_in_hand, pct: null, color: 'text-green-400', isImportant: true },
  ];

  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      className="bg-card border border-border-light rounded-2xl p-6 backdrop-blur-2xl h-full flex flex-col"
    >
      <div className="flex items-center gap-2 mb-8">
        <DollarSign className="w-5 h-5 text-gold" />
        <h2 className="text-lg font-semibold text-text-primary">Revenue Pipeline</h2>
      </div>

      <div className="flex-1 flex flex-col justify-center max-w-sm mx-auto w-full relative">
        {/* Connecting line */}
        <div className="absolute left-6 top-6 bottom-6 w-0.5 bg-border-medium/50 z-0 border-l border-dashed border-border-medium"></div>

        {nodes.map((node, i) => (
          <div key={node.label} className={`relative z-10 flex items-center justify-between py-3 ${node.isImportant ? 'my-2 bg-surface/50 p-3 rounded-xl border border-border-medium' : ''}`}>
            
            {/* The flow indicator icon */}
            <div className={`w-12 flex justify-center ${node.isDeduction ? 'text-text-muted' : 'text-gold'}`}>
              {i === 0 ? (
                <div className="w-3 h-3 rounded-full bg-gold shadow-[0_0_8px_rgba(255,215,0,0.8)]"></div>
              ) : i === nodes.length - 1 ? (
                <div className="w-3 h-3 rounded-full bg-green-500 shadow-[0_0_8px_rgba(34,197,94,0.8)]"></div>
              ) : node.isImportant ? (
                <div className="w-2.5 h-2.5 rounded-full bg-gold"></div>
              ) : (
                <ArrowDown className="w-4 h-4" />
              )}
            </div>

            <div className="flex-1">
              <span className={`text-sm ${node.isImportant ? 'font-semibold text-text-primary' : 'text-text-secondary'}`}>
                {node.label}
              </span>
            </div>

            <div className="text-right flex items-center gap-3">
              <span className={`text-sm font-medium ${node.color} tracking-tight`}>
                {node.isDeduction && node.amount > 0 ? '-' : ''}{formatAmt(node.amount)}
              </span>
              {node.pct !== null && (
                <span className="text-xs text-text-muted w-10 text-right opacity-60">
                  ({node.pct}%)
                </span>
              )}
              {node.pct === null && <span className="w-10"></span>}
            </div>
          </div>
        ))}
      </div>
    </motion.div>
  );
}
