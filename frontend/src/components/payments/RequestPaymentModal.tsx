'use client';

import { useState, useEffect } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { motion, AnimatePresence } from 'framer-motion';
import {
  X, Banknote, Smartphone, CreditCard, Wifi,
  CheckCircle, Clock, AlertCircle, Loader2, ArrowLeft
} from 'lucide-react';
import { apiClient } from '@/lib/api-client';

// ─── Types ────────────────────────────────────────────────────────────────────
type ChannelId = 'flutterwave' | 'visa' | 'mtn' | 'airtel' | 'cash';
type Step = 'details' | 'waiting' | 'success';

interface RequestPaymentModalProps {
  isOpen: boolean;
  onClose: () => void;
  preselectedMethod?: string;
  bookingId?: string;
  customerId?: string;
  customerName?: string;
  serviceName?: string;
  amount?: number;
  onSuccess?: (transaction: any) => void;
}

// ─── Channel config ───────────────────────────────────────────────────────────
const channelConfig: Record<string, {
  label: string;
  Icon: React.ElementType;
  accentClass: string;
  gradientClass: string;
  borderClass: string;
}> = {
  mtn: {
    label: 'MTN Mobile Money',
    Icon: Smartphone,
    accentClass: 'text-gold',
    gradientClass: 'from-[#C9A227]/20 to-[#FFD700]/5',
    borderClass: 'border-[#FFD700]/20',
  },
  airtel: {
    label: 'Airtel Money',
    Icon: Smartphone,
    accentClass: 'text-red-400',
    gradientClass: 'from-red-900/40 to-red-900/10',
    borderClass: 'border-red-500/20',
  },
  cash: {
    label: 'Cash',
    Icon: Banknote,
    accentClass: 'text-emerald-400',
    gradientClass: 'from-emerald-900/40 to-emerald-900/10',
    borderClass: 'border-emerald-500/20',
  },
  visa: {
    label: 'Visa / Card',
    Icon: CreditCard,
    accentClass: 'text-blue-400',
    gradientClass: 'from-blue-900 to-indigo-900',
    borderClass: 'border-blue-500/20',
  },
  flutterwave: {
    label: 'Flutterwave',
    Icon: Wifi,
    accentClass: 'text-[#A29BFE]',
    gradientClass: 'from-[#6C5CE7] to-[#A29BFE]',
    borderClass: 'border-[#6C5CE7]/30',
  },
};

const formatCurrency = (n: number) =>
  new Intl.NumberFormat('en-UG', { style: 'currency', currency: 'UGX', minimumFractionDigits: 0 }).format(n);

