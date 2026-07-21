'use client';

import { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { 
  CreditCard, Smartphone, CheckCircle, RefreshCcw, 
  ArrowRight, Star, Download, CalendarPlus, Check, Plus
} from 'lucide-react';
import ClientLayout from '@/components/ClientLayout';

const personalMethods = [
  {
    id: 'visa',
    name: 'VISA',
    number: '**** **** **** 4242',
    type: 'Credit',
    icon: CreditCard,
    gradient: 'from-blue-900/60 to-indigo-900/20',
    border: 'border-blue-500/20'
  },
  {
    id: 'mastercard',
    name: 'MASTERCARD',
    number: '**** **** **** 8899',
    type: 'Debit',
    icon: CreditCard,
    gradient: 'from-orange-900/60 to-red-900/20',
    border: 'border-orange-500/20'
  },
  {
    id: 'mtn',
    name: 'MTN MOBILE MONEY',
    number: '077 *** **89',
    type: 'Mobile',
    icon: Smartphone,
    gradient: 'from-[#C9A227]/20 to-[#FFD700]/5',
    border: 'border-[#FFD700]/20'
  }
];

const purchaseHistory = [
  {
    id: '1',
    service: 'Executive Haircut',
    amount: 60000,
    status: 'completed',
    time: 'Yesterday',
    receiptUrl: '#'
  },
  {
    id: '2',
    service: 'Beard Trim',
    amount: 35000,
    status: 'completed',
    time: 'Last Week',
    receiptUrl: '#'
  },
  {
    id: '3',
    service: 'Hair Coloring',
    amount: 120000,
    status: 'refunded',
    time: 'Last Month',
    receiptUrl: '#'
  }
];

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-UG', {
    style: 'currency',
    currency: 'UGX',
    minimumFractionDigits: 0
  }).format(amount);
};

