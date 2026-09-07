'use client';

import { motion } from 'framer-motion';
import { 
  Clock, 
  Sparkles, 
  Calendar, 
  Check, 
  ArrowRight,
  AlertCircle,
  Loader2
} from 'lucide-react';
import { useState } from 'react';

interface TrialManagementProps {
  capabilities: any;
  onConvertToPaid?: () => void;
}

export default function TrialManagement({ capabilities, onConvertToPaid }: TrialManagementProps) {
  const isTrialing = capabilities?.subscription?.is_trialing;
  const trialEndsAt = capabilities?.subscription?.trial_ends_at;
  const [isConverting, setIsConverting] = useState(false);

  if (!isTrialing || !trialEndsAt) {
    return null;
  }

  const trialEndDate = new Date(trialEndsAt);
  const now = new Date();
  const daysRemaining = Math.ceil((trialEndDate.getTime() - now.getTime()) / (1000 * 60 * 60 * 24));
  const isEndingSoon = daysRemaining <= 3;

  const handleConvert = async () => {
    setIsConverting(true);
    // Simulate conversion process
    setTimeout(() => {
      setIsConverting(false);
      onConvertToPaid?.();
    }, 2000);
  };

  return (
    <div className="space-y-4">
      {/* Trial Status Banner */}
      <motion.div
        initial={{ opacity: 0, y: -10 }}
        animate={{ opacity: 1, y: 0 }}
        className={`bg-gradient-to-br backdrop-blur-xl border-2 rounded-2xl p-6 ${
          isEndingSoon
            ? 'from-red-500/20 to-orange-600/10 border-red-500/30'
            : 'from-blue-500/20 to-blue-600/10 border-blue-500/30'
        }`}
      >
        <div className="flex items-start gap-4">
          <div className={`w-14 h-14 rounded-full flex items-center justify-center shrink-0 ${
            isEndingSoon ? 'bg-red-500/30' : 'bg-blue-500/30'
          }`}>
            <Clock className={`w-7 h-7 ${isEndingSoon ? 'text-red-400' : 'text-blue-400'}`} />
          </div>
          <div className="flex-1">
            <div className="flex items-center justify-between mb-2">
              <h3 className="text-lg font-bold text-text-primary">
                {isEndingSoon ? 'Trial Ending Soon' : 'Pro Trial Active'}
              </h3>
              <span className={`px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider ${
                isEndingSoon
                  ? 'bg-red-500/30 text-red-400'
                  : 'bg-blue-500/30 text-blue-400'
              }`}>
                {daysRemaining} days left
              </span>
            </div>
            <p className="text-sm text-text-secondary mb-4">
              {isEndingSoon
                ? `Your trial ends on ${trialEndDate.toLocaleDateString()}. Convert to a paid subscription to keep access to Pro features.`
                : `Your trial ends on ${trialEndDate.toLocaleDateString()}. Explore all Pro features and see the value for yourself.`
              }
            </p>

            {/* Trial Progress */}
            <div className="mb-4">
              <div className="flex items-center justify-between text-xs text-text-secondary mb-2">
                <span>Trial Progress</span>
                <span>{Math.max(0, 14 - daysRemaining)} of 14 days used</span>
              </div>
              <div className="h-2 bg-surface rounded-full overflow-hidden">
                <motion.div
                  initial={{ width: 0 }}
                  animate={{ width: `${((14 - daysRemaining) / 14) * 100}%` }}
                  transition={{ duration: 0.5 }}
                  className={`h-full ${isEndingSoon ? 'bg-red-500' : 'bg-blue-500'} transition-all`}
                />
              </div>
            </div>

            {/* Trial Benefits */}
            <div className="grid grid-cols-2 gap-2 mb-4">
              <div className="flex items-center gap-2 text-sm">
                <Check className="w-4 h-4 text-gold" />
                <span className="text-text-primary">Career tracking</span>
              </div>
              <div className="flex items-center gap-2 text-sm">
                <Check className="w-4 h-4 text-gold" />
                <span className="text-text-primary">Intelligence</span>
              </div>
              <div className="flex items-center gap-2 text-sm">
                <Check className="w-4 h-4 text-gold" />
                <span className="text-text-primary">Finance reports</span>
              </div>
              <div className="flex items-center gap-2 text-sm">
                <Check className="w-4 h-4 text-gold" />
                <span className="text-text-primary">Journey goals</span>
              </div>
            </div>

            {/* Action Button */}
            <button
              onClick={handleConvert}
              disabled={isConverting}
              className={`w-full py-3 rounded-xl font-semibold transition-colors flex items-center justify-center gap-2 ${
                isConverting
                  ? 'bg-surface text-text-muted cursor-not-allowed'
                  : 'bg-gold text-black hover:bg-amber-400'
              }`}
            >
              {isConverting ? (
                <>
                  <Loader2 className="w-4 h-4 animate-spin" />
                  Processing...
                </>
              ) : (
                <>
                  Convert to Paid Subscription
                  <ArrowRight className="w-4 h-4" />
                </>
              )}
            </button>
          </div>
        </div>
      </motion.div>

      {/* Trial Tips */}
      {isEndingSoon && (
        <motion.div
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          className="bg-card border border-border-light rounded-xl p-4"
        >
          <div className="flex items-start gap-3">
            <AlertCircle className="w-5 h-5 text-amber-400 shrink-0 mt-0.5" />
            <div>
              <p className="font-medium text-text-primary mb-1">Don't lose your Pro features</p>
              <p className="text-sm text-text-secondary">
                When your trial ends, you'll automatically switch to the Free plan. 
                Convert now to keep uninterrupted access to all Pro features.
              </p>
            </div>
          </div>
        </motion.div>
      )}

      {!isEndingSoon && (
        <motion.div
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          className="bg-gradient-to-br from-gold/10 to-amber-600/5 border border-gold/20 rounded-xl p-4"
        >
          <div className="flex items-start gap-3">
            <Sparkles className="w-5 h-5 text-gold shrink-0 mt-0.5" />
            <div>
              <p className="font-medium text-text-primary mb-1">Make the most of your trial</p>
              <p className="text-sm text-text-secondary">
                Try out all Pro features including Career tracking, Intelligence insights, 
                Finance reports, and Journey goals. No credit card required until you decide to upgrade.
              </p>
            </div>
          </div>
        </motion.div>
      )}
    </div>
  );
}
