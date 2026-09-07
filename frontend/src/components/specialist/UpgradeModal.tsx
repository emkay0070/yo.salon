'use client';

import { motion, AnimatePresence } from 'framer-motion';
import { 
  Crown, 
  Sparkles, 
  Check, 
  X, 
  ArrowRight,
  LayoutDashboard,
  Calendar,
  CalendarClock,
  User,
  Scissors,
  Users,
  Briefcase,
  TrendingUp,
  DollarSign,
  Settings,
  Shield,
  Clock,
  Loader2
} from 'lucide-react';
import { useState } from 'react';

interface UpgradeModalProps {
  isOpen: boolean;
  onClose: () => void;
  featureName?: string;
}

const proFeatures = [
  { icon: Briefcase, name: 'Career', description: 'Career tracking & milestones' },
  { icon: TrendingUp, name: 'Intelligence', description: 'Business insights & analytics' },
  { icon: DollarSign, name: 'Finance', description: 'Revenue tracking & reports' },
  { icon: Clock, name: 'Journey', description: 'Growth goals & achievements' },
];

const freeFeatures = [
  { icon: LayoutDashboard, name: 'Workspace', description: 'Professional dashboard' },
  { icon: Calendar, name: 'Calendar', description: 'Schedule management' },
  { icon: CalendarClock, name: 'Appointments', description: 'Booking management' },
  { icon: User, name: 'Profile', description: 'Public profile' },
  { icon: Scissors, name: 'Craft', description: 'Services & expertise' },
  { icon: Users, name: 'Clients', description: 'Customer management' },
  { icon: Settings, name: 'Settings', description: 'Account settings' },
  { icon: Shield, name: 'Verification', description: 'Professional verification' },
];

