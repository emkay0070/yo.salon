'use client';

import { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { X, CheckCircle2, ArrowRight, Scissors, Users, Calendar, CreditCard, Wallet } from 'lucide-react';
import { useRouter } from 'next/navigation';
import { apiClient } from '@/lib/api-client';

interface ChecklistItem {
  id: string;
  label: string;
  description: string;
  completed: boolean;
  action: string;
  icon: any;
}

export default function OnboardingChecklist() {
  const [isVisible, setIsVisible] = useState(true);
  const [loading, setLoading] = useState(true);
  const [checklist, setChecklist] = useState<ChecklistItem[]>([
    { id: 'salon', label: 'Salon Created', description: 'Your salon profile is set up', completed: true, action: '/settings', icon: Scissors },
    { id: 'team', label: 'Team Added', description: 'Add your staff members', completed: false, action: '/staff', icon: Users },
    { id: 'services', label: 'Services Added', description: 'Configure your service menu', completed: false, action: '/services', icon: Calendar },
    { id: 'wallet', label: 'Configure Wallet', description: 'Set up payment methods', completed: false, action: '/settings/membership', icon: Wallet },
    { id: 'booking', label: 'Create First Booking', description: 'Book your first appointment', completed: false, action: '/bookings', icon: CreditCard },
  ]);
  const router = useRouter();

  useEffect(() => {
    async function fetchChecklistData() {
      try {
        setLoading(true);
        const [staff, services, bookings, paymentMethods] = await Promise.all([
          apiClient.getStaff().catch(() => []),
          apiClient.getServices().catch(() => []),
          apiClient.getBookings().catch(() => []),
          apiClient.getPaymentMethods().catch(() => []),
        ]);

        setChecklist([
          { id: 'salon', label: 'Salon Created', description: 'Your salon profile is set up', completed: true, action: '/settings', icon: Scissors },
          { id: 'team', label: 'Team Added', description: 'Add your staff members', completed: Array.isArray(staff) ? staff.length > 0 : false, action: '/staff', icon: Users },
          { id: 'services', label: 'Services Added', description: 'Configure your service menu', completed: Array.isArray(services) ? services.length > 0 : false, action: '/services', icon: Calendar },
          { id: 'wallet', label: 'Configure Wallet', description: 'Set up payment methods', completed: Array.isArray(paymentMethods) ? paymentMethods.length > 0 : false, action: '/settings/membership', icon: Wallet },
          { id: 'booking', label: 'Create First Booking', description: 'Book your first appointment', completed: Array.isArray(bookings) ? bookings.length > 0 : false, action: '/bookings', icon: CreditCard },
        ]);
      } catch (error) {
        console.error('Failed to fetch checklist data:', error);
      } finally {
        setLoading(false);
      }
    }

    fetchChecklistData();
  }, []);

  const handleItemClick = (item: ChecklistItem) => {
    if (!item.completed) {
      router.push(item.action);
    }
  };

  const completedCount = checklist.filter((item) => item.completed).length;
  const progress = (completedCount / checklist.length) * 100;
  const [isExpanded, setIsExpanded] = useState(true);

  if (!isVisible || progress === 100 || loading) {
    return null;
  }

  return (
    <div className="fixed bottom-6 right-6 z-50 flex flex-col items-end pointer-events-none">
      <AnimatePresence>
        {isExpanded && (
          <motion.div
            initial={{ opacity: 0, y: 20, scale: 0.95 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={{ opacity: 0, y: 20, scale: 0.95 }}
            transition={{ type: "spring", damping: 25, stiffness: 200 }}
            className="w-80 bg-[var(--color-surface)]/95 backdrop-blur-xl border border-[var(--color-border-light)] rounded-2xl shadow-2xl mb-4 overflow-hidden pointer-events-auto"
          >
            {/* Header */}
            <div className="p-4 border-b border-[var(--color-border-light)] bg-[var(--color-card)] flex items-center justify-between">
              <div>
                <h3 className="text-sm font-semibold text-[var(--color-text-primary)]">Getting Started</h3>
                <p className="text-[10px] text-[var(--color-text-secondary)] mt-0.5">Set up your salon</p>
              </div>
              <button
                onClick={() => setIsExpanded(false)}
                className="p-1.5 rounded-lg text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)] hover:bg-white/[0.04] transition-colors"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            {/* Content */}
            <div className="p-4 max-h-[320px] overflow-y-auto scrollbar-hide">
              {/* Progress */}
              <div className="mb-5">
                <div className="flex items-center justify-between text-[10px] text-[var(--color-text-secondary)] mb-2 font-medium">
                  <span>{completedCount} of {checklist.length} completed</span>
                  <span className="text-[var(--color-gold)]">{Math.round(progress)}%</span>
                </div>
                <div className="h-1 bg-white/[0.04] rounded-full overflow-hidden">
                  <motion.div
                    initial={{ width: 0 }}
                    animate={{ width: `${progress}%` }}
                    transition={{ duration: 0.5 }}
                    className="h-full bg-gradient-to-r from-[var(--color-gold)] to-[var(--color-dark-gold)]"
                  />
                </div>
              </div>

              {/* Items */}
              <div className="space-y-1">
                {checklist.map((item) => {
                  const Icon = item.icon;
                  return (
                    <button
                      key={item.id}
                      onClick={() => handleItemClick(item)}
                      disabled={item.completed}
                      className={`w-full flex items-center gap-3 p-2.5 rounded-xl transition-all text-left ${
                        item.completed
                          ? 'opacity-50 cursor-not-allowed'
                          : 'hover:bg-white/[0.04] cursor-pointer group'
                      }`}
                    >
                      <div className={`w-8 h-8 rounded-lg flex items-center justify-center shrink-0 ${
                        item.completed 
                          ? 'bg-emerald-500/20 text-emerald-400' 
                          : 'bg-white/[0.04] text-[var(--color-text-secondary)] group-hover:text-[var(--color-gold)] border border-white/[0.04]'
                      }`}>
                        {item.completed ? <CheckCircle2 className="w-4 h-4" /> : <Icon className="w-4 h-4" />}
                      </div>
                      <div className="flex-1 min-w-0">
                        <h4 className={`text-xs font-medium truncate ${item.completed ? 'text-[var(--color-text-secondary)] line-through' : 'text-[var(--color-text-primary)]'}`}>
                          {item.label}
                        </h4>
                        {!item.completed && (
                          <p className="text-[10px] text-[var(--color-text-secondary)] truncate mt-0.5">{item.description}</p>
                        )}
                      </div>
                      {!item.completed && (
                        <ArrowRight className="w-3.5 h-3.5 text-[var(--color-text-secondary)] opacity-0 -translate-x-2 group-hover:opacity-100 group-hover:translate-x-0 transition-all" />
                      )}
                    </button>
                  );
                })}
              </div>
            </div>
          </motion.div>
        )}
      </AnimatePresence>

      {/* Floating Action Button (when collapsed) */}
      <AnimatePresence>
        {!isExpanded && isVisible && (
          <motion.button
            initial={{ opacity: 0, scale: 0.8 }}
            animate={{ opacity: 1, scale: 1 }}
            exit={{ opacity: 0, scale: 0.8 }}
            onClick={() => setIsExpanded(true)}
            className="w-12 h-12 bg-[var(--color-surface)] border border-[var(--color-border-light)] hover:border-[var(--color-gold)]/50 rounded-full shadow-2xl flex items-center justify-center text-[var(--color-text-primary)] hover:text-[var(--color-gold)] transition-colors pointer-events-auto relative"
          >
            <div className="absolute inset-0 rounded-full border-[2px] border-[var(--color-gold)]" style={{ clipPath: `inset(0 ${100 - progress}% 0 0)` }} />
            <Scissors className="w-5 h-5 relative z-10" />
            
            {/* Notification dot */}
            <span className="absolute top-0 right-0 w-3 h-3 bg-[var(--color-gold)] rounded-full border-2 border-[var(--color-surface)]" />
          </motion.button>
        )}
      </AnimatePresence>
    </div>
  );
}