// ─── Component ────────────────────────────────────────────────────────────────
export default function RequestPaymentModal({
  isOpen,
  onClose,
  preselectedMethod,
  bookingId,
  customerId,
  customerName,
  serviceName,
  amount = 0,
  onSuccess,
}: RequestPaymentModalProps) {
  const [step, setStep] = useState<Step>('details');
  const [phone, setPhone] = useState('');
  const [email, setEmail] = useState('');
  const [error, setError] = useState('');
  const [timelineStep, setTimelineStep] = useState(0);
  const queryClient = useQueryClient();

  const method = preselectedMethod ?? 'cash';
  const channel = channelConfig[method] || channelConfig['cash'];
  const { Icon } = channel;

  // Reset every time the modal opens fresh
  useEffect(() => {
    if (isOpen) {
      setStep('details');
      setPhone('');
      setEmail('');
      setError('');
      setTimelineStep(0);
    }
  }, [isOpen]);

  const handleClose = () => {
    onClose();
  };

  // Manual Transaction Mutation (Cash/Card)
  const manualMutation = useMutation({
    mutationFn: (data: any) => apiClient.recordManualTransaction(data),
    onSuccess: (data) => {
      queryClient.invalidateQueries({ queryKey: ['transactions'] });
      queryClient.invalidateQueries({ queryKey: ['transaction-summary'] });
      setStep('success');
      onSuccess?.(data);
    },
    onError: () => {
      setError('Failed to record transaction. Please try again.');
    }
  });

  // Digital Payment Request Mutation (MTN/Airtel/Flutterwave)
  const requestMutation = useMutation({
    mutationFn: (data: any) => apiClient.requestPayment(data),
    onSuccess: (data) => {
      // In a real app we'd poll or wait for webhook here
      setStep('waiting');
      setTimelineStep(0);
      
      // We will simulate the timeline steps in a useEffect
      // so the UI can visibly progress through the steps.
    },
    onError: () => {
      setError('Failed to initiate payment request. Please try again.');
    }
  });

  const isLoading = manualMutation.isPending || requestMutation.isPending;

  // Timeline simulation effect
  useEffect(() => {
    if (step === 'waiting') {
      if (timelineStep < 4) {
        const timer = setTimeout(() => {
          setTimelineStep(prev => prev + 1);
        }, 1200);
        return () => clearTimeout(timer);
      } else if (timelineStep === 4) {
        // Timeline finished
        queryClient.invalidateQueries({ queryKey: ['transactions'] });
        queryClient.invalidateQueries({ queryKey: ['transaction-summary'] });
        setStep('success');
        if (requestMutation.data) {
          onSuccess?.(requestMutation.data);
        }
      }
    }
  }, [step, timelineStep, queryClient, onSuccess, requestMutation.data]);

  const handleSubmit = () => {
    setError('');

    // Validation for mobile channels
    if ((method === 'mtn' || method === 'airtel') && !phone.trim()) {
      setError("Please enter the customer's phone number.");
      return;
    }

    const payload = {
      booking_id: bookingId,
      customer_id: customerId,
      payment_method: method, // API expects this
      amount: amount,
      phone: phone || undefined,
      email: email || undefined,
      type: 'payment'
    };

    if (method === 'cash' || method === 'visa') {
      manualMutation.mutate(payload);
    } else {
      requestMutation.mutate(payload);
    }
  };

  // ─── Field config per method ───────────────────────────────────────────────
  const renderDetailsFields = () => {
    switch (method) {
      case 'mtn':
      case 'airtel':
        return (
          <div>
            <label className="text-sm font-medium text-text-secondary block mb-2">
              Customer Phone Number
            </label>
            <input
              type="tel"
              placeholder={method === 'mtn' ? '077XXXXXXX' : '075XXXXXXX'}
              value={phone}
              onChange={e => setPhone(e.target.value)}
              className="w-full px-4 py-3 bg-card border border-border-light rounded-2xl text-text-primary placeholder-[#A0A0A0] focus:outline-none focus:border-[rgba(108,92,231,0.5)] transition-colors font-mono tracking-wider"
            />
            <p className="text-text-secondary text-xs mt-2">
              A payment prompt will be sent to this number. The customer approves with their PIN.
            </p>
          </div>
        );

      case 'flutterwave':
        return (
          <div>
            <label className="text-sm font-medium text-text-secondary block mb-2">
              Customer Email <span className="text-text-primary/20 font-normal">(optional)</span>
            </label>
            <input
              type="email"
              placeholder="customer@email.com"
              value={email}
              onChange={e => setEmail(e.target.value)}
              className="w-full px-4 py-3 bg-card border border-border-light rounded-2xl text-text-primary placeholder-[#A0A0A0] focus:outline-none focus:border-[rgba(108,92,231,0.5)] transition-colors"
            />
            <p className="text-text-secondary text-xs mt-2">
              A secure payment link will be generated and optionally sent to the customer.
            </p>
          </div>
        );

      case 'cash':
        return (
          <div className="p-4 rounded-2xl bg-emerald-500/5 border border-emerald-500/20">
            <p className="text-emerald-400 text-sm leading-relaxed">
              Confirm that you have physically received the cash from the customer. This will mark the booking as paid and generate a receipt.
            </p>
          </div>
        );

      case 'visa':
        return (
          <div className="p-4 rounded-2xl bg-blue-500/5 border border-blue-500/20">
            <p className="text-blue-400 text-sm leading-relaxed">
              Confirm that the card terminal has shown a successful approval. This will mark the booking as paid and generate a receipt.
            </p>
          </div>
        );
      
      default:
        return null;
    }
  };

  // ─── CTA label ────────────────────────────────────────────────────────────
  const ctaLabel: Record<string, string> = {
    mtn:         'Send Payment Request',
    airtel:      'Send Payment Request',
    flutterwave: 'Send Payment Link',
    cash:        'Confirm Cash Received',
    visa:        'Confirm Card Payment',
  };

  // ─── Render ───────────────────────────────────────────────────────────────
  return (
    <AnimatePresence>
      {isOpen && (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          onClick={handleClose}
          className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-end sm:items-center justify-center p-0 sm:p-4"
        >
          <motion.div
            initial={{ opacity: 0, y: 60, scale: 0.97 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={{ opacity: 0, y: 60, scale: 0.97 }}
            transition={{ type: 'spring', stiffness: 400, damping: 35 }}
            onClick={e => e.stopPropagation()}
            className="w-full sm:max-w-md bg-[#111111] border border-border-light rounded-t-3xl sm:rounded-3xl overflow-hidden shadow-2xl"
          >
            {/* ── Header ── */}
            <div className={`flex items-center justify-between p-6 bg-gradient-to-br ${channel.gradientClass} border-b ${channel.borderClass}`}>
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-xl bg-white/10 border border-border-medium20 flex items-center justify-center">
                  <Icon className={`w-5 h-5 ${channel.accentClass}`} />
                </div>
                <div>
                  <h2 className="text-lg font-semibold text-text-primary">{channel.label}</h2>
                  {customerName && (
                    <p className="text-text-primary/60 text-sm">{customerName}{serviceName ? ` · ${serviceName}` : ''}</p>
                  )}
                </div>
              </div>
              <button
                onClick={handleClose}
                className="w-8 h-8 flex items-center justify-center rounded-full bg-black/20 hover:bg-black/40 text-text-primary/70 hover:text-text-primary transition-colors"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            {/* ── Body ── */}
            <div className="p-6 relative">
              <AnimatePresence mode="wait">
                
                {/* DETAILS STEP */}
                {step === 'details' && (
                  <motion.div
                    key="details"
                    initial={{ opacity: 0, x: -20 }}
                    animate={{ opacity: 1, x: 0 }}
                    exit={{ opacity: 0, x: 20 }}
                    transition={{ duration: 0.2 }}
                  >
                    <div className="flex items-center justify-between mb-8">
                      <span className="text-text-secondary">Amount Due</span>
                      <span className="text-2xl font-bold text-text-primary tracking-tight">
                        {formatCurrency(amount)}
                      </span>
                    </div>

                    <div className="mb-8">
                      {renderDetailsFields()}
                    </div>

                    {error && (
                      <div className="mb-6 p-3 rounded-xl bg-red-500/10 border border-red-500/20 flex items-center gap-3">
                        <AlertCircle className="w-4 h-4 text-red-400 flex-shrink-0" />
                        <p className="text-sm text-red-400">{error}</p>
                      </div>
                    )}

                    <button
                      onClick={handleSubmit}
                      disabled={isLoading}
                      className="w-full py-3.5 bg-white text-black hover:bg-gray-100 rounded-2xl font-semibold transition-colors disabled:opacity-70 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                    >
                      {isLoading ? (
                        <>
                          <Loader2 className="w-5 h-5 animate-spin" />
                          Processing...
                        </>
                      ) : (
                        ctaLabel[method] || 'Proceed'
                      )}
                    </button>
                  </motion.div>
                )}

                {/* WAITING STEP - TIMELINE */}
                {step === 'waiting' && (
                  <motion.div
                    key="waiting"
                    initial={{ opacity: 0, x: -20 }}
                    animate={{ opacity: 1, x: 0 }}
                    exit={{ opacity: 0, x: 20 }}
                    transition={{ duration: 0.2 }}
                    className="py-4"
                  >
                    <h3 className="text-xl font-bold text-text-primary mb-6 text-center">Awaiting Payment</h3>
                    
                    <div className="space-y-6 max-w-[280px] mx-auto relative">
                      {/* Connecting Line */}
                      <div className="absolute left-4 top-4 bottom-4 w-0.5 bg-white/10 z-0" />

                      {[
                        { title: 'Request Sent', desc: 'Prompt sent to customer' },
                        { title: 'Waiting for customer', desc: 'Customer is reviewing' },
                        { title: 'Customer approved', desc: 'PIN entered successfully' },
                        { title: 'Processing', desc: 'Confirming with provider' }
                      ].map((s, i) => {
                        const isPast = timelineStep > i;
                        const isCurrent = timelineStep === i;
                        const isFuture = timelineStep < i;

                        return (
                          <motion.div 
                            key={i} 
                            initial={{ opacity: 0, y: 10 }}
                            animate={{ opacity: isFuture ? 0.3 : 1, y: 0 }}
                            className="flex gap-4 relative z-10"
                          >
                            <div className={`w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 transition-colors duration-500 ${
                              isPast ? 'bg-emerald-500 text-text-primary' : 
                              isCurrent ? 'bg-white/10 border-2 ' + channel.borderClass + ' ' + channel.accentClass : 
                              'bg-card border-2 border-border-light text-[#555]'
                            }`}>
                              {isPast ? <CheckCircle className="w-4 h-4" /> : 
                               isCurrent ? <Loader2 className="w-4 h-4 animate-spin" /> : 
                               <div className="w-2 h-2 rounded-full bg-current" />}
                            </div>
                            <div className="pt-1">
                              <p className={`font-semibold text-sm transition-colors duration-500 ${isCurrent || isPast ? 'text-text-primary' : 'text-[#555]'}`}>
                                {s.title}
                              </p>
                              <p className={`text-xs transition-colors duration-500 ${isCurrent ? 'text-text-secondary' : 'text-transparent'}`}>
                                {isCurrent && s.desc}
                              </p>
                            </div>
                          </motion.div>
                        );
                      })}
                    </div>
                    
                    <button 
                      onClick={() => setStep('details')}
                      className="mt-8 mx-auto flex items-center justify-center gap-2 text-sm text-text-secondary hover:text-text-primary transition-colors"
                    >
                      <ArrowLeft className="w-4 h-4" /> Cancel Request
                    </button>
                  </motion.div>
                )}

                {/* SUCCESS STEP */}
                {step === 'success' && (
                  <motion.div
                    key="success"
                    initial={{ opacity: 0, scale: 0.95 }}
                    animate={{ opacity: 1, scale: 1 }}
                    className="py-10 text-center flex flex-col items-center"
                  >
                    <div className="w-20 h-20 bg-emerald-500/10 rounded-full flex items-center justify-center mb-6">
                      <CheckCircle className="w-10 h-10 text-emerald-500" />
                    </div>
                    <h3 className="text-2xl font-bold text-text-primary mb-2">Payment Successful</h3>
                    <p className="text-text-secondary mb-8">
                      {formatCurrency(amount)} has been paid via {channel.label}.
                    </p>
                    <button
                      onClick={handleClose}
                      className="w-full py-3.5 bg-white/10 hover:bg-white/20 text-text-primary rounded-2xl font-semibold transition-colors"
                    >
                      Close Window
                    </button>
                  </motion.div>
                )}
                
              </AnimatePresence>
            </div>
          </motion.div>
        </motion.div>
      )}
    </AnimatePresence>
  );
}