export default function UpgradeModal({ isOpen, onClose, featureName }: UpgradeModalProps) {
  const [step, setStep] = useState<'overview' | 'confirm' | 'processing' | 'success'>('overview');
  const [billingCycle, setBillingCycle] = useState<'monthly' | 'yearly'>('monthly');

  if (!isOpen) return null;

  const handleUpgrade = () => {
    setStep('confirm');
  };

  const handleConfirm = () => {
    setStep('processing');
    // Simulate processing
    setTimeout(() => {
      setStep('success');
    }, 2000);
  };

  const handleClose = () => {
    setStep('overview');
    onClose();
  };

  return (
    <AnimatePresence>
      {isOpen && (
        <>
          {/* Backdrop */}
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            onClick={handleClose}
            className="fixed inset-0 bg-black/60 backdrop-blur-sm z-50"
          />

          {/* Modal */}
          <motion.div
            initial={{ opacity: 0, scale: 0.95, y: 20 }}
            animate={{ opacity: 1, scale: 1, y: 0 }}
            exit={{ opacity: 0, scale: 0.95, y: 20 }}
            className="fixed inset-0 z-50 flex items-center justify-center p-4"
          >
            <div className="bg-card border border-border-light rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
              {step === 'overview' && (
                <div className="p-6 space-y-6">
                  {/* Header */}
                  <div className="flex items-start justify-between">
                    <div className="flex items-center gap-4">
                      <div className="w-14 h-14 rounded-full bg-gold/30 flex items-center justify-center">
                        <Sparkles className="w-7 h-7 text-gold" />
                      </div>
                      <div>
                        <h2 className="text-2xl font-bold text-text-primary">
                          Upgrade to Pro
                        </h2>
                        <p className="text-text-secondary">
                          {featureName ? `Unlock ${featureName} and advanced features` : 'Unlock all advanced features'}
                        </p>
                      </div>
                    </div>
                    <button
                      onClick={handleClose}
                      className="p-2 rounded-lg hover:bg-surface text-text-secondary"
                    >
                      <X className="w-5 h-5" />
                    </button>
                  </div>

                  {/* Billing Cycle Toggle */}
                  <div className="flex items-center gap-3 p-1 bg-surface rounded-xl">
                    <button
                      onClick={() => setBillingCycle('monthly')}
                      className={`flex-1 py-2 px-4 rounded-lg text-sm font-medium transition-colors ${
                        billingCycle === 'monthly'
                          ? 'bg-card text-text-primary shadow-sm'
                          : 'text-text-secondary hover:text-text-primary'
                      }`}
                    >
                      Monthly
                    </button>
                    <button
                      onClick={() => setBillingCycle('yearly')}
                      className={`flex-1 py-2 px-4 rounded-lg text-sm font-medium transition-colors ${
                        billingCycle === 'yearly'
                          ? 'bg-card text-text-primary shadow-sm'
                          : 'text-text-secondary hover:text-text-primary'
                      }`}
                    >
                      Yearly
                      <span className="ml-1 text-xs text-gold">Save 20%</span>
                    </button>
                  </div>

                  {/* Pricing */}
                  <div className="bg-gradient-to-br from-gold/20 to-amber-600/10 border-2 border-gold/30 rounded-2xl p-6">
                    <div className="flex items-baseline gap-2 mb-2">
                      <span className="text-4xl font-bold text-text-primary">Coming Soon</span>
                      <span className="text-text-secondary">/{billingCycle}</span>
                    </div>
                    <p className="text-sm text-text-secondary">
                      Pricing will be announced soon. Get notified when Pro launches.
                    </p>
                  </div>

                  {/* Pro Features */}
                  <div className="space-y-3">
                    <h3 className="font-semibold text-text-primary">Pro Features</h3>
                    <div className="grid grid-cols-2 gap-3">
                      {proFeatures.map((feature) => {
                        const Icon = feature.icon;
                        return (
                          <div key={feature.name} className="flex items-start gap-3 p-3 bg-surface rounded-xl">
                            <div className="w-8 h-8 rounded-lg bg-gold/20 flex items-center justify-center shrink-0">
                              <Icon className="w-4 h-4 text-gold" />
                            </div>
                            <div>
                              <p className="font-medium text-text-primary text-sm">{feature.name}</p>
                              <p className="text-xs text-text-secondary">{feature.description}</p>
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  </div>

                  {/* Free Features */}
                  <div className="space-y-3">
                    <h3 className="font-semibold text-text-primary">Everything in Free, plus:</h3>
                    <div className="grid grid-cols-2 gap-2">
                      {freeFeatures.map((feature) => {
                        const Icon = feature.icon;
                        return (
                          <div key={feature.name} className="flex items-center gap-2 text-sm">
                            <Check className="w-4 h-4 text-gold shrink-0" />
                            <span className="text-text-secondary">{feature.name}</span>
                          </div>
                        );
                      })}
                    </div>
                  </div>

                  {/* Actions */}
                  <div className="flex items-center gap-3">
                    <button
                      onClick={handleClose}
                      className="flex-1 py-3 border border-border-light rounded-xl font-semibold text-text-primary hover:bg-surface transition-colors"
                    >
                      Maybe Later
                    </button>
                    <button
                      onClick={handleUpgrade}
                      className="flex-1 py-3 bg-gold text-black rounded-xl font-semibold hover:bg-amber-400 transition-colors flex items-center justify-center gap-2"
                    >
                      Get Notified
                      <ArrowRight className="w-4 h-4" />
                    </button>
                  </div>
                </div>
              )}

              {step === 'confirm' && (
                <div className="p-6 space-y-6">
                  <div className="flex items-start justify-between">
                    <div>
                      <h2 className="text-2xl font-bold text-text-primary">Confirm Upgrade</h2>
                      <p className="text-text-secondary">Review your plan selection</p>
                    </div>
                    <button
                      onClick={handleClose}
                      className="p-2 rounded-lg hover:bg-surface text-text-secondary"
                    >
                      <X className="w-5 h-5" />
                    </button>
                  </div>

                  <div className="bg-surface rounded-xl p-4 space-y-3">
                    <div className="flex items-center justify-between">
                      <span className="text-text-primary">Plan</span>
                      <span className="font-semibold text-text-primary">Pro</span>
                    </div>
                    <div className="flex items-center justify-between">
                      <span className="text-text-primary">Billing Cycle</span>
                      <span className="font-semibold text-text-primary capitalize">{billingCycle}</span>
                    </div>
                    <div className="flex items-center justify-between">
                      <span className="text-text-primary">Price</span>
                      <span className="font-semibold text-text-primary">Coming Soon</span>
                    </div>
                  </div>

                  <div className="flex items-center gap-3">
                    <button
                      onClick={() => setStep('overview')}
                      className="flex-1 py-3 border border-border-light rounded-xl font-semibold text-text-primary hover:bg-surface transition-colors"
                    >
                      Back
                    </button>
                    <button
                      onClick={handleConfirm}
                      className="flex-1 py-3 bg-gold text-black rounded-xl font-semibold hover:bg-amber-400 transition-colors"
                    >
                      Confirm
                    </button>
                  </div>
                </div>
              )}

              {step === 'processing' && (
                <div className="p-12 text-center space-y-4">
                  <Loader2 className="w-12 h-12 text-gold animate-spin mx-auto" />
                  <h2 className="text-xl font-bold text-text-primary">Processing your request...</h2>
                  <p className="text-text-secondary">You'll be notified when Pro launches</p>
                </div>
              )}

              {step === 'success' && (
                <div className="p-12 text-center space-y-6">
                  <div className="w-16 h-16 rounded-full bg-green-400/20 flex items-center justify-center mx-auto">
                    <Check className="w-8 h-8 text-green-400" />
                  </div>
                  <div>
                    <h2 className="text-2xl font-bold text-text-primary mb-2">You're on the list!</h2>
                    <p className="text-text-secondary">
                      We'll notify you as soon as Pro is available. You'll get early access and special launch pricing.
                    </p>
                  </div>
                  <button
                    onClick={handleClose}
                    className="w-full py-3 bg-gold text-black rounded-xl font-semibold hover:bg-amber-400 transition-colors"
                  >
                    Got it
                  </button>
                </div>
              )}
            </div>
          </motion.div>
        </>
      )}
    </AnimatePresence>
  );
}
