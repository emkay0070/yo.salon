'use client';

import { useEffect, useState } from 'react';
import {
  Crown, CheckCircle2, AlertTriangle, TrendingUp,
  Calendar, Download, ChevronRight, Sparkles, Shield,
  Zap, Users, Building2, HardDrive, MessageSquare,
  ArrowUpRight, ArrowDownRight, Clock, CreditCard
} from 'lucide-react';
import { apiClient } from '@/lib/api-client';
import { motion, AnimatePresence } from 'framer-motion';
import DashboardLayout from '@/components/DashboardLayout';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useRole } from '@/contexts/RoleContext';

// ─── Types ────────────────────────────────────────────────────────────────────

interface Plan {
  id: string;
  name: string;
  slug: string;
  description: string;
  monthly_price: number;
  yearly_price: number;
  features: string[];
  staff_limit: number;
  branches_limit: number;
  storage_limit_gb: number;
  support_level: string;
}

interface Subscription {
  id: string;
  salon_id: string;
  plan_id: string;
  status: string;
  billing_cycle: string;
  trial_ends_at: string | null;
  starts_at: string;
  ends_at: string | null;
  renews_at: string | null;
  cancelled_at: string | null;
  plan: Plan;
}

interface UsageMetric {
  metric: string;
  current_value: number;
  limit: number;
  remaining: number;
  percentage: number;
  is_near_limit: boolean;
  is_over_limit: boolean;
}

interface Invoice {
  id: string;
  invoice_number: string;
  status: string;
  currency: string;
  total: number;
  due_date: string | null;
  paid_at: string | null;
  created_at: string;
}

