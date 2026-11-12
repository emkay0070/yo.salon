'use client';

import { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { X, ArrowRight, Settings, Eye, Plus, Smartphone, CreditCard, Wifi, Banknote } from 'lucide-react';
import WalkInSale from './WalkInSale';
import { formatCurrency } from './LedgerTable';

type ChannelId = 'flutterwave' | 'visa' | 'mtn' | 'airtel' | 'cash';

interface ChannelDrawerProps {
  isOpen: boolean;
  onClose: () => void;
  channel: {
    id: string;
    provider: ChannelId;
    display_name: string;
    type: string;
    account_identifier?: string;
    is_primary?: boolean;
  };
  transactions: any[];
  providerId: string;
}

const CHANNEL_CONFIG: Record<string, { Icon: React.ElementType; gradient: string; border: string }> = {
  flutterwave: {
    Icon: Wifi,
    gradient: 'linear-gradient(135deg, #4C3EBF, #6C5CE7, #A29BFE, #6C5CE7)',
    border: 'border-[#6C5CE7]/40',
  },
  mtn: {
    Icon: Smartphone,
    gradient: 'linear-gradient(135deg, #8B6914, #C9A227, #FFD700, #C9A227)',
    border: 'border-gold/40',
  },
  airtel: {
    Icon: Smartphone,
    gradient: 'linear-gradient(135deg, #8B1A10, #C0392B, #FF6B6B, #C0392B)',
    border: 'border-red-500/40',
  },
  visa: {
    Icon: CreditCard,
    gradient: 'linear-gradient(135deg, #0D1B6E, #1a237e, #3949AB, #1a237e)',
    border: 'border-blue-500/40',
  },
  cash: {
    Icon: Banknote,
    gradient: 'linear-gradient(135deg, #0A3D15, #1B5E20, #43A047, #1B5E20)',
    border: 'border-emerald-500/40',
  },
};

export default function ChannelDrawer({
  isOpen,
  onClose,
  channel,
  transactions,
  providerId,
}: ChannelDrawerProps) {
  const [isWalkInSaleOpen, setIsWalkInSaleOpen] = useState(false);
  
  const config = CHANNEL_CONFIG[channel.provider] || CHANNEL_CONFIG['cash'];
  const Icon = config.Icon;

  // Calculate today's stats
  const todayTransactions = transactions.filter((tx: any) => {
    const txDate = new Date(tx.created_at);
    const today = new Date();
    return txDate.toDateString() === today.toDateString();
  });

  const todayTotal = todayTransactions.reduce((sum: number, tx: any) => {
    if (tx.status === 'completed' || tx.status === 'paid') {
      return sum + Number(tx.gross_amount);
    }
    return sum;
  }, 0);

  const successfulCount = todayTransactions.filter((tx: any) => tx.status === 'completed' || tx.status === 'paid').length;
  const pendingCount = todayTransactions.filter((tx: any) => tx.status === 'pending').length;
  const failedCount = todayTransactions.filter((tx: any) => tx.status === 'failed').length;

  const isCash = channel.provider === 'cash';
  const isDigital = !isCash;

  return (
    <>
      <AnimatePresence>
        {isOpen && (
          <>
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              onClick={onClose}
              className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50"
            />
            <motion.div
              initial={{ x: '100%' }}
              animate={{ x: 0 }}
              exit={{ x: '100%' }}
              transition={{ type: 'spring', damping: 30, stiffness: 300 }}
              className="fixed right-0 top-0 h-full w-full max-w-md bg-[#111111] border-l border-border-light z-50 overflow-y-auto"
            >
              {/* Header */}
              <div className={`p-6 bg-gradient-to-br ${config.gradient} border-b ${config.border}`}>
                <div className="flex items-center justify-between mb-4">
                  <div className="flex items-center gap-3">
                    <div className="w-10 h-10 rounded-xl bg-white/10 border border-white/20 flex items-center justify-center">
                      <Icon className="w-5 h-5 text-white" />
                    </div>
                    <div>
                      <h2 className="text-xl font-bold text-white">{channel.display_name}</h2>
                      <p className="text-white/60 text-sm">{channel.type.replace('_', ' ')}</p>
                    </div>
                  </div>
                  <button
                    onClick={onClose}
                    className="w-8 h-8 rounded-full bg-black/20 hover:bg-black/40 flex items-center justify-center text-white/70 hover:text-white transition-colors"
                  >
                    <X className="w-4 h-4" />
                  </button>
                </div>

                {channel.account_identifier && (
                  <p className="text-white/40 font-mono text-xs tracking-[0.2em]">{channel.account_identifier}</p>
                )}
              </div>

              {/* Content */}
              <div className="p-6">
                {/* Today's Summary */}
                <div className="mb-8">
                  <h3 className="text-text-secondary text-sm font-medium mb-4 uppercase tracking-wider">Today</h3>
                  
                  <div className="bg-card border border-border-light rounded-2xl p-6 mb-4">
                    <div className="flex items-end justify-between mb-2">
                      <span className="text-text-secondary text-sm">Total collected</span>
                      <span className="text-2xl font-bold text-text-primary">{formatCurrency(todayTotal)}</span>
                    </div>
                    <div className="flex items-center justify-between text-sm">
                      <span className="text-text-secondary">Transactions</span>
                      <span className="text-text-primary font-medium">{todayTransactions.length}</span>
                    </div>
                  </div>

                  {isDigital && (
                    <div className="grid grid-cols-3 gap-3">
                      <div className="bg-emerald-500/10 border border-emerald-500/20 rounded-xl p-3 text-center">
                        <p className="text-emerald-400 text-2xl font-bold">{successfulCount}</p>
                        <p className="text-emerald-400/70 text-xs mt-1">Successful</p>
                      </div>
                      <div className="bg-orange-500/10 border border-orange-500/20 rounded-xl p-3 text-center">
                        <p className="text-orange-400 text-2xl font-bold">{pendingCount}</p>
                        <p className="text-orange-400/70 text-xs mt-1">Pending</p>
                      </div>
                      <div className="bg-red-500/10 border border-red-500/20 rounded-xl p-3 text-center">
                        <p className="text-red-400 text-2xl font-bold">{failedCount}</p>
                        <p className="text-red-400/70 text-xs mt-1">Failed</p>
                      </div>
                    </div>
                  )}

                  {isCash && (
                    <div className="space-y-3">
                      <div className="flex items-center justify-between p-3 bg-card border border-border-light rounded-xl">
                        <span className="text-text-secondary text-sm">Cash received</span>
                        <span className="text-text-primary font-semibold">{formatCurrency(todayTotal)}</span>
                      </div>
                      <div className="flex items-center justify-between p-3 bg-card border border-border-light rounded-xl">
                        <span className="text-text-secondary text-sm">Refunds</span>
                        <span className="text-text-primary font-semibold">{formatCurrency(0)}</span>
                      </div>
                      <div className="flex items-center justify-between p-3 bg-emerald-500/10 border border-emerald-500/20 rounded-xl">
                        <span className="text-emerald-400 text-sm font-medium">Net cash</span>
                        <span className="text-emerald-400 font-bold">{formatCurrency(todayTotal)}</span>
                      </div>
                    </div>
                  )}
                </div>

                {/* Actions */}
                <div className="space-y-3 mb-8">
                  <button
                    className="w-full flex items-center justify-between p-4 bg-card border border-border-light rounded-2xl hover:bg-white/10 transition-colors group"
                  >
                    <div className="flex items-center gap-3">
                      <Eye className="w-5 h-5 text-text-secondary group-hover:text-text-primary transition-colors" />
                      <span className="text-text-primary font-medium">View transactions</span>
                    </div>
                    <ArrowRight className="w-4 h-4 text-text-secondary group-hover:text-text-primary transition-colors" />
                  </button>

                  {isCash && (
                    <button
                      onClick={() => setIsWalkInSaleOpen(true)}
                      className="w-full flex items-center justify-between p-4 bg-[#6C5CE7]/10 border border-[#6C5CE7]/30 rounded-2xl hover:bg-[#6C5CE7]/20 transition-colors group"
                    >
                      <div className="flex items-center gap-3">
                        <Plus className="w-5 h-5 text-[#A29BFE] group-hover:text-[#A29BFE] transition-colors" />
                        <span className="text-[#A29BFE] font-medium">Record cash payment</span>
                      </div>
                      <ArrowRight className="w-4 h-4 text-[#A29BFE] group-hover:text-[#A29BFE] transition-colors" />
                    </button>
                  )}

                  {isDigital && (
                    <button
                      className="w-full flex items-center justify-between p-4 bg-card border border-border-light rounded-2xl hover:bg-white/10 transition-colors group"
                    >
                      <div className="flex items-center gap-3">
                        <Settings className="w-5 h-5 text-text-secondary group-hover:text-text-primary transition-colors" />
                        <span className="text-text-primary font-medium">Payment settings</span>
                      </div>
                      <ArrowRight className="w-4 h-4 text-text-secondary group-hover:text-text-primary transition-colors" />
                    </button>
                  )}
                </div>

                {/* Recent Transactions Preview */}
                {todayTransactions.length > 0 && (
                  <div>
                    <h3 className="text-text-secondary text-sm font-medium mb-4 uppercase tracking-wider">Recent</h3>
                    <div className="space-y-2">
                      {todayTransactions.slice(0, 5).map((tx: any) => (
                        <div key={tx.id} className="p-3 bg-card border border-border-light rounded-xl">
                          <div className="flex items-center justify-between mb-1">
                            <span className="text-text-primary text-sm font-medium">
                              {tx.booking?.customer?.name || 'Unknown'}
                            </span>
                            <span className="text-text-primary text-sm font-semibold">
                              {formatCurrency(tx.gross_amount)}
                            </span>
                          </div>
                          <div className="flex items-center justify-between text-xs text-text-secondary">
                            <span>{new Date(tx.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>
                            <span className={`capitalize ${
                              tx.status === 'completed' || tx.status === 'paid' 
                                ? 'text-emerald-400' 
                                : tx.status === 'pending' 
                                  ? 'text-orange-400' 
                                  : 'text-red-400'
                            }`}>
                              {tx.status}
                            </span>
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                )}
              </div>
            </motion.div>
          </>
        )}
      </AnimatePresence>

      {/* Walk-in Sale Modal */}
      <WalkInSale
        isOpen={isWalkInSaleOpen}
        onClose={() => setIsWalkInSaleOpen(false)}
        providerId={providerId}
      />
    </>
  );
}
