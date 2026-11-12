'use client';

import { useOnboarding } from '@/contexts/OnboardingContext';
import { useCategory } from '@/contexts/CategoryContext';
import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/lib/api-client';
import { motion } from 'framer-motion';
import { Check, Star, Loader2, Zap, Shield, Building2, Crown, Gift, HardDrive, MessageSquare } from 'lucide-react';
import SceneLayout from './SceneLayout';

const planIcons: Record<string, any> = {
  'free-trial': Gift,
  starter:      Zap,
  professional: Star,
  premium:      Crown,
  enterprise:   Building2,
};

const planColors: Record<string, string> = {
  'free-trial': 'from-emerald-400 to-teal-500',
  starter:      'from-slate-400 to-slate-500',
  professional: 'from-blue-400 to-indigo-500',
  premium:      'from-[#FFD700] to-[#C9A227]',
  enterprise:   'from-violet-400 to-purple-500',
};

function formatCurrency(amount: number, currency: string = 'UGX'): string {
  return new Intl.NumberFormat('en-UG', {
    style: 'currency',
    currency: currency,
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(amount);
}

export default function MembershipScene() {
  const { selectedPlanId, setSelectedPlanId, providerData } = useOnboarding();
  const { terminology } = useCategory();

  const { data: plansData, isLoading, error } = useQuery({
    queryKey: ['plans', providerData.providerType],
    queryFn: () => apiClient.getMembershipPlans(providerData.providerType || 'salon'),
    enabled: !!providerData.providerType,
  });

  console.log('Membership plans:', plansData, 'Error:', error);
  
  const plans = plansData?.plans || [];

  return (
    <SceneLayout
      nextLabel={selectedPlanId ? 'Start My Free Trial' : 'Choose a plan to continue'}
      nextDisabled={!selectedPlanId}
      hint="No credit card required. Cancel anytime."
    >
      <div className="text-center mb-8">
        <p className="text-[#FFD700]/70 text-xs font-semibold tracking-widest uppercase mb-3">Your {terminology.business.toLowerCase()} is ready</p>
        <h2 className="text-3xl md:text-4xl font-bold text-white mb-3">
          Choose the tools that help you grow.
        </h2>
        <p className="text-white/40 text-base">
          Start with a 14-day free trial. Pick the plan that fits your vision.
        </p>
      </div>

      {isLoading ? (
        <div className="flex items-center justify-center py-16">
          <Loader2 className="w-8 h-8 text-[#FFD700]/40 animate-spin" />
        </div>
      ) : error ? (
        <div className="text-center py-8 text-red-400">Failed to load plans</div>
      ) : plans.length === 0 ? (
        <div className="text-center py-8 text-white/50">No plans available</div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 pb-6">
          {plans.map((plan: any, i: number) => {
            const key = plan.slug || plan.name.toLowerCase();
            const Icon = planIcons[key] || Star;
            const gradient = planColors[key] || 'from-white/20 to-white/10';
            const isSelected = selectedPlanId === plan.id;
            const isPopular = key === 'professional' || key === 'premium';

            return (
              <motion.button
                key={plan.id}
                initial={{ opacity: 0, y: 16 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: i * 0.07 }}
                onClick={() => setSelectedPlanId(plan.id)}
                className={`relative text-left p-5 rounded-2xl border-2 transition-all duration-200 ${
                  isSelected
                    ? 'border-[#FFD700]/70 bg-[#FFD700]/[0.06] shadow-xl shadow-[#FFD700]/5'
                    : 'border-white/[0.07] bg-white/[0.02] hover:border-white/[0.14] hover:bg-white/[0.04]'
                }`}
              >
                {/* Popular badge */}
                {isPopular && !isSelected && (
                  <div className="absolute -top-2.5 left-4 bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black px-2.5 py-0.5 rounded-full text-[10px] font-bold flex items-center gap-1">
                    <Star className="w-2.5 h-2.5" /> Popular
                  </div>
                )}
                {isSelected && (
                  <motion.div
                    initial={{ scale: 0 }}
                    animate={{ scale: 1 }}
                    className="absolute top-4 right-4 w-6 h-6 rounded-full bg-[#FFD700] flex items-center justify-center"
                  >
                    <Check className="w-3.5 h-3.5 text-black" />
                  </motion.div>
                )}

                {/* Icon */}
                <div className={`w-9 h-9 rounded-xl bg-gradient-to-br ${gradient} flex items-center justify-center mb-3`}>
                  <Icon className="w-4 h-4 text-white" />
                </div>

                <h3 className="text-base font-bold text-white mb-0.5">{plan.name}</h3>
                <div className="flex items-baseline gap-1 mb-3">
                  <span className="text-2xl font-bold text-white">
                    {formatCurrency(plan.monthly_price || 0)}
                  </span>
                  <span className="text-white/30 text-xs">/month</span>
                </div>
                {plan.yearly_price && (
                  <p className="text-white/30 text-xs mb-3">{formatCurrency(plan.yearly_price)}/year billed annually</p>
                )}

                <div className="space-y-1.5">
                  <div className="flex items-center justify-between text-xs">
                    <span className="text-white/30">Staff</span>
                    <span className="text-white/60 font-medium">{plan.staff_limit === null || plan.staff_limit === -1 ? 'Unlimited' : plan.staff_limit}</span>
                  </div>
                  <div className="flex items-center justify-between text-xs">
                    <span className="text-white/30">Branches</span>
                    <span className="text-white/60 font-medium">{plan.branches_limit === null || plan.branches_limit === -1 ? 'Unlimited' : plan.branches_limit}</span>
                  </div>
                  {plan.storage_limit_gb !== undefined && plan.storage_limit_gb !== null && (
                    <div className="flex items-center justify-between text-xs">
                      <span className="text-white/30">Storage</span>
                      <span className="text-white/60 font-medium">{plan.storage_limit_gb === null || plan.storage_limit_gb === -1 ? 'Unlimited' : `${plan.storage_limit_gb} GB`}</span>
                    </div>
                  )}
                  {plan.support_level && (
                    <div className="flex items-center justify-between text-xs">
                      <span className="text-white/30">Support</span>
                      <span className="text-white/60 font-medium capitalize">{plan.support_level}</span>
                    </div>
                  )}
                  {Array.isArray(plan.features) && plan.features.map((f: string, fi: number) => (
                    <div key={fi} className="flex items-center gap-1.5 text-xs text-white/30">
                      <Check className="w-3 h-3 text-[#FFD700]/50 flex-shrink-0" />
                      {f}
                    </div>
                  ))}
                </div>
              </motion.button>
            );
          })}
        </div>
      )}
    </SceneLayout>
  );
}
