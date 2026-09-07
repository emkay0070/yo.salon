'use client';

import { motion } from 'framer-motion';
import { 
  Crown, 
  Sparkles, 
  Calendar, 
  CreditCard, 
  AlertTriangle,
  ChevronRight,
  Download,
  HelpCircle
} from 'lucide-react';

interface SubscriptionManagementProps {
  capabilities: any;
  onManagePayment?: () => void;
  onViewInvoices?: () => void;
  onCancel?: () => void;
}

export default function SubscriptionManagement({ 
  capabilities, 
  onManagePayment,
  onViewInvoices,
  onCancel 
}: SubscriptionManagementProps) {
  const isPro = capabilities?.plan?.slug === 'specialist-pro';
  const isTrialing = capabilities?.subscription?.is_trialing;
  const trialEndsAt = capabilities?.subscription?.trial_ends_at;
  const renewsAt = capabilities?.subscription?.renews_at;

  if (!isPro) {
    return null;
  }

  return (
    <div className="space-y-4">
      {/* Subscription Status */}
      <div className="bg-gradient-to-br from-gold/20 to-amber-600/10 border border-gold/30 rounded-2xl p-6">
        <div className="flex items-center gap-3 mb-4">
          <div className="w-12 h-12 rounded-full bg-gold/30 flex items-center justify-center">
            <Sparkles className="w-6 h-6 text-gold" />
          </div>
          <div>
            <h3 className="text-lg font-bold text-text-primary">Pro Plan</h3>
            <p className="text-sm text-text-secondary">
              {isTrialing ? 'Trial Active' : 'Active Subscription'}
            </p>
          </div>
        </div>

        {isTrialing && trialEndsAt && (
          <div className="bg-blue-500/10 border border-blue-500/20 rounded-xl p-4 mb-4">
            <div className="flex items-center gap-2 mb-2">
              <Calendar className="w-4 h-4 text-blue-400" />
              <span className="font-medium text-text-primary">Trial Period</span>
            </div>
            <p className="text-sm text-text-secondary">
              Your trial ends on {new Date(trialEndsAt).toLocaleDateString()}. 
              You won't be charged until the trial ends.
            </p>
          </div>
        )}

        {renewsAt && !isTrialing && (
          <div className="flex items-center justify-between text-sm">
            <span className="text-text-secondary">Next billing date</span>
            <span className="font-medium text-text-primary">
              {new Date(renewsAt).toLocaleDateString()}
            </span>
          </div>
        )}
      </div>

      {/* Management Options */}
      <div className="bg-card border border-border-light rounded-2xl overflow-hidden">
        <button
          onClick={onManagePayment}
          className="w-full flex items-center justify-between p-4 hover:bg-surface transition-colors"
        >
          <div className="flex items-center gap-3">
            <CreditCard className="w-5 h-5 text-text-secondary" />
            <span className="text-text-primary">Payment Method</span>
          </div>
          <ChevronRight className="w-5 h-5 text-text-muted" />
        </button>

        <button
          onClick={onViewInvoices}
          className="w-full flex items-center justify-between p-4 hover:bg-surface transition-colors border-t border-border-light"
        >
          <div className="flex items-center gap-3">
            <Download className="w-5 h-5 text-text-secondary" />
            <span className="text-text-primary">Invoices</span>
          </div>
          <ChevronRight className="w-5 h-5 text-text-muted" />
        </button>

        <button
          onClick={onCancel}
          className="w-full flex items-center justify-between p-4 hover:bg-red-500/5 transition-colors border-t border-border-light group"
        >
          <div className="flex items-center gap-3">
            <AlertTriangle className="w-5 h-5 text-red-400 group-hover:text-red-300" />
            <span className="text-text-primary group-hover:text-red-400">Cancel Subscription</span>
          </div>
          <ChevronRight className="w-5 h-5 text-text-muted group-hover:text-red-400" />
        </button>
      </div>

      {/* Help Section */}
      <div className="bg-card border border-border-light rounded-2xl p-4">
        <div className="flex items-center gap-3">
          <HelpCircle className="w-5 h-5 text-text-secondary" />
          <div className="flex-1">
            <p className="text-sm text-text-primary">Need help with your subscription?</p>
            <p className="text-xs text-text-secondary">Contact support for assistance</p>
          </div>
          <button className="px-4 py-2 bg-surface border border-border-light rounded-lg text-sm font-medium text-text-primary hover:bg-border-light transition-colors">
            Contact Support
          </button>
        </div>
      </div>
    </div>
  );
}
