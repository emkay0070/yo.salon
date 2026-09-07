'use client';

import { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { X, CheckCircle2, Circle, ArrowRight, Scissors, Users, Calendar, CreditCard, Wallet } from 'lucide-react';
import { useRouter } from 'next/navigation';
import { apiClient } from '@/lib/api-client';

interface ChecklistItem {
  id: string;
  label: string;
  description: string;
  completed: boolean;
  action: string;
  icon: string; // Store icon name as string
}

const iconMap: Record<string, any> = {
  Scissors,
  Users,
  Calendar,
  Wallet,
  CreditCard,
};

const defaultChecklist: ChecklistItem[] = [
  {
    id: 'salon',
    label: 'Salon Created',
    description: 'Your salon profile is set up',
    completed: false,
    action: '/settings',
    icon: 'Scissors',
  },
  {
    id: 'team',
    label: 'Team Added',
    description: 'Add your staff members',
    completed: false,
    action: '/staff',
    icon: 'Users',
  },
  {
    id: 'services',
    label: 'Services Added',
    description: 'Configure your service menu',
    completed: false,
    action: '/services',
    icon: 'Calendar',
  },
  {
    id: 'wallet',
    label: 'Configure Wallet',
    description: 'Set up payment methods',
    completed: false,
    action: '/settings/membership',
    icon: 'Wallet',
  },
  {
    id: 'booking',
    label: 'Create First Booking',
    description: 'Book your first appointment',
    completed: false,
    action: '/bookings',
    icon: 'CreditCard',
  },
];

export default function OnboardingChecklist() {
  const [checklist, setChecklist] = useState<ChecklistItem[]>(defaultChecklist);
  const [isVisible, setIsVisible] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const router = useRouter();

  // Fetch onboarding status from backend
  useEffect(() => {
    const fetchOnboardingStatus = async () => {
      try {
        const session = await apiClient.getOnboardingSession();
        
        // Map backend status to checklist completion
        const updatedChecklist = defaultChecklist.map(item => {
          let completed = false;
          
          if (session.completed_steps?.includes(item.id)) {
            completed = true;
          }
          
          // Check specific conditions
          if (item.id === 'salon' && session.salon_created) {
            completed = true;
          }
          if (item.id === 'team' && session.team_added) {
            completed = true;
          }
          if (item.id === 'services' && session.services_added) {
            completed = true;
          }
          if (item.id === 'wallet' && session.wallet_configured) {
            completed = true;
          }
          if (item.id === 'booking' && session.first_booking_created) {
            completed = true;
          }
          
          return { ...item, completed };
        });
        
        setChecklist(updatedChecklist);
        
        // Only show if not 100% complete and user hasn't dismissed it
        const dismissed = localStorage.getItem('onboarding_checklist_dismissed');
        const allComplete = updatedChecklist.every(item => item.completed);
        
        if (!dismissed && !allComplete) {
          setIsVisible(true);
        }
      } catch (error) {
        console.error('Failed to fetch onboarding status:', error);
        // Fallback to localStorage if backend fails
        const saved = localStorage.getItem('onboarding_checklist');
        if (saved) {
          setChecklist(JSON.parse(saved));
          const dismissed = localStorage.getItem('onboarding_checklist_dismissed');
          if (!dismissed) {
            setIsVisible(true);
          }
        }
      } finally {
        setIsLoading(false);
      }
    };

    fetchOnboardingStatus();
  }, []);

  const handleDismiss = () => {
    setIsVisible(false);
    localStorage.setItem('onboarding_checklist_dismissed', 'true');
  };

  const handleItemClick = (item: ChecklistItem) => {
    if (!item.completed) {
      router.push(item.action);
    }
  };

  const completedCount = checklist.filter((item) => item.completed).length;
  const progress = (completedCount / checklist.length) * 100;

  const [isExpanded, setIsExpanded] = useState(true);

  if (isLoading || !isVisible || progress === 100) {
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
            className="w-80 bg-[#111115]/95 backdrop-blur-xl border border-white/[0.08] rounded-2xl shadow-2xl mb-4 overflow-hidden pointer-events-auto"
          >
            {/* Header */}
            <div className="p-4 border-b border-white/[0.04] bg-white/[0.02] flex items-center justify-between">
              <div>
                <h3 className="text-sm font-semibold text-text-primary">Getting Started</h3>
                <p className="text-[10px] text-text-secondary mt-0.5">Set up your salon</p>
              </div>
              <button
                onClick={() => setIsExpanded(false)}
                className="p-1.5 rounded-lg text-text-secondary hover:text-text-primary hover:bg-white/[0.04] transition-colors"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            {/* Content */}
            <div className="p-4 max-h-[320px] overflow-y-auto scrollbar-hide">
              {/* Progress */}
              <div className="mb-5">
                <div className="flex items-center justify-between text-[10px] text-text-secondary mb-2 font-medium">
                  <span>{completedCount} of {checklist.length} completed</span>
                  <span className="text-[#FFD700]">{Math.round(progress)}%</span>
                </div>
                <div className="h-1 bg-white/[0.04] rounded-full overflow-hidden">
                  <motion.div
                    initial={{ width: 0 }}
                    animate={{ width: `${progress}%` }}
                    transition={{ duration: 0.5 }}
                    className="h-full bg-gradient-to-r from-[#FFD700] to-[#C9A227]"
                  />
                </div>
              </div>

              {/* Items */}
              <div className="space-y-1">
                {checklist.map((item) => {
                  const Icon = iconMap[item.icon] || Scissors;
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
                          : 'bg-white/[0.04] text-text-secondary group-hover:text-[#FFD700] border border-white/[0.04]'
                      }`}>
                        {item.completed ? <CheckCircle2 className="w-4 h-4" /> : <Icon className="w-4 h-4" />}
                      </div>
                      <div className="flex-1 min-w-0">
                        <h4 className={`text-xs font-medium truncate ${item.completed ? 'text-text-secondary line-through' : 'text-text-primary'}`}>
                          {item.label}
                        </h4>
                        {!item.completed && (
                          <p className="text-[10px] text-text-secondary truncate mt-0.5">{item.description}</p>
                        )}
                      </div>
                      {!item.completed && (
                        <ArrowRight className="w-3.5 h-3.5 text-text-secondary opacity-0 -translate-x-2 group-hover:opacity-100 group-hover:translate-x-0 transition-all" />
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
            className="w-12 h-12 bg-[#111115] border border-white/[0.08] hover:border-[#FFD700]/50 rounded-full shadow-2xl flex items-center justify-center text-text-primary hover:text-[#FFD700] transition-colors pointer-events-auto relative"
          >
            <div className="absolute inset-0 rounded-full border-[2px] border-[#FFD700]" style={{ clipPath: `inset(0 ${100 - progress}% 0 0)` }} />
            <Scissors className="w-5 h-5 relative z-10" />
            
            {/* Notification dot */}
            <span className="absolute top-0 right-0 w-3 h-3 bg-[#FFD700] rounded-full border-2 border-[#111115]" />
          </motion.button>
        )}
      </AnimatePresence>
    </div>
  );
}