interface TimelineEvent {
  id: string;
  type: string;
  description: string;
  created_at: string;
  payload: any;
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function formatCurrency(amount: number, currency: string = 'UGX'): string {
  return new Intl.NumberFormat('en-UG', {
    style: 'currency',
    currency: currency,
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(amount);
}

function formatDate(dateString: string): string {
  return new Date(dateString).toLocaleDateString('en-UG', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
}

function getDaysUntil(dateString: string): number {
  const date = new Date(dateString);
  const now = new Date();
  const diff = date.getTime() - now.getTime();
  return Math.ceil(diff / (1000 * 60 * 60 * 24));
}

// ─── Status Badge ─────────────────────────────────────────────────────────────

function StatusBadge({ status }: { status: string }) {
  const config = {
    active: { color: 'bg-emerald-500', text: 'text-emerald-400', bg: 'bg-emerald-500/10', border: 'border-emerald-500/25', label: 'Active' },
    trialing: { color: 'bg-blue-500', text: 'text-blue-400', bg: 'bg-blue-500/10', border: 'border-blue-500/25', label: 'Trial' },
    past_due: { color: 'bg-orange-500', text: 'text-orange-400', bg: 'bg-orange-500/10', border: 'border-orange-500/25', label: 'Past Due' },
    cancelled: { color: 'bg-red-500', text: 'text-red-400', bg: 'bg-red-500/10', border: 'border-red-500/25', label: 'Cancelled' },
    expired: { color: 'bg-zinc-500', text: 'text-zinc-400', bg: 'bg-zinc-500/10', border: 'border-zinc-500/25', label: 'Expired' },
  };

  const cfg = config[status as keyof typeof config] || config.active;

  return (
    <div className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold ${cfg.bg} ${cfg.text} border ${cfg.border}`}>
      <div className={`w-1.5 h-1.5 rounded-full ${cfg.color}`} />
      {cfg.label}
    </div>
  );
}

// ─── Usage Card ───────────────────────────────────────────────────────────────

function UsageCard({ metric }: { metric: UsageMetric }) {
  const icons: Record<string, any> = {
    staff: Users,
    branches: Building2,
    storage: HardDrive,
    sms_credits: MessageSquare,
    bookings: Calendar,
  };

  const labels: Record<string, string> = {
    staff: 'Staff',
    branches: 'Branches',
    storage: 'Storage',
    sms_credits: 'SMS Credits',
    bookings: 'Bookings',
  };

  const Icon = icons[metric.metric] || Zap;
  const label = labels[metric.metric] || metric.metric;

  const isWarning = metric.is_near_limit && !metric.is_over_limit;
  const isCritical = metric.is_over_limit;

  return (
    <div className={`bg-card border rounded-xl p-4 ${
      isCritical ? 'border-red-500/50' : isWarning ? 'border-amber-500/50' : 'border-border-medium'
    }`}>
      <div className="flex items-center justify-between mb-3">
        <div className="flex items-center gap-2">
          <Icon className={`w-4 h-4 ${isCritical ? 'text-red-400' : isWarning ? 'text-amber-400' : 'text-text-secondary'}`} />
          <span className="text-text-secondary text-sm font-medium">{label}</span>
        </div>
        {isCritical && (
          <AlertTriangle className="w-4 h-4 text-red-400" />
        )}
      </div>

      <div className="flex items-end justify-between mb-2">
        <div>
          <span className={`text-2xl font-bold ${isCritical ? 'text-red-400' : isWarning ? 'text-amber-400' : 'text-text-primary'}`}>
            {metric.current_value}
          </span>
          <span className="text-text-secondary text-sm ml-1">/ {metric.limit}</span>
        </div>
        <span className={`text-xs font-medium ${isCritical ? 'text-red-400' : isWarning ? 'text-amber-400' : 'text-text-secondary'}`}>
          {metric.remaining} remaining
        </span>
      </div>

      <div className="h-2 bg-surface rounded-full overflow-hidden">
        <div
          className={`h-full rounded-full transition-all ${
            isCritical ? 'bg-red-500' : isWarning ? 'bg-amber-500' : 'bg-gradient-to-r from-[#FFD700] to-[#C9A227]'
          }`}
          style={{ width: `${Math.min(metric.percentage, 100)}%` }}
        />
      </div>
    </div>
  );
}

// ─── Feature List ─────────────────────────────────────────────────────────────

function FeatureList({ features }: { features: string[] | string | null | undefined }) {
  const featuresArray = Array.isArray(features) ? features : [];
  return (
    <div className="space-y-2">
      {featuresArray.map((feature, i) => (
        <div key={i} className="flex items-start gap-3">
          <CheckCircle2 className="w-5 h-5 text-[#FFD700] flex-shrink-0 mt-0.5" />
          <span className="text-text-primary text-sm">{feature}</span>
        </div>
      ))}
    </div>
  );
}

// ─── Invoice Row ───────────────────────────────────────────────────────────────

function InvoiceRow({ invoice }: { invoice: Invoice }) {
  const statusConfig = {
    paid: { color: 'text-emerald-400', bg: 'bg-emerald-500/10', label: 'Paid' },
    pending: { color: 'text-amber-400', bg: 'bg-amber-500/10', label: 'Pending' },
    failed: { color: 'text-red-400', bg: 'bg-red-500/10', label: 'Failed' },
    cancelled: { color: 'text-zinc-400', bg: 'bg-zinc-500/10', label: 'Cancelled' },
  };

  const cfg = statusConfig[invoice.status as keyof typeof statusConfig] || statusConfig.pending;

  return (
    <div className="bg-card border border-border-medium rounded-xl p-4 flex items-center justify-between hover:border-border-light transition-colors">
      <div className="flex items-center gap-4">
        <div className={`w-10 h-10 rounded-lg ${cfg.bg} flex items-center justify-center`}>
          <CreditCard className={`w-5 h-5 ${cfg.color}`} />
        </div>
        <div>
          <p className="text-text-primary font-medium">{invoice.invoice_number}</p>
          <p className="text-text-secondary text-xs">{formatDate(invoice.created_at)}</p>
        </div>
      </div>

      <div className="flex items-center gap-6">
        <div className="text-right">
          <p className="text-text-primary font-semibold">{formatCurrency(invoice.total, invoice.currency)}</p>
          <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium ${cfg.bg} ${cfg.color}`}>
            {cfg.label}
          </span>
        </div>
        <button className="p-2 hover:bg-surface rounded-lg transition-colors text-text-secondary hover:text-text-primary">
          <Download className="w-4 h-4" />
        </button>
      </div>
    </div>
  );
}

// ─── Timeline Event ───────────────────────────────────────────────────────────

function TimelineEvent({ event }: { event: TimelineEvent }) {
  const icons: Record<string, any> = {
    subscription_created: Crown,
    subscription_updated: Sparkles,
    subscription_cancelled: AlertTriangle,
    payment_succeeded: CheckCircle2,
    payment_failed: AlertTriangle,
    invoice_created: CreditCard,
    trial_started: Zap,
    trial_ended: Clock,
  };

  const Icon = icons[event.type] || Sparkles;

  return (
    <div className="flex gap-4">
      <div className="flex flex-col items-center">
        <div className="w-8 h-8 rounded-full bg-[#FFD700]/10 border border-[#FFD700]/25 flex items-center justify-center">
          <Icon className="w-4 h-4 text-[#FFD700]" />
        </div>
        <div className="w-px h-full bg-border-light mt-2" />
      </div>
      <div className="flex-1 pb-6">
        <p className="text-text-primary text-sm font-medium mb-1">{event.description}</p>
        <p className="text-text-secondary text-xs">{formatDate(event.created_at)}</p>
      </div>
    </div>
  );
}

// ─── Plan Comparison Card ─────────────────────────────────────────────────────

function PlanCard({ plan, isCurrent, onSelect }: { plan: Plan; isCurrent: boolean; onSelect: () => void }) {
  return (
    <motion.div
      whileHover={{ y: -4 }}
      className={`relative bg-card border rounded-2xl p-6 ${
        isCurrent ? 'border-[#FFD700] shadow-lg shadow-[#FFD700]/10' : 'border-border-medium'
      }`}
    >
      {isCurrent && (
        <div className="absolute -top-3 left-1/2 -translate-x-1/2 bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black px-3 py-1 rounded-full text-xs font-bold">
          Current Plan
        </div>
      )}

      <div className="text-center mb-6">
        <h3 className="text-text-primary text-xl font-bold mb-2">{plan.name}</h3>
        <div className="flex items-baseline justify-center gap-1">
          <span className="text-3xl font-bold text-text-primary">{formatCurrency(plan.monthly_price)}</span>
          <span className="text-text-secondary text-sm">/month</span>
        </div>
        <p className="text-text-secondary text-xs mt-1">{formatCurrency(plan.yearly_price)}/year billed annually</p>
      </div>

      <div className="space-y-3 mb-6">
        <div className="flex items-center justify-between text-sm">
          <span className="text-text-secondary">Staff</span>
          <span className="text-text-primary font-medium">{plan.staff_limit}</span>
        </div>
        <div className="flex items-center justify-between text-sm">
          <span className="text-text-secondary">Branches</span>
          <span className="text-text-primary font-medium">{plan.branches_limit}</span>
        </div>
        <div className="flex items-center justify-between text-sm">
          <span className="text-text-secondary">Storage</span>
          <span className="text-text-primary font-medium">{plan.storage_limit_gb} GB</span>
        </div>
        <div className="flex items-center justify-between text-sm">
          <span className="text-text-secondary">Support</span>
          <span className="text-text-primary font-medium capitalize">{plan.support_level}</span>
        </div>
      </div>

      {plan.features && plan.features.length > 0 && (
        <div className="mb-6">
          <FeatureList features={plan.features} />
        </div>
      )}

      <button
        onClick={onSelect}
        disabled={isCurrent}
        className={`w-full py-3 rounded-xl font-semibold transition-all ${
          isCurrent
            ? 'bg-surface text-text-secondary cursor-not-allowed border border-border-medium'
            : 'bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black hover:opacity-90'
        }`}
      >
        {isCurrent ? 'Current Plan' : 'Select Plan'}
      </button>
    </motion.div>
  );
}

// ─── Main Page ─────────────────────────────────────────────────────────────────

export default function MembershipPage() {
  const { salonId } = useRole();
  const queryClient = useQueryClient();
  const [showUpgradeModal, setShowUpgradeModal] = useState(false);

  // Fetch subscription data
  const { data: subscriptionData, isLoading: subscriptionLoading } = useQuery({
    queryKey: ['membership', salonId],
    queryFn: () => apiClient.getMembership(),
    enabled: !!salonId,
    retry: false,
  });

  const hasSubscription = !!subscriptionData?.subscription;

  // Fetch usage data (only if subscription exists)
  const { data: usageData } = useQuery({
    queryKey: ['membership-usage', salonId],
    queryFn: () => apiClient.getMembershipUsage(),
    enabled: !!salonId && hasSubscription,
    retry: false,
  });

  // Fetch invoices (only if subscription exists)
  const { data: invoicesData } = useQuery({
    queryKey: ['membership-invoices', salonId],
    queryFn: () => apiClient.getMembershipInvoices(),
    enabled: !!salonId && hasSubscription,
    retry: false,
  });

  // Fetch timeline (only if subscription exists)
  const { data: timelineData } = useQuery({
    queryKey: ['membership-timeline', salonId],
    queryFn: () => apiClient.getMembershipTimeline(),
    enabled: !!salonId && hasSubscription,
    retry: false,
  });

  // Fetch all plans
  const { data: plansData } = useQuery({
    queryKey: ['plans'],
    queryFn: () => apiClient.getMembershipPlans(),
  });

  // Change plan mutation
  const changePlanMutation = useMutation({
    mutationFn: (planId: string) => apiClient.changeMembershipPlan(planId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['membership'] });
      queryClient.invalidateQueries({ queryKey: ['membership-usage'] });
      setShowUpgradeModal(false);
    },
  });

  const subscription = subscriptionData?.subscription;
  const usage = usageData?.usage || [];
  const invoices = invoicesData?.invoices || [];
  const timeline = timelineData?.timeline || [];
  const plans = plansData?.plans || [];

  if (subscriptionLoading) {
    return (
      <DashboardLayout>
        <div className="flex items-center justify-center h-64">
          <div className="text-text-secondary">Loading membership information...</div>
        </div>
      </DashboardLayout>
    );
  }

  if (!subscription) {
    return (
      <DashboardLayout>
        <div className="max-w-7xl mx-auto">
          <div className="bg-card border border-border-medium rounded-2xl p-8 text-center">
            <Crown className="w-16 h-16 text-[#FFD700] mx-auto mb-4" />
            <h2 className="text-text-primary text-2xl font-bold mb-2">Start Your Free Trial</h2>
            <p className="text-text-secondary mb-6">Choose a plan to get started with Yo.Salon</p>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
              {plans.map((plan: Plan) => (
                <PlanCard
                  key={plan.id}
                  plan={plan}
                  isCurrent={false}
                  onSelect={() => {/* Start trial logic */}}
                />
              ))}
            </div>
          </div>
        </div>
      </DashboardLayout>
    );
  }

  const daysUntilRenewal = subscription.renews_at ? getDaysUntil(subscription.renews_at) : 0;
  const isTrial = subscription.status === 'trialing';

  return (
    <DashboardLayout>
      <div className="max-w-7xl mx-auto space-y-8">

        {/* ── Hero Section ─────────────────────────────────────────────────── */}
        <div className="bg-gradient-to-br from-[#FFD700]/10 via-[#FFD700]/5 to-transparent border border-[#FFD700]/25 rounded-2xl p-8">
          <div className="flex items-start justify-between mb-6">
            <div>
              <div className="flex items-center gap-3 mb-2">
                <Crown className="w-6 h-6 text-[#FFD700]" />
                <h1 className="text-text-primary text-2xl font-bold">Your Workspace</h1>
              </div>
              <p className="text-text-secondary">Everything is running smoothly</p>
            </div>
            <StatusBadge status={subscription.status} />
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div>
              <p className="text-text-secondary text-sm mb-1">Plan</p>
              <p className="text-text-primary text-xl font-bold">{subscription.plan.name}</p>
            </div>
            <div>
              <p className="text-text-secondary text-sm mb-1">Billing Cycle</p>
              <p className="text-text-primary text-xl font-bold capitalize">{subscription.billing_cycle}</p>
            </div>
            <div>
              <p className="text-text-secondary text-sm mb-1">
                {isTrial ? 'Trial ends' : 'Renews in'}
              </p>
              <p className="text-text-primary text-xl font-bold">
                {isTrial && subscription.trial_ends_at
                  ? `${getDaysUntil(subscription.trial_ends_at)} days`
                  : subscription.renews_at
                  ? `${daysUntilRenewal} days`
                  : 'N/A'
                }
              </p>
            </div>
          </div>

          <div className="flex gap-3">
            <button
              onClick={() => setShowUpgradeModal(true)}
              className="flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-[#FFD700] to-[#C9A227] text-black rounded-xl text-sm font-semibold hover:opacity-90 transition-opacity"
            >
              <ArrowUpRight className="w-4 h-4" />
              Upgrade Plan
            </button>
            {subscription.cancelled_at ? (
              <button className="flex items-center gap-2 px-4 py-2 bg-surface text-text-primary rounded-xl text-sm font-medium hover:bg-card transition-colors border border-border-medium">
                <ArrowDownRight className="w-4 h-4" />
                Resume Subscription
              </button>
            ) : (
              <button className="flex items-center gap-2 px-4 py-2 bg-surface text-text-secondary rounded-xl text-sm font-medium hover:bg-card hover:text-text-primary transition-colors border border-border-medium">
                Cancel
              </button>
            )}
          </div>
        </div>

        {/* ── Usage Cards ───────────────────────────────────────────────────── */}
        <div>
          <h2 className="text-text-primary text-lg font-semibold mb-4 flex items-center gap-2">
            <TrendingUp className="w-5 h-5 text-[#FFD700]" />
            Usage Overview
          </h2>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            {usage.map((metric: UsageMetric) => (
              <UsageCard key={metric.metric} metric={metric} />
            ))}
          </div>
        </div>

        {/* ── Membership Benefits ─────────────────────────────────────────────── */}
        <div>
          <h2 className="text-text-primary text-lg font-semibold mb-4 flex items-center gap-2">
            <Sparkles className="w-5 h-5 text-[#FFD700]" />
            Included Features
          </h2>
          <div className="bg-card border border-border-medium rounded-2xl p-6">
            {subscription.plan.features && subscription.plan.features.length > 0 ? (
              <FeatureList features={subscription.plan.features} />
            ) : (
              <p className="text-text-secondary text-sm">No features listed for this plan</p>
            )}
          </div>
        </div>

        {/* ── Billing Timeline ───────────────────────────────────────────────── */}
        <div>
          <h2 className="text-text-primary text-lg font-semibold mb-4 flex items-center gap-2">
            <Clock className="w-5 h-5 text-[#FFD700]" />
            Account History
          </h2>
          <div className="bg-card border border-border-medium rounded-2xl p-6">
            {timeline.length > 0 ? (
              <div className="max-h-64 overflow-y-auto">
                {timeline.map((event: TimelineEvent) => (
                  <TimelineEvent key={event.id} event={event} />
                ))}
              </div>
            ) : (
              <p className="text-text-secondary text-sm">No history available</p>
            )}
          </div>
        </div>

        {/* ── Invoices ───────────────────────────────────────────────────────── */}
        <div>
          <h2 className="text-text-primary text-lg font-semibold mb-4 flex items-center gap-2">
            <CreditCard className="w-5 h-5 text-[#FFD700]" />
            Invoices
          </h2>
          <div className="space-y-3">
            {invoices.length > 0 ? (
              invoices.map((invoice: Invoice) => (
                <InvoiceRow key={invoice.id} invoice={invoice} />
              ))
            ) : (
              <div className="bg-card border border-border-medium rounded-2xl p-8 text-center">
                <CreditCard className="w-12 h-12 text-text-secondary/30 mx-auto mb-3" />
                <p className="text-text-secondary text-sm">No invoices yet</p>
              </div>
            )}
          </div>
        </div>

      </div>

      {/* ── Upgrade Modal ───────────────────────────────────────────────────── */}
      <AnimatePresence>
        {showUpgradeModal && (
          <>
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              onClick={() => setShowUpgradeModal(false)}
              className="fixed inset-0 bg-overlay backdrop-blur-sm z-40"
            />
            <motion.div
              initial={{ opacity: 0, scale: 0.95 }}
              animate={{ opacity: 1, scale: 1 }}
              exit={{ opacity: 0, scale: 0.95 }}
              className="fixed inset-4 md:inset-auto md:top-1/2 md:left-1/2 md:-translate-x-1/2 md:-translate-y-1/2 md:max-w-6xl md:w-full bg-surface border border-border-medium rounded-2xl z-50 shadow-2xl overflow-hidden"
            >
              <div className="p-6 border-b border-border-medium flex items-center justify-between">
                <div>
                  <h2 className="text-text-primary text-xl font-bold">Choose Your Workspace</h2>
                  <p className="text-text-secondary text-sm">Select the plan that fits your business</p>
                </div>
                <button
                  onClick={() => setShowUpgradeModal(false)}
                  className="p-2 hover:bg-card rounded-lg transition-colors text-text-secondary hover:text-text-primary"
                >
                  <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>

              <div className="p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 max-h-[60vh] overflow-y-auto">
                {plans.map((plan: Plan) => (
                  <PlanCard
                    key={plan.id}
                    plan={plan}
                    isCurrent={plan.id === subscription.plan_id}
                    onSelect={() => changePlanMutation.mutate(plan.id)}
                  />
                ))}
              </div>
            </motion.div>
          </>
        )}
      </AnimatePresence>
    </DashboardLayout>
  );
}
