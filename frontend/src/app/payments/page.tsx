'use client';

import { useState, useCallback, useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import { motion, AnimatePresence } from 'framer-motion';
import {
  CreditCard, Smartphone, Plus, ArrowUpRight, ArrowDownRight,
  Settings, Clock, User, Banknote, Wifi, ChevronLeft, ChevronRight,
  Wallet
} from 'lucide-react';
import DashboardLayout from '@/components/DashboardLayout';
import RequestPaymentModal from '@/components/payments/RequestPaymentModal';
import AddMethodWizard from '@/components/payments/AddMethodWizard';
import { apiClient } from '@/lib/api-client';
import { useRole } from '@/contexts/RoleContext';

// ─── Config Mapping ───────────────────────────────────────────────────────────
// We map the backend "provider" string to frontend visual configurations
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

const formatCurrency = (amount: number) =>
  new Intl.NumberFormat('en-UG', { style: 'currency', currency: 'UGX', minimumFractionDigits: 0 }).format(Math.abs(amount));

const gradientStyle = `
  @keyframes gradientShift {
    0%   { background-position: 0% 50%; }
    50%  { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
  }
  .card-gradient-animated {
    background-size: 200% 200%;
    animation: gradientShift 6s ease infinite;
  }
`;

// ─── Component ────────────────────────────────────────────────────────────────
export default function PaymentsPage() {
  const { salonId } = useRole();

  // State
  const [activeIndex, setActiveIndex] = useState(0);
  const [isRequestModalOpen, setIsRequestModalOpen] = useState(false);
  const [isHovered, setIsHovered] = useState(false);
  const [isWizardOpen, setIsWizardOpen] = useState(false);

  // Queries
  const { data: paymentMethods = [], isLoading: isMethodsLoading } = useQuery({
    queryKey: ['payment-methods', salonId],
    queryFn: () => apiClient.getPaymentMethods({ salon_id: salonId }),
  });

  // Calculate active method
  const activeMethod = useMemo(() => {
    if (paymentMethods.length === 0) return null;
    // ensure activeIndex is in bounds
    return paymentMethods[activeIndex % paymentMethods.length];
  }, [paymentMethods, activeIndex]);

  // Fetch transactions only if we have an active method
  const { data: transactions = [], isLoading: isTxLoading } = useQuery({
    queryKey: ['transactions', salonId, activeMethod?.id],
    queryFn: () => apiClient.getTransactions({ 
      salon_id: salonId, 
      payment_method_id: activeMethod?.id,
      today: true // Future proof filtering
    }),
    enabled: !!activeMethod,
  });

  // Fetch today's summary
  const { data: summary } = useQuery({
    queryKey: ['transaction-summary', salonId],
    queryFn: () => apiClient.getTransactionSummary({ salon_id: salonId }),
  });

  // Fetch settlements
  const { data: settlements = [] } = useQuery({
    queryKey: ['settlements', salonId],
    queryFn: () => apiClient.getSettlements({ salon_id: salonId }),
  });

  // Stack Navigation
  const scrollPrev = useCallback(() => {
    if (paymentMethods.length > 0) {
      setActiveIndex(i => (i - 1 + paymentMethods.length) % paymentMethods.length);
    }
  }, [paymentMethods.length]);
  
  const scrollNext = useCallback(() => {
    if (paymentMethods.length > 0) {
      setActiveIndex(i => (i + 1) % paymentMethods.length);
    }
  }, [paymentMethods.length]);

  const handleWheel = useCallback((e: React.WheelEvent) => {
    if (e.deltaY > 0) scrollNext();
    else scrollPrev();
  }, [scrollNext, scrollPrev]);

  // Loading State
  if (isMethodsLoading) {
    return (
      <DashboardLayout>
        <div className="max-w-[1400px] mx-auto pb-20 overflow-x-hidden flex items-center justify-center min-h-[60vh]">
          <div className="animate-pulse w-10 h-10 rounded-full bg-[#6C5CE7]/20 border border-[#6C5CE7]/30" />
        </div>
      </DashboardLayout>
    );
  }

  // ─── Empty State ────────────────────────────────────────────────────────────
  if (paymentMethods.length === 0) {
    return (
      <DashboardLayout>
        <div className="max-w-[1400px] mx-auto pb-20 overflow-x-hidden">
          <div className="flex flex-col md:flex-row items-start md:items-center justify-between mb-8 gap-6">
            <div>
              <h1 className="text-3xl font-bold text-text-primary tracking-tight">Wallet</h1>
              <p className="text-text-secondary text-sm mt-1">Configure your payment channels.</p>
            </div>
          </div>

          <div className="flex flex-col items-center justify-center min-h-[50vh] text-center max-w-md mx-auto">
            <div className="w-24 h-24 mb-6 rounded-3xl bg-card border border-border-light flex items-center justify-center shadow-2xl relative">
               <Wallet className="w-10 h-10 text-text-primary/50" />
               <div className="absolute inset-0 rounded-3xl bg-gradient-to-tr from-transparent via-white/5 to-transparent pointer-events-none" />
            </div>
            <h2 className="text-2xl font-bold text-text-primary tracking-tight mb-3">Your wallet is empty</h2>
            <p className="text-text-secondary leading-relaxed mb-8">
              Add your first payment channel to start accepting customer payments and tracking revenue.
            </p>
            <button 
              onClick={() => setIsWizardOpen(true)}
              className="flex items-center gap-2 px-6 py-3.5 bg-white text-black hover:bg-gray-100 rounded-2xl font-semibold transition-colors shadow-lg"
            >
              <Plus className="w-5 h-5" />
              Add Payment Method
            </button>
          </div>
        </div>

        <AddMethodWizard 
          isOpen={isWizardOpen} 
          onClose={() => setIsWizardOpen(false)} 
          salonId={salonId || ''} 
        />
      </DashboardLayout>
    );
  }

  // ─── Main View ──────────────────────────────────────────────────────────────
  const activeConfig = activeMethod ? (CHANNEL_CONFIG[activeMethod.provider] || CHANNEL_CONFIG['cash']) : null;
  const ActiveIcon = activeConfig?.Icon || Banknote;

  // Derive stats for the active card based on fetched transactions
  const activeTodayCollected = transactions
    .filter((tx: any) => tx.status === 'completed' || tx.status === 'paid')
    .reduce((sum: number, tx: any) => sum + Number(tx.amount), 0);
  const activeTodayCount = transactions.length;

  return (
    <DashboardLayout>
      <style>{gradientStyle}</style>

      <div className="max-w-[1400px] mx-auto pb-20 overflow-x-hidden">
        <div className="flex flex-col md:flex-row items-start md:items-center justify-between mb-8 gap-6">
          <div>
            <h1 className="text-3xl font-bold text-text-primary tracking-tight">Wallet</h1>
            <p className="text-text-secondary text-sm mt-1">Monitor channels, activity, and settlements.</p>
          </div>
          <div className="flex items-center gap-2 sm:gap-3 w-full sm:w-auto">
            <button 
              onClick={() => setIsWizardOpen(true)}
              className="flex items-center gap-2 px-3 sm:px-5 py-2.5 bg-card border border-border-light rounded-xl text-text-primary hover:bg-white/10 transition-colors font-medium text-sm"
            >
              <Plus className="w-4 h-4" />
              <span className="hidden sm:inline">Add Method</span>
              <span className="sm:hidden">Add</span>
            </button>
          </div>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 mb-8">
          
          {/* ── Left: Interactive Wallet Stack ─────────────────────────────── */}
          <div className="lg:col-span-5 flex flex-col gap-6">
            <div className="bg-card border border-border-light rounded-3xl p-8 flex flex-col items-center">
              
              <div
                className="relative w-full max-w-sm select-none"
                style={{ height: '250px' }}
                onWheel={handleWheel}
                onMouseEnter={() => setIsHovered(true)}
                onMouseLeave={() => setIsHovered(false)}
              >
                {paymentMethods.map((method: any, index: number) => {
                  const config = CHANNEL_CONFIG[method.provider] || CHANNEL_CONFIG['cash'];
                  const Icon = config.Icon;
                  const offset = index - activeIndex;
                  const isActive = offset === 0;

                  const wrappedOffset = ((offset + paymentMethods.length) % paymentMethods.length);
                  const displayOffset = wrappedOffset <= Math.floor(paymentMethods.length / 2)
                    ? wrappedOffset
                    : wrappedOffset - paymentMethods.length;

                  const absOffset = Math.abs(displayOffset);
                  
                  // Fanning logic: when hovered, increase the Y gap between cards
                  const fanSpread = isHovered ? 25 : 14; 
                  const y = isActive ? 40 : 40 + absOffset * fanSpread;
                  const scale = isActive ? 1.03 : Math.max(0.78, 0.95 - absOffset * 0.055);
                  const opacity = isActive ? 1 : Math.max(0.3, 0.85 - absOffset * 0.2);
                  const zIndex = isActive ? 50 : 50 - absOffset * 5;

                  return (
                    <motion.div
                      key={method.id}
                      onClick={() => setActiveIndex(index)}
                      animate={{ y, scale, zIndex, opacity }}
                      transition={{ type: 'spring', stiffness: 340, damping: 34, mass: 0.75 }}
                      className={`absolute top-0 left-0 w-full h-[200px] rounded-[24px] p-7 cursor-pointer border ${config.border} shadow-2xl flex flex-col justify-between overflow-hidden card-gradient-animated`}
                      style={{ background: config.gradient }}
                    >
                      <div className="absolute inset-0 bg-gradient-to-b from-white/10 to-transparent pointer-events-none rounded-[24px]" />
                      <div className="absolute -top-10 -right-10 w-40 h-40 bg-white/10 rounded-full blur-2xl pointer-events-none" />

                      {isActive && (
                        <motion.div
                          initial={{ scale: 0.8, opacity: 0.25 }}
                          animate={{ scale: 1.8, opacity: 0 }}
                          transition={{ duration: 2.5, ease: 'easeOut', repeat: Infinity, repeatDelay: 1.5 }}
                          className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-20 h-20 bg-white/20 rounded-full pointer-events-none"
                        />
                      )}

                      <div className="relative z-10 flex justify-between items-start">
                        <div className="flex items-center gap-3">
                          <div className="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center">
                            <Icon className="w-4 h-4 text-text-primary" />
                          </div>
                          <span className="text-text-primary font-semibold tracking-wide drop-shadow">{method.display_name}</span>
                        </div>
                        {method.is_primary ? (
                          <div className="px-3 py-1 rounded-full bg-surface text-text-primary text-xs font-medium border border-white/20 backdrop-blur-sm">
                            Primary
                          </div>
                        ) : null}
                      </div>

                      <div className="relative z-10">
                        <p className="text-text-primary/70 text-xs mb-1 uppercase tracking-wider">
                          {method.type.replace('_', ' ')}
                        </p>
                        
                        {/* Only show "Today's Collections" if active to prevent fetching 5 channels data at once without API bulk support */}
                        {isActive ? (
                          <div className="flex items-end justify-between mt-2">
                            <div>
                              <h2 className="text-2xl font-bold text-text-primary tracking-tight drop-shadow-sm">
                                {formatCurrency(activeTodayCollected)}
                              </h2>
                            </div>
                            <div className="text-right">
                              <p className="text-text-primary font-bold text-xl">{activeTodayCount}</p>
                            </div>
                          </div>
                        ) : (
                          <div className="flex items-end justify-between mt-2">
                            <div className="h-8 flex items-center">
                              <span className="text-text-primary/40 text-sm italic">Select to view</span>
                            </div>
                          </div>
                        )}
                        
                        {method.account_identifier && (
                          <p className="text-text-primary/40 font-mono text-xs mt-3 tracking-[0.2em]">{method.account_identifier}</p>
                        )}
                      </div>
                    </motion.div>
                  );
                })}
              </div>

              {/* Scroll controls */}
              <div className="flex items-center gap-4 mt-6">
                <button
                  onClick={scrollPrev}
                  className="w-8 h-8 rounded-full bg-card border border-border-light flex items-center justify-center hover:bg-white/10 transition-colors"
                >
                  <ChevronLeft className="w-4 h-4 text-text-primary/70" />
                </button>
                <div className="flex items-center gap-2">
                  {paymentMethods.map((m: any, i: number) => (
                    <button
                      key={m.id}
                      onClick={() => setActiveIndex(i)}
                      className={`h-1.5 rounded-full transition-all duration-300 ${
                        i === activeIndex ? 'w-6 bg-[#6C5CE7]' : 'w-1.5 bg-white/20 hover:bg-white/40'
                      }`}
                    />
                  ))}
                </div>
                <button
                  onClick={scrollNext}
                  className="w-8 h-8 rounded-full bg-card border border-border-light flex items-center justify-center hover:bg-white/10 transition-colors"
                >
                  <ChevronRight className="w-4 h-4 text-text-primary/70" />
                </button>
              </div>

              {/* Context Action Buttons */}
              <div className="grid grid-cols-2 gap-3 w-full mt-6">
                <button
                  onClick={() => setIsRequestModalOpen(true)}
                  className="flex items-center justify-center gap-2 p-3.5 rounded-2xl bg-[#6C5CE7]/10 border border-[#6C5CE7]/30 hover:bg-[#6C5CE7]/20 transition-colors text-[#A29BFE] font-medium text-sm"
                >
                  <ArrowUpRight className="w-4 h-4" />
                  Request via {activeMethod.display_name.split(' ')[0]}
                </button>
                <button className="flex items-center justify-center gap-2 p-3.5 rounded-2xl bg-card border border-border-light hover:bg-white/10 transition-colors text-text-primary font-medium text-sm">
                  <Settings className="w-4 h-4 text-text-primary/70" />
                  Channel Settings
                </button>
              </div>
            </div>
          </div>

          {/* ── Right: Contextual Activity Feed ────────────────────────────── */}
          <div className="lg:col-span-7">
            <div className="bg-card border border-border-light rounded-3xl p-8 h-full flex flex-col">
              
              <AnimatePresence mode="wait">
                <motion.div
                  key={activeMethod.id}
                  initial={{ opacity: 0, y: -8 }}
                  animate={{ opacity: 1, y: 0 }}
                  exit={{ opacity: 0, y: 8 }}
                  transition={{ duration: 0.2 }}
                  className="flex items-center justify-between mb-6"
                >
                  <div className="flex items-center gap-3">
                    <div 
                      className={`w-9 h-9 rounded-xl flex items-center justify-center border ${activeConfig?.border}`}
                      style={{ background: activeConfig?.gradient }}
                    >
                      <ActiveIcon className="w-4 h-4 text-text-primary" />
                    </div>
                    <div>
                      <h2 className="text-lg font-bold text-text-primary tracking-tight">{activeMethod.display_name}</h2>
                      <p className="text-text-secondary text-xs">{activeTodayCount} transactions today</p>
                    </div>
                  </div>
                </motion.div>
              </AnimatePresence>

              <AnimatePresence mode="wait">
                <motion.div
                  key={`stats-${activeMethod.id}`}
                  initial={{ opacity: 0 }}
                  animate={{ opacity: 1 }}
                  exit={{ opacity: 0 }}
                  transition={{ duration: 0.2 }}
                  className="grid grid-cols-2 gap-4 mb-6 p-4 rounded-2xl bg-white/[0.02] border border-white/5"
                >
                  <div>
                    <p className="text-text-secondary text-xs mb-1">Today's Volume</p>
                    <p className="text-text-primary font-semibold text-lg">{formatCurrency(activeTodayCollected)}</p>
                  </div>
                  <div>
                    <p className="text-text-secondary text-xs mb-1">Transactions</p>
                    <p className="text-text-primary font-semibold text-lg">{activeTodayCount}</p>
                  </div>
                </motion.div>
              </AnimatePresence>

              <div className="flex-1 space-y-5 overflow-y-auto">
                <AnimatePresence mode="wait">
                  {transactions.length === 0 ? (
                    <motion.div
                      key="empty"
                      initial={{ opacity: 0 }}
                      animate={{ opacity: 1 }}
                      exit={{ opacity: 0 }}
                      className="relative flex flex-col items-center justify-center py-16 text-center rounded-2xl overflow-hidden border border-border-light"
                    >
                      <div className="absolute inset-0 z-0">
                        <img src="/images/salon_reception.png" alt="Reception" className="w-full h-full object-cover opacity-20" />
                        <div className="absolute inset-0 bg-gradient-to-t from-[#121212] via-[#121212]/80 to-transparent"></div>
                      </div>
                      <div className="relative z-10">
                        <div className="w-14 h-14 mx-auto rounded-2xl bg-card border border-border-light flex items-center justify-center mb-4 shadow-xl backdrop-blur-md">
                          <ActiveIcon className="w-6 h-6 text-text-secondary" />
                        </div>
                        <p className="text-text-primary font-medium mb-1">No transactions today</p>
                        <p className="text-text-secondary text-sm">Transactions via {activeMethod.display_name} will appear here.</p>
                      </div>
                    </motion.div>
                  ) : (
                    <motion.div
                      key={`list-${activeMethod.id}`}
                      initial={{ opacity: 0 }}
                      animate={{ opacity: 1 }}
                      exit={{ opacity: 0 }}
                      transition={{ duration: 0.2 }}
                      className="space-y-5"
                    >
                      {transactions.map((tx: any, i: number) => {
                        const isPaid = tx.status === 'completed' || tx.status === 'paid';
                        const isRefund = tx.status === 'refunded';
                        const isSettlement = tx.type === 'settlement';

                        return (
                          <motion.div
                            key={tx.id}
                            initial={{ opacity: 0, x: 10 }}
                            animate={{ opacity: 1, x: 0 }}
                            transition={{ delay: i * 0.05 }}
                            className="flex items-center justify-between group"
                          >
                            <div className="flex items-center gap-4">
                              <div className="w-10 h-10 rounded-full bg-white/10 border border-white/20 flex items-center justify-center flex-shrink-0">
                                {isSettlement
                                  ? <Banknote className="w-5 h-5 text-[#6C5CE7]" />
                                  : <User className="w-5 h-5 text-text-secondary" />
                                }
                              </div>
                              <div>
                                <h4 className="text-text-primary font-medium group-hover:text-[#6C5CE7] transition-colors">
                                  {tx.customer?.first_name ? `${tx.customer.first_name} ${tx.customer.last_name || ''}` : (isSettlement ? 'Settlement' : 'Walk-in')}
                                </h4>
                                <p className="text-text-secondary text-sm">{tx.booking?.service?.name || tx.notes || 'Payment'}</p>
                              </div>
                            </div>

                            <div className="hidden sm:block text-right">
                              <span className="text-text-secondary text-sm">
                                {new Date(tx.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                              </span>
                            </div>

                            <div className="flex items-center gap-4 min-w-[140px] justify-end">
                              <div className={`px-2.5 py-1 rounded-md text-xs font-medium uppercase tracking-wider ${
                                isPaid    ? 'bg-emerald-500/10 text-emerald-400' :
                                isRefund  ? 'bg-red-500/10 text-red-400' :
                                            'bg-yellow-500/10 text-yellow-400'
                              }`}>
                                {tx.status}
                              </div>
                              <span className={`font-semibold min-w-[90px] text-right ${isRefund ? 'text-red-400' : 'text-text-primary'}`}>
                                {isRefund ? '-' : ''}{formatCurrency(Number(tx.amount))}
                              </span>
                            </div>
                          </motion.div>
                        );
                      })}
                    </motion.div>
                  )}
                </AnimatePresence>
              </div>
            </div>
          </div>
        </div>

        {/* ── Bottom: Summary + Settlements ─────────────────────────────────── */}
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-8">
          
          <div className="lg:col-span-8 bg-card border border-border-light rounded-3xl p-8">
            <div className="flex items-center justify-between mb-8">
              <h2 className="text-xl font-bold text-text-primary tracking-tight">Today's Summary</h2>
              <button className="flex items-center gap-2 text-sm font-medium text-text-secondary bg-card px-4 py-2 rounded-full border border-border-light hover:text-text-primary transition-colors">
                Today <ArrowDownRight className="w-3 h-3" />
              </button>
            </div>
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-6">
              {[
                { label: 'Total Received', value: formatCurrency(summary?.total_received || 0), trend: '+18%', up: true,  Icon: Banknote,       color: 'bg-[#6C5CE7]/20 text-[#6C5CE7]' },
                { label: 'Transactions',   value: summary?.count || 0,                          trend: '+12%', up: true,  Icon: CreditCard,     color: 'bg-blue-500/20 text-blue-400'   },
                { label: 'Average Sale',   value: formatCurrency(summary?.average_sale || 0),   trend: '-5%',  up: false, Icon: Clock,          color: 'bg-orange-500/20 text-orange-400'},
                { label: 'Refunds',        value: formatCurrency(summary?.refunds || 0),        trend: '-8%',  up: false, Icon: ArrowDownRight, color: 'bg-red-500/20 text-red-400'     },
              ].map(stat => (
                <div key={stat.label}>
                  <div className={`w-8 h-8 rounded-lg flex items-center justify-center mb-3 ${stat.color}`}>
                    <stat.Icon className="w-4 h-4" />
                  </div>
                  <p className="text-text-secondary text-sm mb-1">{stat.label}</p>
                  <h3 className="text-xl font-bold text-text-primary mb-2">{stat.value}</h3>
                </div>
              ))}
            </div>
          </div>

          <div className="lg:col-span-4 bg-card border border-border-light rounded-3xl p-8">
            <div className="flex items-center justify-between mb-8">
              <h2 className="text-xl font-bold text-text-primary tracking-tight">Settlements</h2>
            </div>
            
            {settlements.length === 0 ? (
              <div className="bg-card border border-border-light rounded-2xl p-6 text-center text-text-secondary">
                No pending settlements.
              </div>
            ) : (
              <div className="bg-card border border-border-light rounded-2xl p-6">
                <div className="flex items-center gap-3 mb-6">
                  <div className="w-8 h-8 rounded-full bg-[#FFD700] flex items-center justify-center">
                    <span className="text-obsidian text-xs font-bold">MTN</span>
                  </div>
                  <h4 className="text-text-primary font-medium">Next Payout</h4>
                </div>
                <div className="flex justify-between items-end">
                  <div>
                    <p className="text-text-secondary text-sm mb-1">Status</p>
                    <p className="text-emerald-400 font-medium">Processing</p>
                  </div>
                  <div className="text-right">
                    <p className="text-text-secondary text-sm mb-1">Estimated Amount</p>
                    <p className="text-xl font-bold text-text-primary">{formatCurrency(settlements[0]?.amount || 0)}</p>
                  </div>
                </div>
              </div>
            )}
          </div>
        </div>
      </div>

      <RequestPaymentModal
        isOpen={isRequestModalOpen}
        onClose={() => setIsRequestModalOpen(false)}
        preselectedMethod={activeMethod.provider}
        onSuccess={(tx) => {
          setIsRequestModalOpen(false);
        }}
      />
      
      <AddMethodWizard 
        isOpen={isWizardOpen} 
        onClose={() => setIsWizardOpen(false)} 
        salonId={salonId || ''} 
      />
    </DashboardLayout>
  );
}
