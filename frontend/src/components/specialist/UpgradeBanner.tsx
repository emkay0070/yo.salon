'use client';

import { motion } from 'framer-motion';
import { Crown, Sparkles, ArrowRight, X } from 'lucide-react';
import Link from 'next/link';

interface UpgradeBannerProps {
  featureName: string;
  description?: string;
  onDismiss?: () => void;
  compact?: boolean;
}

export default function UpgradeBanner({ 
  featureName, 
  description = `Unlock ${featureName} and other advanced features with Pro`,
  onDismiss,
  compact = false 
}: UpgradeBannerProps) {
  if (compact) {
    return (
      <motion.div
        initial={{ opacity: 0, y: -10 }}
        animate={{ opacity: 1, y: 0 }}
        className="bg-gradient-to-r from-gold/20 to-amber-600/10 border border-gold/30 rounded-xl p-4 flex items-center gap-4"
      >
        <div className="w-10 h-10 rounded-full bg-gold/30 flex items-center justify-center shrink-0">
          <Crown className="w-5 h-5 text-gold" />
        </div>
        <div className="flex-1 min-w-0">
          <p className="font-semibold text-text-primary text-sm">
            {featureName} is a Pro feature
          </p>
          <p className="text-xs text-text-secondary truncate">
            Upgrade to unlock advanced features
          </p>
        </div>
        <Link
          href="/specialist-portal/plans"
          className="px-4 py-2 bg-gold text-black rounded-lg text-sm font-semibold hover:bg-amber-400 transition-colors flex items-center gap-2 shrink-0"
        >
          Upgrade
          <ArrowRight className="w-4 h-4" />
        </Link>
        {onDismiss && (
          <button
            onClick={onDismiss}
            className="p-1 rounded-lg hover:bg-surface text-text-secondary shrink-0"
          >
            <X className="w-4 h-4" />
          </button>
        )}
      </motion.div>
    );
  }

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      className="bg-gradient-to-br from-gold/20 to-amber-600/10 border-2 border-gold/30 rounded-2xl p-6 space-y-4"
    >
      <div className="flex items-start justify-between">
        <div className="flex items-center gap-4">
          <div className="w-14 h-14 rounded-full bg-gold/30 flex items-center justify-center shrink-0">
            <Sparkles className="w-7 h-7 text-gold" />
          </div>
          <div>
            <h3 className="text-xl font-bold text-text-primary">
              Unlock {featureName}
            </h3>
            <p className="text-text-secondary">
              {description}
            </p>
          </div>
        </div>
        {onDismiss && (
          <button
            onClick={onDismiss}
            className="p-2 rounded-lg hover:bg-surface text-text-secondary"
          >
            <X className="w-5 h-5" />
          </button>
        )}
      </div>

      <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div className="bg-surface/50 rounded-xl p-3 text-center">
          <Crown className="w-5 h-5 text-gold mx-auto mb-1" />
          <p className="text-xs font-medium text-text-primary">Career</p>
        </div>
        <div className="bg-surface/50 rounded-xl p-3 text-center">
          <Crown className="w-5 h-5 text-gold mx-auto mb-1" />
          <p className="text-xs font-medium text-text-primary">Intelligence</p>
        </div>
        <div className="bg-surface/50 rounded-xl p-3 text-center">
          <Crown className="w-5 h-5 text-gold mx-auto mb-1" />
          <p className="text-xs font-medium text-text-primary">Finance</p>
        </div>
        <div className="bg-surface/50 rounded-xl p-3 text-center">
          <Crown className="w-5 h-5 text-gold mx-auto mb-1" />
          <p className="text-xs font-medium text-text-primary">Journey</p>
        </div>
      </div>

      <div className="flex items-center gap-3">
        <Link
          href="/specialist-portal/plans"
          className="flex-1 py-3 bg-gold text-black rounded-xl font-semibold hover:bg-amber-400 transition-colors flex items-center justify-center gap-2"
        >
          Upgrade to Pro
          <ArrowRight className="w-4 h-4" />
        </Link>
        <Link
          href="/specialist-portal/plans"
          className="px-4 py-3 border border-border-light rounded-xl font-semibold text-text-primary hover:bg-surface transition-colors"
        >
          View Plans
        </Link>
      </div>
    </motion.div>
  );
}
