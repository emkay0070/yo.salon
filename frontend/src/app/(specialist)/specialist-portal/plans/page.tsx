'use client';

import { motion } from 'framer-motion';
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
  Clock
} from 'lucide-react';
import Link from 'next/link';

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

const proFeatures = [
  { icon: Briefcase, name: 'Career', description: 'Career tracking & milestones' },
  { icon: TrendingUp, name: 'Intelligence', description: 'Business insights & analytics' },
  { icon: DollarSign, name: 'Finance', description: 'Revenue tracking & reports' },
  { icon: Clock, name: 'Journey', description: 'Growth goals & achievements' },
];

export default function PlansPage() {
  return (
    <div className="p-4 sm:p-6 lg:p-8 space-y-6">
      {/* Header */}
      <div className="text-center space-y-4">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          className="inline-flex items-center gap-2 px-4 py-2 bg-gold/20 rounded-full"
        >
          <Crown className="w-4 h-4 text-gold" />
          <span className="text-sm font-semibold text-gold">Upgrade Your Practice</span>
        </motion.div>
        
        <motion.h1
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.1 }}
          className="text-3xl sm:text-4xl font-bold text-text-primary"
        >
          Choose Your Plan
        </motion.h1>
        
        <motion.p
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.2 }}
          className="text-text-secondary max-w-2xl mx-auto"
        >
          Start with our free plan and upgrade when you're ready to unlock advanced business intelligence features.
        </motion.p>
      </div>

      {/* Plans */}
      <div className="grid md:grid-cols-2 gap-6 max-w-5xl mx-auto">
        {/* Free Plan */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.3 }}
          className="bg-card border border-border-light rounded-2xl p-6 space-y-6"
        >
          <div className="space-y-2">
            <div className="flex items-center gap-3">
              <div className="w-12 h-12 rounded-full bg-surface border border-border-light flex items-center justify-center">
                <Crown className="w-6 h-6 text-text-secondary" />
              </div>
              <div>
                <h2 className="text-2xl font-bold text-text-primary">Free</h2>
                <p className="text-text-secondary">Core professional tools</p>
              </div>
            </div>
            <div className="flex items-baseline gap-1">
              <span className="text-4xl font-bold text-text-primary">$0</span>
              <span className="text-text-secondary">/month</span>
            </div>
          </div>

          <div className="space-y-3">
            <h3 className="font-semibold text-text-primary">Included Features</h3>
            <div className="space-y-2">
              {freeFeatures.map((feature) => {
                const Icon = feature.icon;
                return (
                  <div key={feature.name} className="flex items-start gap-3">
                    <div className="w-5 h-5 rounded-full bg-gold/20 flex items-center justify-center shrink-0 mt-0.5">
                      <Check className="w-3 h-3 text-gold" />
                    </div>
                    <div>
                      <p className="font-medium text-text-primary">{feature.name}</p>
                      <p className="text-sm text-text-secondary">{feature.description}</p>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>

          <div className="space-y-3">
            <h3 className="font-semibold text-text-primary">Pro Features</h3>
            <div className="space-y-2">
              {proFeatures.map((feature) => {
                const Icon = feature.icon;
                return (
                  <div key={feature.name} className="flex items-start gap-3 opacity-50">
                    <div className="w-5 h-5 rounded-full bg-border-light flex items-center justify-center shrink-0 mt-0.5">
                      <X className="w-3 h-3 text-text-muted" />
                    </div>
                    <div>
                      <p className="font-medium text-text-primary">{feature.name}</p>
                      <p className="text-sm text-text-secondary">{feature.description}</p>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>

          <button className="w-full py-3 border border-border-light rounded-xl font-semibold text-text-primary hover:bg-surface transition-colors">
            Current Plan
          </button>
        </motion.div>

        {/* Pro Plan */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.4 }}
          className="bg-gradient-to-br from-gold/20 to-amber-600/10 border-2 border-gold/30 rounded-2xl p-6 space-y-6 relative overflow-hidden"
        >
          <div className="absolute top-4 right-4 px-3 py-1 bg-gold text-black text-xs font-bold rounded-full uppercase tracking-wider">
            Popular
          </div>

          <div className="space-y-2">
            <div className="flex items-center gap-3">
              <div className="w-12 h-12 rounded-full bg-gold/30 flex items-center justify-center">
                <Sparkles className="w-6 h-6 text-gold" />
              </div>
              <div>
                <h2 className="text-2xl font-bold text-text-primary">Pro</h2>
                <p className="text-text-secondary">Advanced business intelligence</p>
              </div>
            </div>
            <div className="flex items-baseline gap-1">
              <span className="text-4xl font-bold text-text-primary">Coming Soon</span>
              <span className="text-text-secondary">/month</span>
            </div>
          </div>

          <div className="space-y-3">
            <h3 className="font-semibold text-text-primary">Everything in Free, plus:</h3>
            <div className="space-y-2">
              {proFeatures.map((feature) => {
                const Icon = feature.icon;
                return (
                  <div key={feature.name} className="flex items-start gap-3">
                    <div className="w-5 h-5 rounded-full bg-gold/30 flex items-center justify-center shrink-0 mt-0.5">
                      <Sparkles className="w-3 h-3 text-gold" />
                    </div>
                    <div>
                      <p className="font-medium text-text-primary">{feature.name}</p>
                      <p className="text-sm text-text-secondary">{feature.description}</p>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>

          <div className="space-y-3">
            <h3 className="font-semibold text-text-primary">All Free Features</h3>
            <div className="space-y-2">
              {freeFeatures.map((feature) => {
                const Icon = feature.icon;
                return (
                  <div key={feature.name} className="flex items-start gap-3">
                    <div className="w-5 h-5 rounded-full bg-gold/20 flex items-center justify-center shrink-0 mt-0.5">
                      <Check className="w-3 h-3 text-gold" />
                    </div>
                    <div>
                      <p className="font-medium text-text-primary">{feature.name}</p>
                      <p className="text-sm text-text-secondary">{feature.description}</p>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>

          <button className="w-full py-3 bg-gold text-black rounded-xl font-semibold hover:bg-amber-400 transition-colors flex items-center justify-center gap-2">
            Upgrade to Pro
            <ArrowRight className="w-4 h-4" />
          </button>
        </motion.div>
      </div>

      {/* FAQ Section */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.5 }}
        className="max-w-3xl mx-auto space-y-4"
      >
        <h2 className="text-xl font-bold text-text-primary text-center">Frequently Asked Questions</h2>
        
        <div className="space-y-3">
          <div className="bg-card border border-border-light rounded-xl p-4">
            <h3 className="font-semibold text-text-primary mb-2">Can I switch plans at any time?</h3>
            <p className="text-sm text-text-secondary">Yes, you can upgrade or downgrade your plan at any time. Changes take effect immediately.</p>
          </div>
          
          <div className="bg-card border border-border-light rounded-xl p-4">
            <h3 className="font-semibold text-text-primary mb-2">What happens if I downgrade?</h3>
            <p className="text-sm text-text-secondary">If you downgrade to Free, you'll lose access to Pro features but retain all your data and Free features.</p>
          </div>
          
          <div className="bg-card border border-border-light rounded-xl p-4">
            <h3 className="font-semibold text-text-primary mb-2">Is there a free trial for Pro?</h3>
            <p className="text-sm text-text-secondary">Yes, Pro includes a 14-day free trial so you can experience all the advanced features risk-free.</p>
          </div>
        </div>
      </motion.div>
    </div>
  );
}
