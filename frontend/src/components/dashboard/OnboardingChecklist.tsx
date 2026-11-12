'use client';

import { useState, useEffect, useMemo } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { X, CheckCircle2, Circle, ArrowRight, Scissors, Users, Calendar, CreditCard, Wallet, AlertCircle } from 'lucide-react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { apiClient } from '@/lib/api-client';
import { useCategory } from '@/contexts/CategoryContext';

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

interface OnboardingChecklistProps {
  variant?: 'full' | 'banner';
}

export default function OnboardingChecklist({ variant = 'full' }: OnboardingChecklistProps) {
  const [checklist, setChecklist] = useState<ChecklistItem[]>([]);
  const [isVisible, setIsVisible] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const router = useRouter();
  const { terminology } = useCategory();

  const defaultChecklist = useMemo<ChecklistItem[]>(() => [
    {
      id: 'salon',
      label: `${terminology.business} Created`,
      description: `Your ${terminology.business.toLowerCase()} profile is set up`,
      completed: false,
      action: '/settings',
      icon: 'Scissors',
    },
    {
      id: 'team',
      label: 'Team Added',
      description: `Add your ${terminology.specialistPlural.toLowerCase()}`,
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
      label: `Create First ${terminology.appointment}`,
      description: `Book your first ${terminology.appointment.toLowerCase()}`,
      completed: false,
      action: '/bookings',
      icon: 'CreditCard',
    },
  ], [terminology]);

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

  if (variant === 'banner') {
    return (
      <div className="w-full mb-6 bg-amber-500/10 border border-amber-500/30 rounded-2xl p-4 flex items-center justify-between shadow-sm">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-full bg-amber-500/20 flex items-center justify-center shrink-0">
            <AlertCircle className="w-5 h-5 text-amber-500" />
          </div>
          <div>
            <h4 className="text-sm font-bold text-text-primary">Incomplete Setup Actions</h4>
            <p className="text-xs text-text-secondary">You have {checklist.length - completedCount} tasks left to complete your {terminology.business.toLowerCase()} setup.</p>
          </div>
        </div>
        <div className="flex items-center gap-3 shrink-0">
          <Link href="/settings" className="px-4 py-2 bg-amber-500 text-black text-sm font-semibold rounded-lg hover:bg-amber-400 transition-colors">
            Complete Setup
          </Link>
          <button onClick={handleDismiss} className="p-2 text-text-secondary hover:text-white transition-colors">
            <X className="w-4 h-4" />
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="w-full mb-6">
      <div className="bg-card border border-border-light rounded-3xl p-6 shadow-xl relative overflow-hidden">
        {/* Background glow */}
        <div className="absolute top-0 right-0 w-64 h-64 bg-gold/10 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2 pointer-events-none" />

        <div className="flex items-center justify-between mb-6 relative z-10">
          <div>
            <h3 className="text-lg font-bold text-text-primary">Getting Started</h3>
            <p className="text-sm text-text-secondary mt-1">Set up your {terminology.business.toLowerCase()}</p>
          </div>
          
          <div className="flex flex-col items-end">
            <div className="text-sm font-medium text-text-secondary mb-2">
              <span className="text-gold mr-1">{Math.round(progress)}%</span>
              <span>Complete</span>
            </div>
            <div className="w-32 h-1.5 bg-surface rounded-full overflow-hidden border border-border-light">
              <motion.div
                initial={{ width: 0 }}
                animate={{ width: `${progress}%` }}
                transition={{ duration: 0.5, delay: 0.2 }}
                className="h-full bg-gradient-to-r from-gold to-amber-400"
              />
            </div>
          </div>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-3 relative z-10">
          {checklist.map((item, index) => {
            const Icon = iconMap[item.icon] || Scissors;
            return (
              <button
                key={item.id}
                onClick={() => handleItemClick(item)}
                disabled={item.completed}
                className={`flex flex-col items-start p-4 rounded-2xl transition-all text-left border ${
                  item.completed
                    ? 'bg-surface/50 border-border-light opacity-60 cursor-default'
                    : 'bg-surface border-border-light hover:border-gold/30 hover:bg-white/[0.02] cursor-pointer group'
                }`}
              >
                <div className={`w-8 h-8 rounded-xl flex items-center justify-center mb-3 transition-colors ${
                  item.completed 
                    ? 'bg-green-500/20 text-green-400' 
                    : 'bg-white/[0.04] text-text-secondary group-hover:text-gold group-hover:bg-gold/10'
                }`}>
                  {item.completed ? <CheckCircle2 className="w-4 h-4" /> : <Icon className="w-4 h-4" />}
                </div>
                
                <h4 className={`text-sm font-medium mb-1 ${item.completed ? 'text-text-secondary line-through' : 'text-text-primary'}`}>
                  {item.label}
                </h4>
                
                {!item.completed && (
                  <p className="text-xs text-text-secondary line-clamp-2 mt-auto">
                    {item.description}
                  </p>
                )}
              </button>
            );
          })}
        </div>
      </div>
    </div>
  );
}