export default function ClientWalletPage() {
  const [activeCard, setActiveCard] = useState(personalMethods[0].id);
  const [isHoveringStack, setIsHoveringStack] = useState(false);
  const [showCelebration, setShowCelebration] = useState(true); // Toggle for demo purposes
  const [walletBalance, setWalletBalance] = useState(20000);

  return (
    <ClientLayout>
      <div className="pb-20">

        {/* Celebration State (Shown immediately after a successful appointment/payment) */}
        <AnimatePresence>
          {showCelebration && (
            <motion.div
              initial={{ opacity: 0, y: -20 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, scale: 0.95 }}
              className="mb-12 relative overflow-hidden bg-gradient-to-b from-emerald-900/20 to-transparent border border-emerald-500/20 rounded-3xl p-8 md:p-12 flex flex-col items-center text-center"
            >
              <button 
                onClick={() => setShowCelebration(false)}
                className="absolute top-6 right-6 text-text-secondary hover:text-text-primary transition-colors"
              >
                Dismiss
              </button>
              
              <div className="w-16 h-16 rounded-full bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center mb-6">
                <Check className="w-8 h-8 text-emerald-400" />
              </div>
              
              <h2 className="text-2xl md:text-3xl font-medium text-emerald-400 mb-2">Payment Successful</h2>
              <p className="text-text-secondary text-lg mb-10 max-w-md">
                Thanks for visiting Executive Salon. See you again soon.
              </p>

              <div className="flex flex-wrap justify-center gap-4">
                <button className="flex items-center gap-2 px-6 py-3 bg-card hover:bg-white/10 border border-border-light rounded-full text-text-primary transition-colors">
                  <Star className="w-4 h-4 text-gold" />
                  Leave a tip
                </button>
                <button className="flex items-center gap-2 px-6 py-3 bg-card hover:bg-white/10 border border-border-light rounded-full text-text-primary transition-colors">
                  <Download className="w-4 h-4" />
                  Download receipt
                </button>
                <button className="flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-obsidian font-medium hover:opacity-90 rounded-full transition-opacity">
                  <CalendarPlus className="w-4 h-4" />
                  Book again
                </button>
              </div>
            </motion.div>
          )}
        </AnimatePresence>

        {/* Client Hero */}
        <div className="mb-12">
          <h1 className="text-3xl font-semibold text-text-primary tracking-tight mb-8">Good afternoon, Sarah.</h1>
          
          <div className="flex items-end gap-6">
            <div>
              <p className="text-text-secondary text-sm font-medium mb-2 uppercase tracking-wider">Wallet Balance</p>
              <h2 className="text-5xl font-light text-text-primary tracking-tight">
                {formatCurrency(walletBalance)}
              </h2>
            </div>
            <div className="mb-2">
              <span className="text-sm font-medium text-emerald-400 bg-emerald-500/10 px-3 py-1.5 rounded-lg border border-emerald-500/20">
                Available for your next visit
              </span>
            </div>
          </div>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-12 gap-12 mt-16">
          
          {/* Left Column: Personal Payment Methods */}
          <div className="lg:col-span-5">
            <div className="sticky top-28">
              
              <div className="flex items-center justify-between mb-8">
                <h3 className="text-lg font-medium text-text-primary">Payment Methods</h3>
                <button className="text-sm text-gold hover:text-text-primary transition-colors flex items-center gap-1">
                  <Plus className="w-4 h-4" /> Add New
                </button>
              </div>

              {/* Wallet Stack */}
              <div 
                className="relative h-[320px] w-full max-w-sm"
                onMouseEnter={() => setIsHoveringStack(true)}
                onMouseLeave={() => setIsHoveringStack(false)}
              >
                {personalMethods.map((method, i) => {
                  const isActive = activeCard === method.id;
                  
                  // Stacking logic
                  const zIndex = isActive ? 50 : i;
                  
                  // Smooth fanning
                  const y = isHoveringStack 
                    ? i * 75  // spread out evenly when hovered
                    : (isActive ? 0 : 30 + i * 12); // active at 0, others peeking out

                  const scale = isActive ? 1 : (isHoveringStack ? 0.95 : 0.9 - i * 0.02);
                  const opacity = isActive ? 1 : (isHoveringStack ? 0.9 : 0.4 + i * 0.1);

                  return (
                    <motion.div
                      key={method.id}
                      onClick={() => setActiveCard(method.id)}
                      animate={{ y, scale, zIndex, opacity }}
                      transition={{ 
                        type: "spring", 
                        stiffness: 300, 
                        damping: 30,
                        mass: 0.8
                      }}
                      className={`absolute top-0 left-0 w-full h-[180px] rounded-2xl border p-6 cursor-pointer backdrop-blur-2xl bg-gradient-to-br ${method.gradient} ${method.border} shadow-2xl flex flex-col justify-between overflow-hidden group`}
                    >
                      {/* Ripple effect on active */}
                      {isActive && (
                         <motion.div
                           initial={{ scale: 0, opacity: 0.4 }}
                           animate={{ scale: 2, opacity: 0 }}
                           transition={{ duration: 1.5, ease: "easeOut", repeat: Infinity, repeatDelay: 2.5 }}
                           className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-32 h-32 bg-white/10 rounded-full pointer-events-none"
                         />
                      )}

                      <div className="relative z-10 flex justify-between items-start">
                        <div>
                          <h3 className="text-text-primary/90 font-medium tracking-widest">{method.name}</h3>
                        </div>
                        <div className="w-10 h-10 rounded-full bg-card border border-border-light flex items-center justify-center">
                          <method.icon className="w-5 h-5 text-text-primary/70" />
                        </div>
                      </div>
                      
                      <div className="relative z-10 flex justify-between items-end">
                        <p className="text-text-primary/70 font-mono tracking-widest text-lg">{method.number}</p>
                        {isActive && (
                          <div className="w-2 h-2 rounded-full bg-emerald-400 shadow-[0_0_8px_rgba(52,211,153,0.8)]" />
                        )}
                      </div>
                    </motion.div>
                  );
                })}
              </div>

            </div>
          </div>

          {/* Right Column: Purchase Timeline */}
          <div className="lg:col-span-7">
            
            <h3 className="text-lg font-medium text-text-primary mb-8">Purchase History</h3>

            <div className="relative">
              {/* Timeline line */}
              <div className="absolute left-[7px] top-4 bottom-4 w-px bg-white/10" />

              <div className="space-y-8">
                {purchaseHistory.map((item, index) => {
                  const isCompleted = item.status === 'completed';
                  const isRefund = item.status === 'refunded';

                  return (
                    <motion.div
                      key={item.id}
                      initial={{ opacity: 0, x: 20 }}
                      animate={{ opacity: 1, x: 0 }}
                      transition={{ duration: 0.4, delay: index * 0.1 }}
                      className="relative flex items-start gap-8 group"
                    >
                      {/* Timeline dot */}
                      <div className={`absolute left-0 top-1.5 w-4 h-4 rounded-full border-2 border-[#0A0A0A] ${
                        isCompleted ? 'bg-emerald-500' : 'bg-red-500'
                      } shadow-sm z-10`} />

                      <div className="flex-1 border-b border-white/5 pb-8 group-last:border-0 group-last:pb-0">
                        <div className="flex justify-between items-start mb-2">
                          <div>
                            <span className="text-text-secondary text-sm mb-1 block">{item.time}</span>
                            <h4 className="text-text-primary font-medium text-lg">{item.service}</h4>
                          </div>
                          <div className="text-right">
                            <span className="text-text-primary font-medium text-lg block">
                              {formatCurrency(item.amount)}
                            </span>
                            <span className={`text-xs font-medium uppercase tracking-wider ${isCompleted ? 'text-emerald-400' : 'text-red-400'}`}>
                              {item.status}
                            </span>
                          </div>
                        </div>
                        
                        <div className="mt-4">
                          <button className="flex items-center gap-1.5 text-sm text-gold hover:text-text-primary transition-colors group/btn">
                            Receipt 
                            <ArrowRight className="w-3.5 h-3.5 transition-transform group-hover/btn:translate-x-1" />
                          </button>
                        </div>
                      </div>
                    </motion.div>
                  );
                })}
              </div>
            </div>
            
          </div>
        </div>

      </div>
    </ClientLayout>
  );
}
