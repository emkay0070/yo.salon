'use client';

import { useSpecialistAuth } from '@/contexts/SpecialistAuthContext';
import { useQuery } from '@tanstack/react-query';
import { motion } from 'framer-motion';
import { 
  Brain, 
  TrendingUp, 
  Users, 
  Calendar,
  AlertTriangle,
  Lightbulb,
  ArrowRight,
  RefreshCw,
  Loader2
} from 'lucide-react';
import { apiClient } from '@/lib/api-client';
import { useSpecialistCapabilities } from '@/hooks/useSpecialistCapabilities';
import UpgradeBanner from '@/components/specialist/UpgradeBanner';

export default function IntelligencePage() {
  const { specialist } = useSpecialistAuth();
  const { hasCapability, isPro } = useSpecialistCapabilities();

  const { data: insights, isLoading, isError } = useQuery({
    queryKey: ['specialist-intelligence'],
    queryFn: async () => {
      return await apiClient.get('/v1/specialist-portal/intelligence');
    },
    enabled: !!specialist && isPro(),
    retry: false,
  });

  return (
    <div className="p-4 sm:p-6 lg:p-8 space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-text-primary">Intelligence</h1>
          <p className="text-text-secondary">AI-powered professional insights</p>
        </div>
        <button className="flex items-center gap-2 px-4 py-2 rounded-lg bg-surface border border-border-light text-sm text-text-secondary hover:text-text-primary transition-colors">
          <RefreshCw className="w-4 h-4" />
          Refresh
        </button>
      </div>

      {!isPro() && (
        <UpgradeBanner 
          featureName="Intelligence"
          description="Get AI-powered insights about your customers, trends, and opportunities"
        />
      )}

      {/* Pro Dashboard - only render when user has Pro access */}
      {isPro() && (
        <>
      {isLoading ? (
        <div className="flex items-center justify-center py-24">
          <Loader2 className="w-8 h-8 text-gold animate-spin" />
        </div>
      ) : isError ? (
        <div className="bg-card border border-border-light rounded-2xl p-12 text-center">
          <AlertTriangle className="w-12 h-12 text-text-muted mx-auto mb-4" />
          <p className="text-text-secondary font-medium">Intelligence features are coming soon</p>
          <p className="text-sm text-text-muted mt-1">AI-powered insights will be available in a future update.</p>
        </div>
      ) : (
      <>
      {/* Follow-ups */}
      <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        className="bg-card border border-border-light rounded-2xl p-6"
      >
        <div className="flex items-center gap-2 mb-4">
          <Calendar className="w-5 h-5 text-blue-400" />
          <h3 className="font-semibold text-text-primary">Customers Needing Follow-up</h3>
        </div>
        <div className="space-y-3">
          {insights?.followUps?.map((item: any, index: number) => (
            <motion.div
              key={item.id}
              initial={{ opacity: 0, x: -10 }}
              animate={{ opacity: 1, x: 0 }}
              transition={{ delay: index * 0.1 }}
              className="flex items-center justify-between p-4 bg-surface rounded-xl border border-border-light"
            >
              <div className="flex items-center gap-4">
                <div className="w-10 h-10 rounded-full bg-gradient-to-br from-gold to-amber-600 flex items-center justify-center text-white font-bold">
                  {item.customer.charAt(0)}
                </div>
                <div>
                  <p className="font-medium text-text-primary">{item.customer}</p>
                  <p className="text-sm text-text-secondary">
                    {item.daysOverdue} days overdue • Last: {new Date(item.lastBooking).toLocaleDateString()}
                  </p>
                </div>
              </div>
              <div className="text-right">
                <p className="text-sm text-text-secondary">Suggest</p>
                <p className="text-sm font-medium text-gold">{item.suggestedService}</p>
                <button className="mt-2 px-3 py-1.5 bg-gold/10 text-gold rounded-lg text-xs font-medium hover:bg-gold/20 transition-colors">
                  Send Reminder
                </button>
              </div>
            </motion.div>
          ))}
        </div>
      </motion.div>

      {/* Trends */}
      <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.1 }}
        className="bg-card border border-border-light rounded-2xl p-6"
      >
        <div className="flex items-center gap-2 mb-4">
          <TrendingUp className="w-5 h-5 text-green-400" />
          <h3 className="font-semibold text-text-primary">Trends & Opportunities</h3>
        </div>
        <div className="space-y-3">
          {insights?.trends?.map((trend: any, index: number) => (
            <motion.div
              key={trend.id}
              initial={{ opacity: 0, x: -10 }}
              animate={{ opacity: 1, x: 0 }}
              transition={{ delay: 0.2 + index * 0.1 }}
              className="p-4 bg-surface rounded-xl border border-border-light"
            >
              <div className="flex items-start justify-between gap-4">
                <div className="flex-1">
                  <p className="font-medium text-text-primary mb-1">{trend.trend}</p>
                  <p className="text-sm text-text-secondary">{trend.recommendation}</p>
                </div>
                <div className="flex flex-col items-end gap-2">
                  <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                    trend.impact === 'high' ? 'bg-green-400/20 text-green-400' : 'bg-amber-400/20 text-amber-400'
                  }`}>
                    {trend.impact} impact
                  </span>
                  <button className="px-3 py-1.5 bg-gold/10 text-gold rounded-lg text-xs font-medium hover:bg-gold/20 transition-colors">
                    Apply
                  </button>
                </div>
              </div>
            </motion.div>
          ))}
        </div>
      </motion.div>

      {/* Sales Opportunities */}
      <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.2 }}
        className="bg-card border border-border-light rounded-2xl p-6"
      >
        <div className="flex items-center gap-2 mb-4">
          <Lightbulb className="w-5 h-5 text-amber-400" />
          <h3 className="font-semibold text-text-primary">Product Opportunities</h3>
        </div>
        <div className="space-y-3">
          {insights?.opportunities?.map((opp: any, index: number) => (
            <motion.div
              key={opp.id}
              initial={{ opacity: 0, x: -10 }}
              animate={{ opacity: 1, x: 0 }}
              transition={{ delay: 0.3 + index * 0.1 }}
              className="flex items-center justify-between p-4 bg-surface rounded-xl border border-border-light"
            >
              <div className="flex items-center gap-4">
                <div className="w-10 h-10 rounded-full bg-gradient-to-br from-gold to-amber-600 flex items-center justify-center text-white font-bold">
                  {opp.customer.charAt(0)}
                </div>
                <div>
                  <p className="font-medium text-text-primary">{opp.customer}</p>
                  <p className="text-sm text-text-secondary">{opp.reason}</p>
                </div>
              </div>
              <div className="text-right">
                <p className="text-sm font-medium text-gold">{opp.product}</p>
                <p className="text-xs text-text-secondary">{opp.likelihood}% likely</p>
                <button className="mt-2 px-3 py-1.5 bg-gold/10 text-gold rounded-lg text-xs font-medium hover:bg-gold/20 transition-colors">
                  Suggest
                </button>
              </div>
            </motion.div>
          ))}
        </div>
      </motion.div>

      {/* Strengths */}
      <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.3 }}
        className="bg-card border border-border-light rounded-2xl p-6"
      >
        <div className="flex items-center gap-2 mb-4">
          <Brain className="w-5 h-5 text-purple-400" />
          <h3 className="font-semibold text-text-primary">Your Strengths</h3>
        </div>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          {insights?.strengths?.map((strength: any, index: number) => (
            <motion.div
              key={strength.id}
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.4 + index * 0.1 }}
              className="p-4 bg-surface rounded-xl border border-border-light"
            >
              <div className="flex items-center justify-between mb-2">
                <p className="font-medium text-text-primary">{strength.service}</p>
                <span className="text-lg font-bold text-gold">{strength.rating}%</span>
              </div>
              <div className="h-2 bg-surface rounded-full overflow-hidden mb-2">
                <div
                  className="h-full bg-gradient-to-r from-gold to-amber-600 transition-all"
                  style={{ width: `${strength.rating}%` }}
                />
              </div>
              <p className="text-xs text-text-secondary">{strength.bookings} bookings</p>
            </motion.div>
          ))}
        </div>
      </motion.div>

      {/* At Risk Customers */}
      <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.4 }}
        className="bg-card border border-red-400/30 rounded-2xl p-6"
      >
        <div className="flex items-center gap-2 mb-4">
          <AlertTriangle className="w-5 h-5 text-red-400" />
          <h3 className="font-semibold text-text-primary">At Risk Customers</h3>
        </div>
        <div className="space-y-3">
          {insights?.atRisk?.map((customer: any, index: number) => (
            <motion.div
              key={customer.id}
              initial={{ opacity: 0, x: -10 }}
              animate={{ opacity: 1, x: 0 }}
              transition={{ delay: 0.5 + index * 0.1 }}
              className="flex items-center justify-between p-4 bg-surface rounded-xl border border-red-400/20"
            >
              <div className="flex items-center gap-4">
                <div className="w-10 h-10 rounded-full bg-gradient-to-br from-red-400 to-red-600 flex items-center justify-center text-white font-bold">
                  {customer.customer.charAt(0)}
                </div>
                <div>
                  <p className="font-medium text-text-primary">{customer.customer}</p>
                  <p className="text-sm text-text-secondary">{customer.reason}</p>
                </div>
              </div>
              <div className="flex items-center gap-2">
                <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                  customer.risk === 'high' ? 'bg-red-400/20 text-red-400' : 'bg-amber-400/20 text-amber-400'
                }`}>
                  {customer.risk} risk
                </span>
                <button className="px-3 py-1.5 bg-gold/10 text-gold rounded-lg text-xs font-medium hover:bg-gold/20 transition-colors">
                  Reach Out
                </button>
              </div>
            </motion.div>
          ))}
        </div>
      </motion.div>
      </>
      )}
      </>
      )}
    </div>
  );
}
