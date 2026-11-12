'use client';

import { useSpecialistAuth } from '@/contexts/SpecialistAuthContext';
import { useQuery } from '@tanstack/react-query';
import { motion } from 'framer-motion';
import { 
  DollarSign, 
  TrendingUp, 
  Calendar, 
  Target,
  Wallet,
  Gift,
  ArrowUpRight,
  ArrowDownRight
} from 'lucide-react';
import { useState } from 'react';
import { apiClient } from '@/lib/api-client';
import { useSpecialistCapabilities } from '@/hooks/useSpecialistCapabilities';
import UpgradeBanner from '@/components/specialist/UpgradeBanner';
import PaymentProfileCard from '@/components/specialist/PaymentProfileCard';
import AddPaymentProfileModal from '@/components/specialist/AddPaymentProfileModal';
import { Plus } from 'lucide-react';

export default function FinancePage() {
  const { specialist } = useSpecialistAuth();
  const { hasCapability, isPro } = useSpecialistCapabilities();
  const [period, setPeriod] = useState<'today' | 'week' | 'month'>('today');
  const [activeTab, setActiveTab] = useState<'earnings' | 'payment_methods'>('earnings');

  const [isAddMethodOpen, setIsAddMethodOpen] = useState(false);
  const [editingProfile, setEditingProfile] = useState<any>(null);

  const { data: profiles, isLoading: loadingProfiles, refetch: refetchProfiles } = useQuery({
    queryKey: ['specialist-payment-profiles'],
    queryFn: async () => {
      return await apiClient.getSpecialistPaymentProfiles();
    },
    enabled: !!specialist && activeTab === 'payment_methods',
  });

  const { data: financeData, isLoading: loadingFinance } = useQuery({
    queryKey: ['specialist-finance', period],
    queryFn: async () => {
      const data = await apiClient.get(`/v1/specialist-portal/earnings?period=${period}`);

      return {
        revenue: data.total_earned || 0,
        commission: data.total_settled || 0,
        tips: 0,
        averageTicket: data.clients > 0 ? Math.round(data.total_earned / data.clients) : 0,
        clients: data.by_salon?.reduce((sum: number, salon: any) => sum + (salon.clients || 0), 0) || 0,
        projected: data.outstanding_balance || 0,
      };
    },
    enabled: !!specialist && isPro(),
  });

  const { data: journeyData, isLoading: loadingJourney } = useQuery({
    queryKey: ['specialist-journey'],
    queryFn: async () => {
      return await apiClient.get('/v1/specialist-portal/journey');
    },
    enabled: !!specialist && isPro(),
  });

  const financeGoals = journeyData?.goals?.filter((g: any) => 
    g.title === 'Daily Revenue Target' || g.title === 'Monthly Revenue Target'
  ) || [];

  const periods = [
    { id: 'today' as const, label: 'Today' },
    { id: 'week' as const, label: 'This Week' },
    { id: 'month' as const, label: 'This Month' },
  ];

  return (
    <div className="p-4 sm:p-6 lg:p-8 space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-text-primary">Finance</h1>
        <p className="text-text-secondary">Your earnings and motivation</p>
      </div>

      {!isPro() && (
        <UpgradeBanner 
          featureName="Finance"
          description="Track your revenue, commission, and financial goals with detailed reports"
        />
      )}

      {/* Pro Dashboard - only render when user has Pro access */}
      {isPro() && (
        <>
      {/* Tabs */}
      <div className="border-b border-border-light mb-6">
        <nav className="-mb-px flex space-x-8">
          <button
            onClick={() => setActiveTab('earnings')}
            className={`whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium ${
              activeTab === 'earnings'
                ? 'border-indigo-500 text-indigo-600'
                : 'border-transparent text-text-secondary hover:border-border-light hover:text-text-primary'
            }`}
          >
            Earnings
          </button>
          <button
            onClick={() => setActiveTab('payment_methods')}
            className={`whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium ${
              activeTab === 'payment_methods'
                ? 'border-indigo-500 text-indigo-600'
                : 'border-transparent text-text-secondary hover:border-border-light hover:text-text-primary'
            }`}
          >
            Payment Methods
          </button>
        </nav>
      </div>

      {activeTab === 'earnings' ? (
        <>
      {/* Period Selector */}
      <div className="flex gap-2">
        {periods.map((p) => (
          <button
            key={p.id}
            onClick={() => setPeriod(p.id)}
            className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
              period === p.id
                ? 'bg-gold text-black'
                : 'bg-surface text-text-secondary hover:text-text-primary'
            }`}
          >
            {p.label}
          </button>
        ))}
      </div>

      {/* Main Revenue Card */}
      {loadingFinance ? (
        <div className="bg-gradient-to-br from-green-400/20 to-emerald-600/20 border border-green-400/30 rounded-2xl p-6 animate-pulse">
          <div className="h-12 w-32 bg-surface rounded mb-4"></div>
          <div className="h-16 w-16 bg-surface rounded-full"></div>
        </div>
      ) : (
        <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        className="bg-gradient-to-br from-green-400/20 to-emerald-600/20 border border-green-400/30 rounded-2xl p-6"
      >
        <div className="flex items-center justify-between">
          <div>
            <p className="text-sm text-text-secondary mb-1">Total Revenue</p>
            <p className="text-4xl font-bold text-text-primary">
              UGX {financeData?.revenue?.toLocaleString('en-UG', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}
            </p>
          </div>
          <div className="w-16 h-16 rounded-full bg-green-400/20 flex items-center justify-center">
            <DollarSign className="w-8 h-8 text-green-400" />
          </div>
        </div>
      </motion.div>
      )}

      {/* Earnings Breakdown */}
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        {[
          { label: 'Commission', value: financeData?.commission, icon: Wallet, color: 'text-blue-400', bg: 'bg-blue-400/20' },
          { label: 'Average Ticket', value: financeData?.averageTicket, icon: Target, color: 'text-amber-400', bg: 'bg-amber-400/20' },
        ].map((stat, index) => {
          const Icon = stat.icon;
          return (
            <motion.div
              key={stat.label}
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.1 + index * 0.1 }}
              className="bg-card border border-border-light rounded-2xl p-4"
            >
              <div className={`w-10 h-10 rounded-lg ${stat.bg} flex items-center justify-center mb-3`}>
                <Icon className={`w-5 h-5 ${stat.color}`} />
              </div>
              <p className="text-sm text-text-secondary mb-1">{stat.label}</p>
              <p className="text-xl font-bold text-text-primary">
                UGX {stat.value?.toLocaleString('en-UG', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}
              </p>
            </motion.div>
          );
        })}
      </div>


      {/* Pending Earnings */}
      <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.5 }}
        className="bg-card border border-border-light rounded-2xl p-6"
      >
        <div className="flex items-center gap-2 mb-4">
          <TrendingUp className="w-5 h-5 text-gold" />
          <h3 className="font-semibold text-text-primary">Pending Earnings</h3>
        </div>
        <div className="flex items-center justify-between">
          <div>
            <p className="text-3xl font-bold text-text-primary">
              UGX {financeData?.projected?.toLocaleString('en-UG', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}
            </p>
            <p className="text-sm text-text-secondary mt-2">
              Based on current bookings
            </p>
          </div>
          <div className="flex items-center gap-2 text-green-400">
            <ArrowUpRight className="w-5 h-5" />
            <span className="text-sm font-medium">
              {Math.round(((financeData?.projected || 0) / (financeData?.revenue || 1)) * 100 - 100)}%
            </span>
          </div>
        </div>
      </motion.div>

      {/* Target Progress */}
      {financeGoals.length > 0 ? (
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          {financeGoals.map((goal: any, idx: number) => {
            const progressPct = goal.target > 0 ? Math.min(100, Math.round((goal.progress / goal.target) * 100)) : 0;
            return (
              <motion.div
                key={goal.id}
                initial={{ opacity: 0, y: 10 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: 0.6 + idx * 0.1 }}
                className="bg-card border border-border-light rounded-2xl p-6"
              >
                <div className="flex items-center justify-between mb-4">
                  <h3 className="font-semibold text-text-primary">{goal.title}</h3>
                  <Target className="w-5 h-5 text-gold" />
                </div>
                <div className="space-y-2">
                  <div className="flex justify-between text-sm">
                    <span className="text-text-secondary">Progress</span>
                    <span className="text-text-primary font-medium">UGX {goal.progress.toLocaleString('en-UG', { minimumFractionDigits: 0, maximumFractionDigits: 0 })} / {goal.target.toLocaleString('en-UG', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}</span>
                  </div>
                  <div className="h-2 bg-surface rounded-full overflow-hidden">
                    <div 
                      className="h-full bg-gradient-to-r from-gold to-amber-600 rounded-full"
                      style={{ width: `${progressPct}%` }}
                    />
                  </div>
                  <p className="text-xs text-text-secondary text-right">{progressPct}% reached</p>
                </div>
              </motion.div>
            );
          })}
        </div>
      ) : (
        <motion.div
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.6 }}
          className="bg-card border border-border-light rounded-2xl p-6 flex flex-col items-center justify-center text-center space-y-4"
        >
          <div className="w-16 h-16 rounded-full bg-surface flex items-center justify-center">
            <Target className="w-8 h-8 text-gold" />
          </div>
          <div>
            <h3 className="font-semibold text-text-primary text-lg">Set Your Financial Goals</h3>
            <p className="text-sm text-text-secondary mt-1 max-w-sm mx-auto">
              Track your daily and monthly targets. Head over to the Journey tab to set your goals.
            </p>
          </div>
          <a 
            href="/specialist-portal/journey"
            className="px-6 py-2 bg-gradient-to-r from-gold to-amber-600 text-white rounded-lg font-medium hover:opacity-90 transition-opacity"
          >
            Go to Journey
          </a>
        </motion.div>
      )}
      </>
      ) : (
        <div className="space-y-6">
          <div className="flex items-center justify-between">
            <div>
              <h2 className="text-lg font-medium text-text-primary">Payment Methods</h2>
              <p className="text-sm text-text-secondary">Manage where you receive your earnings</p>
            </div>
            <button
              onClick={() => {
                setEditingProfile(null);
                setIsAddMethodOpen(true);
              }}
              className="flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
            >
              <Plus className="h-4 w-4" />
              Add Method
            </button>
          </div>

          {loadingProfiles ? (
            <div className="space-y-3">
              <div className="h-20 w-full animate-pulse rounded-xl bg-surface"></div>
              <div className="h-20 w-full animate-pulse rounded-xl bg-surface"></div>
            </div>
          ) : profiles?.length > 0 ? (
            <div className="space-y-3">
              {profiles.map((profile: any) => (
                <PaymentProfileCard
                  key={profile.id}
                  profile={profile}
                  onEdit={(p) => {
                    setEditingProfile(p);
                    setIsAddMethodOpen(true);
                  }}
                  onRemove={async (p) => {
                    if (confirm('Are you sure you want to remove this payment method?')) {
                      await apiClient.deleteSpecialistPaymentProfile(p.id);
                      refetchProfiles();
                    }
                  }}
                  onMakeDefault={async (p) => {
                    await apiClient.updateSpecialistPaymentProfile(p.id, { is_default: true });
                    refetchProfiles();
                  }}
                />
              ))}
            </div>
          ) : (
            <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border-light bg-card p-12 text-center">
              <div className="flex h-12 w-12 items-center justify-center rounded-full bg-indigo-50 text-indigo-600 mb-4">
                <Wallet className="h-6 w-6" />
              </div>
              <h3 className="text-lg font-medium text-text-primary mb-1">No payment methods</h3>
              <p className="text-sm text-text-secondary mb-6 max-w-sm">
                Add a mobile money number or bank account to receive your payouts automatically.
              </p>
              <button
                onClick={() => {
                  setEditingProfile(null);
                  setIsAddMethodOpen(true);
                }}
                className="flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
              >
                <Plus className="h-4 w-4" />
                Add Method
              </button>
            </div>
          )}

          {isAddMethodOpen && (
            <AddPaymentProfileModal
              existingProfile={editingProfile}
              onClose={() => {
                setIsAddMethodOpen(false);
                setEditingProfile(null);
              }}
              onSaved={() => {
                setIsAddMethodOpen(false);
                setEditingProfile(null);
                refetchProfiles();
              }}
            />
          )}
        </div>
      )}
      </>
      )}
    </div>
  );
}
